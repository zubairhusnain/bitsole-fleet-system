<?php

namespace App\Http\Controllers;

use App\Models\Zones;
use App\Models\User;
use App\Models\TcGeofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZoneController extends Controller
{
    /**
     * List zones (auth-protected, role-aware) with pagination.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);
        // Optional: include soft-deleted (blocked) zones
        $query = $request->boolean('withDeleted') ? Zones::withTrashed() : Zones::query();

        // Filter by specific remote geofence id when provided
        if ($request->filled('geofenceId')) {
            $gfId = (int) $request->query('geofenceId');
            if ($gfId > 0) {
                $query->where('geofence_id', $gfId);
            }
        }

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId);
                if ($role === User::ROLE_FLEET_MANAGER) {
                    $managedIds = User::query()
                        ->where('manager_id', $user->id)
                        ->pluck('id')
                        ->all();
                    $query->whereIn('user_id', array_merge([$user->id], $managedIds));
                } else {
                    $query->where('user_id', $user->id);
                }
            }
            // Admin sees all zones
        }

        // Name-based search removed; local table no longer stores name

        // Include creator user relation so UI can show creator username
        $zones = $query->with('user')->orderByDesc('id')->paginate(25);
        // Enrich with Traccar geofence name/description/status/speed following driver pattern
        try {
            $ids = collect($zones->items())->map(fn($z) => (int) ($z->geofence_id ?? 0))->filter(fn($id) => $id > 0)->unique()->values();
            if ($ids->count()) {
                $remotes = TcGeofence::query()->whereIn('id', $ids)->get()->keyBy('id');
                $zones->getCollection()->transform(function ($z) use ($remotes) {
                    $gf = $remotes->get((int) ($z->geofence_id ?? 0));
                    if ($gf) {
                        // Traccar columns: name, description, area, attributes (JSON)
                        $z->setAttribute('name', $gf->name ?? null);
                        $z->setAttribute('description', $gf->description ?? null);
                        // Extract status/speed from attributes JSON if present
                        $attrs = null;
                        try {
                            if (is_array($gf->attributes)) {
                                $attrs = $gf->attributes;
                            } elseif (is_string($gf->attributes)) {
                                $attrs = json_decode($gf->attributes, true);
                            }
                        } catch (\Throwable $e) { $attrs = null; }
                        if (is_array($attrs)) {
                            $z->setAttribute('status', ($attrs['status'] ?? null) ?: $z->status ?? null);
                            $z->setAttribute('speed', isset($attrs['speed']) ? (float) $attrs['speed'] : ($z->speed ?? null));
                        }
                    }
                    return $z;
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to enrich zones with Traccar geofences', ['error' => $e->getMessage()]);
        }
        return response()->json($zones);
    }

    /**
     * Show a single zone with remote geofence details.
     */
    public function show(Request $request, int $zoneParam)
    {
        // Prefer geofence_id for lookups; fall back to local id for backward compatibility
        $zone = Zones::where('geofence_id', $zoneParam)->first();
        if (!$zone) { $zone = Zones::findOrFail($zoneParam); }
        Log::info('Zones.show called', ['zone_param' => $zoneParam, 'zone_found_id' => $zone->id, 'geofence_id' => $zone->geofence_id]);
        $geoId = (int) ($zoneParam ?? $zone->geofence_id ?? 0);
        $remote = $geoId > 0 ? ($this->geofencesService->getGeofenceById($request, $geoId) ?: null) : null;
        return response()->json(['zone' => $zone, 'geofence' => $remote]);
    }

    /**
     * List geofences directly from Traccar DB (tc_geofences) to aid testing.
     */
    public function geofencesDb(Request $request)
    {
        $pageSize = max(1, min((int) ($request->query('pageSize', 25)), 100));
        $query = TcGeofence::query()->orderByDesc('id');
        if ($name = $request->query('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        $list = $query->paginate($pageSize);
        // Normalize attributes to arrays for frontend
        $list->getCollection()->transform(function ($row) {
            $attrs = $row->attributes;
            if (is_string($attrs)) {
                try { $parsed = json_decode($attrs, true); if (is_array($parsed)) $attrs = $parsed; } catch (\Throwable $e) {}
            }
            $row->attributes = $attrs;
            return $row;
        });
        return response()->json($list);
    }

    /**
     * Create a new zone and geofence on tracking server.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
            'speed' => 'nullable|numeric',
            'coordinates' => 'nullable|string', // either single "lat,lng" or two pairs for rectangle ("lat,lng; lat,lng")
            'radius' => 'nullable|numeric',
            'polygon' => 'nullable|string', // "lat,lng; lat,lng; ..."
        ]);

        [$type, $coords, $lat, $lng, $radius] = $this->deriveShape($validated['coordinates'] ?? null, $validated['radius'] ?? null, $validated['polygon'] ?? null);

        // Prepare request for GeofencesService::addGeofence
        $request->merge([
            'name' => $validated['name'],
            'address' => $validated['description'] ?? '',
            'type' => $type,
            'coordinates' => $coords,
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
        ]);

        $tracking = $this->geofencesService->addGeofence($request);
        if (!isset($tracking->responseCode) || $tracking->responseCode < 200 || $tracking->responseCode >= 300) {
            return response()->json([
                'message' => 'Failed to create geofence on tracking server',
                'code' => $tracking->responseCode ?? 0,
                'error' => $tracking->error ?? null,
            ], 502);
        }

        $payload = json_decode($tracking->response, false);
        if (!$payload || !isset($payload->id)) {
            return response()->json([
                'message' => 'Unexpected response from tracking server',
                'response' => $tracking->response ?? null,
            ], 500);
        }

        // Derive local user/distributor based on role
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);
        if ($role === User::ROLE_ADMIN || $role === User::ROLE_DISTRIBUTOR) {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->id;
        } else {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->distributor_id ?? $user->id;
        }

        $zone = Zones::create([
            'user_id' => $userIdLocal,
            'distributor_id' => $distributorIdLocal,
            'geofence_id' => (int) $payload->id,
        ]);

        return response()->json([
            'message' => 'Zone created',
            'geofence' => $payload,
            'zone' => $zone,
        ], 201);
    }

    /**
     * Update a zone and its remote geofence.
     */
    public function update(Request $request, int $zoneParam)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
            'speed' => 'nullable|numeric',
            'coordinates' => 'nullable|string',
            'radius' => 'nullable|numeric',
            'polygon' => 'nullable|string',
        ]);

        $zone = Zones::where('geofence_id', $zoneParam)->first();
        if (!$zone) { $zone = Zones::findOrFail($zoneParam); }
        $geoId = (int) ($zone->geofence_id ?? 0);

        [$type, $coords, $lat, $lng, $radius] = $this->deriveShape($validated['coordinates'] ?? null, $validated['radius'] ?? null, $validated['polygon'] ?? null);

        // Build WKT using GeofencesService logic by temporarily merging and calling addGeofence's helpers
        $tmpReq = clone $request;
        $tmpReq->merge([
            'name' => $validated['name'],
            'address' => $validated['description'] ?? '',
            'type' => $type,
            'coordinates' => $coords,
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
        ]);

        // Compute WKT via service
        $wkt = $this->geofencesService->computeWKT($type, $coords, $lat, $lng, $radius);

        // Include other values as geofence attributes (driver pattern)
        $attributes = [
            'user_id' => $request->user()->id,
            'type' => $type,
            'lat' => $lat,
            'long' => $lng,
            'radius' => $radius,
            'coordinates' => $coords,
            'status' => $validated['status'] ?? null,
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
            'distributor_id' => $request->user()->distributor_id ?? null,
        ];

        // Update via service
        $resp = $this->geofencesService->updateGeofence(
            $request,
            $geoId,
            $validated['name'],
            $validated['description'] ?? '',
            $wkt,
            $attributes
        );
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            return response()->json([
                'message' => 'Failed to update geofence on tracking server',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        // No local metadata update; attributes are stored remotely in Traccar

        $payload = json_decode($resp->response, false);
        return response()->json([
            'message' => 'Zone updated',
            'geofence' => $payload,
            'zone' => $zone,
        ], 200);
    }

    /**
     * Delete a zone and its remote geofence.
     */
    public function destroy(Request $request, int $zoneParam)
    {
        // Soft delete by default (block). Use force=1 (or hard=1) to permanently delete remotely + locally.
        $force = $request->boolean('force') || $request->boolean('hard');

        if (!$force) {
            // SOFT DELETE (block): mark local record as deleted, do not delete on tracking server
            $zone = Zones::where('geofence_id', $zoneParam)->first();
            if (!$zone) { $zone = Zones::find($zoneParam); }
            if (!$zone) {
                return response()->json(['message' => 'Zone not found'], 404);
            }
            if (method_exists($zone, 'trashed') && $zone->trashed()) {
                return response()->json(['message' => 'Zone already blocked'], 200);
            }
            try {
                $zone->delete();
            } catch (\Throwable $e) {
            Log::warning('Zone soft delete failed', ['zone_param' => $zoneParam, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to block zone',
                'error' => $e->getMessage(),
            ], 500);
            }
            return response()->json(['message' => 'Zone blocked'], 200);
        }

        // HARD DELETE: delete geofence on tracking server (Traccar), permanently remove local record
        $zone = Zones::withTrashed()->where('geofence_id', $zoneParam)->first();
        if (!$zone) { $zone = Zones::withTrashed()->find($zoneParam); }
        if (!$zone) {
            return response()->json(['message' => 'Zone not found'], 404);
        }

        $geoId = (int) ($zone->geofence_id ?? 0);
        $request->merge(['geofenceId' => $geoId]);

        // Delete on tracking server (ignore 404)
        try {
            $tracking = $this->geofencesService->deleteGeofence($request);
            $code = (int) ($tracking->responseCode ?? 0);
            if (!($code >= 200 && $code < 300) && $code !== 404) {
                return response()->json([
                    'message' => 'Failed to delete geofence on tracking server',
                    'code' => $code,
                    'error' => $tracking->error ?? null,
                ], 502);
            }
        } catch (\Throwable $e) {
            Log::warning('Tracking server geofence delete failed', ['zone_param' => $zoneParam, 'geofence_id' => $geoId, 'error' => $e->getMessage()]);
        }

        try {
            $zone->forceDelete();
        } catch (\Throwable $e) {
            Log::warning('Zone hard delete failed', ['zone_param' => $zoneParam, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to permanently delete zone',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Zone deleted']);
    }

    /**
     * Restore (activate) a soft-deleted zone.
     */
    public function restore(Request $request, int $zoneParam)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        // Prefer geofence_id for lookups
        $query = Zones::withTrashed()->where('geofence_id', $zoneParam);
        if (!$query->count()) { $query = Zones::withTrashed()->where('id', $zoneParam); }
        if ($role === User::ROLE_DISTRIBUTOR) {
            $query->where('distributor_id', $user->id);
        } elseif ($role !== User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)->where('user_id', $user->id);
        }

        $zone = $query->first();
        if (!$zone) {
            return response()->json(['message' => 'Zone not found'], 404);
        }
        if (method_exists($zone, 'trashed') && !$zone->trashed()) {
            return response()->json(['message' => 'Zone already active'], 200);
        }

        try {
            $zone->restore();
        } catch (\Throwable $e) {
            Log::warning('Zone restore failed', ['zone_param' => $zoneParam, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to activate zone',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Zone activated'], 200);
    }

    /**
     * Helper: derive shape/type from inputs.
     * Returns: [type, coordinates(array), lat, lng, radius]
     */
    protected function deriveShape(?string $coordinates, ?float $radius, ?string $polygon): array
    {
        $coords = [];
        $lat = null; $lng = null; $type = null;

        $polygonArr = $this->polygonToArray($polygon);
        if ($polygonArr && count($polygonArr) >= 3) {
            $type = 'polygon';
            $coords = $polygonArr;
            return [$type, $coords, $lat, $lng, (float) ($radius ?? 0)];
        }

        if ($coordinates) {
            $pairs = array_map('trim', explode(';', $coordinates));
            if (count($pairs) >= 2) {
                // Rectangle using two points
                $p1 = $this->parsePair($pairs[0]);
                $p2 = $this->parsePair($pairs[1]);
                if ($p1 && $p2) {
                    $type = 'rectangle';
                    $coords = [$p1, $p2];
                    return [$type, $coords, $lat, $lng, (float) ($radius ?? 0)];
                }
            } else {
                // Single coord + radius => circle
                $p1 = $this->parsePair($pairs[0]);
                if ($p1 && ($radius ?? 0) > 0) {
                    $type = 'circle';
                    $lat = $p1[0];
                    $lng = $p1[1];
                    $coords = [$p1];
                    return [$type, $coords, $lat, $lng, (float) $radius];
                }
            }
        }

        // Default to polygon demo around St. Louis
        $type = 'polygon';
        $coords = [
            [38.68, -90.33],
            [38.70, -90.10],
            [38.60, -90.05],
            [38.58, -90.30],
            [38.68, -90.33],
        ];
        return [$type, $coords, $lat, $lng, (float) ($radius ?? 0)];
    }

    protected function parsePair(string $pair): ?array
    {
        $parts = array_map('trim', explode(',', $pair));
        if (count($parts) !== 2) return null;
        $lat = (float) $parts[0];
        $lng = (float) $parts[1];
        if (!is_finite($lat) || !is_finite($lng)) return null;
        return [$lat, $lng];
    }

    protected function polygonToArray(?string $polygon): ?array
    {
        if (!$polygon) return null;
        $pairs = array_map('trim', explode(';', $polygon));
        $pts = [];
        foreach ($pairs as $p) {
            $pp = $this->parsePair($p);
            if ($pp) { $pts[] = $pp; }
        }
        return $pts ?: null;
    }
}
