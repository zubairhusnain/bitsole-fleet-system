<?php

namespace App\Http\Controllers;

use App\Models\Zones;
use App\Models\User;
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

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('user_id', $user->id)
                      ->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)
                      ->where('user_id', $user->id);
            }
            // Admin sees all zones
        }

        // Optional search by name
        if ($name = $request->query('name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        $zones = $query->orderByDesc('id')->paginate(25);
        return response()->json($zones);
    }

    /**
     * Show a single zone with remote geofence details.
     */
    public function show(Request $request, int $zoneId)
    {
        $zone = Zones::findOrFail($zoneId);

        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $geoId = (int) ($zone->geofence_id ?? 0);
        $remote = null;
        if ($geoId > 0) {
            $resp = static::curl('/api/geofences?id=' . $geoId, 'GET', $sessionId, '', ['Accept: application/json']);
            if (($resp->responseCode ?? 0) >= 200 && ($resp->responseCode ?? 0) < 300) {
                $payload = json_decode($resp->response, true);
                $remote = is_array($payload) && count($payload) ? $payload[0] : null;
            } else {
                Log::warning('Failed to fetch geofence for zone', ['zone_id' => $zoneId, 'geofence_id' => $geoId, 'code' => $resp->responseCode ?? null, 'error' => $resp->error ?? null]);
            }
        }

        return response()->json(['zone' => $zone, 'geofence' => $remote]);
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
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
            'coordinates' => $coords,
            'radius' => $radius,
            'polygon' => $this->polygonToArray($validated['polygon'] ?? null),
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
    public function update(Request $request, int $zoneId)
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

        $zone = Zones::findOrFail($zoneId);
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

        // Rebuild the same WKT as addGeofence would generate
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        // Invoke service to compute WKT by piggybacking on addGeofence's logic without posting
        // We duplicate the WKT construction here to avoid a new service method
        $wkt = '';
        if ($type === 'circle') {
            $wkt = $this->geofencesService->createCircleWKT($lat, $lng, $radius);
        } elseif ($type === 'rectangle') {
            $p1 = $coords[0]; $p2 = $coords[1];
            $wkt = "POLYGON(({$p1[1]} {$p1[0]}, {$p2[1]} {$p1[0]}, {$p2[1]} {$p2[0]}, {$p1[1]} {$p2[0]}, {$p1[1]} {$p1[0]}))";
        } elseif ($type === 'polygon') {
            $wktPts = [];
            foreach ($coords as $pt) { $wktPts[] = $pt[1] . ' ' . $pt[0]; }
            if ($wktPts[0] !== end($wktPts)) { $wktPts[] = $wktPts[0]; }
            $wkt = 'POLYGON((' . implode(', ', $wktPts) . '))';
        } elseif ($type === 'route') {
            $wktPts = [];
            foreach ($coords as $pt) { $wktPts[] = $pt[1] . ' ' . $pt[0]; }
            $wkt = 'ROUTE(' . implode(', ', $wktPts) . ')';
        }

        // Include other values as geofence attributes (driver pattern)
        $attributes = [
            'user_id' => $request->user()->id,
            'type' => $type,
            'lat' => $lat,
            'long' => $lng,
            'radius' => $radius,
            'coordinates' => $coords,
            'status' => $validated['status'] ?? $zone->status,
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : $zone->speed,
            'distributor_id' => $request->user()->distributor_id ?? null,
        ];

        $data = json_encode([
            'id' => $geoId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'area' => $wkt,
            'attributes' => $attributes,
        ]);

        $resp = static::curl('/api/geofences/' . $geoId, 'PUT', $sessionId, $data, ['Content-Type: application/json', 'Accept: application/json']);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            return response()->json([
                'message' => 'Failed to update geofence on tracking server',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        // Update local zone
        $zone->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $zone->status,
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : $zone->speed,
            'coordinates' => $coords,
            'radius' => $radius,
            'polygon' => $this->polygonToArray($validated['polygon'] ?? null),
        ]);

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
    public function destroy(Request $request, int $zoneId)
    {
        // Soft delete by default (block). Use force=1 (or hard=1) to permanently delete remotely + locally.
        $force = $request->boolean('force') || $request->boolean('hard');

        if (!$force) {
            // SOFT DELETE (block): mark local record as deleted, do not delete on tracking server
            $zone = Zones::find($zoneId);
            if (!$zone) {
                return response()->json(['message' => 'Zone not found'], 404);
            }
            if (method_exists($zone, 'trashed') && $zone->trashed()) {
                return response()->json(['message' => 'Zone already blocked'], 200);
            }
            try {
                $zone->delete();
            } catch (\Throwable $e) {
                Log::warning('Zone soft delete failed', ['zone_id' => $zoneId, 'error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to block zone',
                    'error' => $e->getMessage(),
                ], 500);
            }
            return response()->json(['message' => 'Zone blocked'], 200);
        }

        // HARD DELETE: delete geofence on tracking server (Traccar), permanently remove local record
        $zone = Zones::withTrashed()->find($zoneId);
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
            Log::warning('Tracking server geofence delete failed', ['zone_id' => $zoneId, 'geofence_id' => $geoId, 'error' => $e->getMessage()]);
        }

        try {
            $zone->forceDelete();
        } catch (\Throwable $e) {
            Log::warning('Zone hard delete failed', ['zone_id' => $zoneId, 'error' => $e->getMessage()]);
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
    public function restore(Request $request, int $zoneId)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        $query = Zones::withTrashed()->where('id', $zoneId);
        if ($role === User::ROLE_DISTRIBUTOR) {
            $query->where('user_id', $user->id)->where('distributor_id', $user->id);
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
            Log::warning('Zone restore failed', ['zone_id' => $zoneId, 'error' => $e->getMessage()]);
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