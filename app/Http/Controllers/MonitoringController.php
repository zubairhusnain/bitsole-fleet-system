<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use App\Models\Zones;
use App\Models\TcGeofence;
use App\Models\TcDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\DeviceService;

class MonitoringController extends Controller
{
    /**
     * Helper to parse attributes safely.
     */
    private function parseAttributes($attrs)
    {
        if (is_array($attrs)) {
            return $attrs;
        }
        if (is_string($attrs)) {
            try {
                return json_decode($attrs, true) ?? [];
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }

    /**
     * Helper to format date.
     */
    private function formatDate($dateStr)
    {
        if (!$dateStr) {
            return 'N/A';
        }
        return date('d/m/Y-H:i', strtotime($dateStr));
    }

    /**
     * Get zone details with full vehicle list.
     */
    public function zoneDetail(Request $request, $id)
    {
        $user = $request->user();
        // Accept either a remote geofence ID or a local Zones.id and resolve to geofence_id
        $gid = (int) $id;
        $zone = Zones::where('geofence_id', $gid)->first();
        if (!$zone) {
            $zone = Zones::find($gid);
            if ($zone && (int) $zone->geofence_id > 0) {
                $gid = (int) $zone->geofence_id;
            }
        }

        // Check permission (reuse logic or simplify if simple ownership)
        // For now, assuming if they can see summary, they can see detail.
        // But strictly we should check if this $gid is in allowed list.

        $gf = TcGeofence::find($gid);
        if (!$gf) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        // Verify access
        $canAccess = false;
        $role = (int) ($user->role ?? User::ROLE_ADMIN);
        $localZone = Zones::where('geofence_id', $gid)->first();

        if ($request->boolean('mine')) {
            if ($localZone && $localZone->user_id == $user->id) {
                $canAccess = true;
            }
        } else {
             // simplified check: if admin or if part of allowed zones
             // Ideally reusing the query from zoneSummary would be better but expensive.
             // Let's assume for now if they have the ID and are auth'd, we check basic ownership if not admin.
             if ($role === User::ROLE_ADMIN) {
                 $canAccess = true;
             } elseif ($localZone) {
                 if ($role === User::ROLE_DISTRIBUTOR && $localZone->distributor_id == $user->id) {
                     $canAccess = true;
                 } elseif ($role === User::ROLE_FLEET_MANAGER && $localZone->user_id == $user->id) {
                     $canAccess = true;
                 } elseif ($localZone->user_id == $user->manager_id) {
                     $canAccess = true;
                 }
             }
        }

        // If not found in local Zones but exists in TcGeofence and user is admin?
        if (!$localZone && $role === User::ROLE_ADMIN) {
            $canAccess = true;
        }

        if (!$canAccess) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Fetch vehicles
        $deviceIds = Devices::accessibleByUser($user)->pluck('device_id')->filter()->unique()->values();
        $totalDevices = $deviceIds->count();

        $links = DB::connection('pgsql')
            ->table('tc_device_geofence')
            ->where('geofenceid', $gid)
            ->whereIn('deviceid', $deviceIds->all())
            ->get(['deviceid']);

        $linkedDeviceIds = $links->pluck('deviceid')->unique()->values()->all();
        $deviceRows = TcDevice::with('position')->whereIn('id', $linkedDeviceIds)->get()->keyBy('id');

        $vehicles = collect($linkedDeviceIds)->map(function ($did) use ($deviceRows) {
            $row = $deviceRows->get((int) $did);
            if (!$row) {
                return null;
            }

            $pos = $row->position;
            $attrs = $this->parseAttributes($row->attributes);
            $posAttrs = $pos ? $this->parseAttributes($pos->attributes) : [];

            $vehicle_no = $attrs['vehicleNo'] ?? ($attrs['vehicle_id'] ?? ($attrs['vehicleId'] ?? ($attrs['vehicleID'] ?? null)));
            $name = $row->name ?? $vehicle_no;
            $ignition = $posAttrs['ignition'] ?? false;

            // Status
            $deviceStatus = trim(strtolower($row->status ?? ''));
            $isOnline = ($deviceStatus === 'online') || (strtotime($row->lastupdate) > time() - 300);
            $status = $isOnline ? 'Online' : 'Offline';

            return [
                'id' => $row->id,
                'name' => $name,
                'vehicle_no' => $vehicle_no,
                'uniqueid' => $row->uniqueid,
                'active' => (bool) $ignition,
                'ignition' => (bool) $ignition,
                'last_ignition_on' => 'N/A', // Deprecated
                'last_ignition_off' => 'N/A', // Deprecated
                'status' => $status,
                'speed' => $pos ? round($pos->speed * 1.852, 1) : 0,
                'lat' => $pos ? $pos->latitude : 0,
                'lng' => $pos ? $pos->longitude : 0,
                'last_update' => $pos ? $pos->servertime : $row->lastupdate,
                'address' => $pos ? $pos->address : '',
                'type' => $attrs['type'] ?? 'Unknown',
                'model' => $row->model ?? 'Unknown',
                'odometer' => isset($posAttrs['odometer']) ? round($posAttrs['odometer'] / 1000, 1) : 0,
            ];
        })->filter()->values()->all();

        $count = count($vehicles);
        $percent = $totalDevices > 0 ? (int) floor(($count / $totalDevices) * 100) : 0;

        // Return geofence payload in the same format as getGeofenceById,
        // alongside monitoring-specific fields (vehicles, count, percent)
        $geofence = $this->geofencesService->getGeofenceById($request, $gid);
        if (!$geofence) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        return response()->json([
            'geofence' => $geofence,
            'count' => $count,
            'percent' => $percent,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Get zone monitoring summary (grouped by zone with vehicle counts).
     */
    public function zoneSummary(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        $zoneQuery = Zones::query();
        if ($request->boolean('mine')) {
            $zoneQuery->where('user_id', $user->id);
        } else {
            if ($role === User::ROLE_DISTRIBUTOR) {
                $zoneQuery->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $zoneQuery->where('distributor_id', $distId);
                if ($role === User::ROLE_FLEET_MANAGER) {
                    $zoneQuery->where('user_id', $user->id);
                } else {
                    $zoneQuery->where('user_id', $user->manager_id);
                }
            }
        }
        $allowedIds = $zoneQuery->pluck('geofence_id')->filter()->unique()->values();
        if ($allowedIds->isEmpty()) {
            return response()->json(['zones' => [], 'total_devices' => 0]);
        }

        $deviceIds = Devices::accessibleByUser($user)->pluck('device_id')->filter()->unique()->values();
        $totalDevices = $deviceIds->count();

        $links = DB::connection('pgsql')
            ->table('tc_device_geofence')
            ->whereIn('geofenceid', $allowedIds->all())
            ->whereIn('deviceid', $deviceIds->all())
            ->get(['deviceid', 'geofenceid']);

        $assignedCounts = [];
        foreach ($links as $ln) {
            $gid = (int) $ln->geofenceid;
            if (!isset($assignedCounts[$gid])) $assignedCounts[$gid] = 0;
            $assignedCounts[$gid]++;
        }


        // Real-time "Inside" Counts based on device position
        $insideCounts = [];
        $devicesInsideAny = [];

        $devicesWithPos = TcDevice::with('position')
            ->whereIn('id', $deviceIds->all())
            ->get();

        // Pre-fetch zones and parse polygons for fallback check
        $zoneRows = TcGeofence::query()->whereIn('id', $allowedIds->all())->get()->keyBy('id');
        $zonePolygons = [];
        foreach ($zoneRows as $z) {
            if (!empty($z->area) && str_starts_with($z->area, 'POLYGON')) {
                // Parse WKT: POLYGON((lon lat, lon lat, ...))
                if (preg_match('/\(\((.*?)\)\)/', $z->area, $matches)) {
                    $pointsStr = explode(',', $matches[1]);
                    $polygon = [];
                    foreach ($pointsStr as $pStr) {
                        $coords = preg_split('/\s+/', trim($pStr));
                        if (count($coords) >= 2) {
                            // WKT is usually LON LAT
                            $polygon[] = ['lat' => (float)$coords[1], 'lon' => (float)$coords[0]];
                        }
                    }
                    $zonePolygons[$z->id] = $polygon;
                }
            }
        }

        foreach ($devicesWithPos as $dev) {
            if (!$dev->position) continue;

            $detectedForDevice = [];

            // 1. Check Traccar-provided geofenceids
            if (!empty($dev->position->geofenceids)) {
                $gids = explode(',', $dev->position->geofenceids);
                foreach ($gids as $gidStr) {
                    $gid = (int) trim($gidStr);
                    if ($gid > 0 && in_array($gid, $allowedIds->all())) {
                        $detectedForDevice[$gid] = true;
                    }
                }
            }

            // 2. Fallback: Check Point-In-Polygon for zones not yet detected
            // This handles cases where Traccar hasn't computed the geofence entry yet
            if ($dev->position->latitude && $dev->position->longitude) {
                foreach ($zonePolygons as $zid => $poly) {
                    if (isset($detectedForDevice[$zid])) continue;

                    if ($this->isPointInPolygon($dev->position->latitude, $dev->position->longitude, $poly)) {
                        $detectedForDevice[$zid] = true;
                    }
                }
            }

            // Update counts based on combined results
            $inAllowed = false;
            foreach ($detectedForDevice as $gid => $_) {
                if (!isset($insideCounts[$gid])) $insideCounts[$gid] = 0;
                $insideCounts[$gid]++;
                $inAllowed = true;
            }

            if ($inAllowed) {
                $devicesInsideAny[] = $dev->id;
            }
        }

        $vehiclesInAnyZone = count(array_unique($devicesInsideAny));

        $localZones = Zones::whereIn('geofence_id', $allowedIds->all())->get()->keyBy('geofence_id');

        $zones = $allowedIds->map(function ($gid) use ($zoneRows, $assignedCounts, $insideCounts, $totalDevices, $localZones) {
            $gf = $zoneRows->get((int) $gid);
            if (!$gf) return null;

            $localZone = $localZones->get((int) $gid);
            $assigned = $assignedCounts[(int) $gid] ?? 0;
            $inside = $insideCounts[(int) $gid] ?? 0;
            $percent = $totalDevices > 0 ? (int) floor(($assigned / $totalDevices) * 100) : 0;
 
            return [
                'id' => (int) $gid,
                'name' => $gf->name ?? ('Zone ' . $gid),
                'description' => $gf->description ?? null,
                'created_at' => $localZone ? $localZone->created_at : null,
                'updated_at' => $localZone ? $localZone->updated_at : null,
                'status' => $localZone ? $localZone->status : 'active',
                'count' => $assigned, // Backward compatibility (Assign Vehicles)
                'assigned_count' => $assigned,
                'inside_count' => $inside,
                'percent' => $percent,
                'vehicles' => [], // Empty to improve performance
            ];
        })->filter()->values()->all();


        return response()->json([
            'zones' => $zones,
            'total_devices' => $totalDevices,
            'vehicles_in_zone' => $vehiclesInAnyZone
        ]);
    }

    /**
     * List vehicles for monitoring with detailed attributes.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Apply role-based access control
        if ($request->boolean('mine')) {
            $query = Devices::accessibleByUser($user);
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } else {
            $query = Devices::accessibleByUser($user);
        }

        // Eager load tcDevice and its current position
        // We include soft-deleted devices if they are still relevant for monitoring history,
        // but typically monitoring focuses on active devices.
        // As per request, we remove deleted vehicles from monitoring list.
        // Also changing owner to manager instead of distributor.
        $query->with(['tcDevice.position', 'manager']);

        // Pagination or fetch all
        $perPage = $request->input('per_page', 25);

        // If per_page is -1 or very large, we might want to return all, but paginate is safer.
        // The frontend requests per_page=500 in fetchVehicles.
        $devices = $query->orderByDesc('id')->paginate($perPage);

        // Fetch last ignition events for these devices
        $deviceIds = $devices->pluck('device_id')->unique()->values()->all();

        if (!empty($deviceIds)) {
            $ignitionEvents = DB::connection('pgsql')
                ->table('tc_events')
                ->select('deviceid', 'type', DB::raw('MAX(eventtime) as last_time'))
                ->whereIn('deviceid', $deviceIds)
                ->whereIn('type', ['ignitionOn', 'ignitionOff'])
                ->groupBy('deviceid', 'type')
                ->get();

            $ignitionTimes = [];
            foreach ($ignitionEvents as $evt) {
                $ignitionTimes[$evt->deviceid][$evt->type] = $evt->last_time;
            }

            // Helper for date formatting
            $formatDate = function ($dateStr) {
                if (!$dateStr) return null;
                return date('d/m/Y-H:i', strtotime($dateStr));
            };

            // Enrich the devices collection
            $devices->getCollection()->transform(function ($device) use ($ignitionTimes, $formatDate) {
                $ignOnTime = $ignitionTimes[$device->device_id]['ignitionOn'] ?? null;
                $ignOffTime = $ignitionTimes[$device->device_id]['ignitionOff'] ?? null;

                $device->last_ignition_on = $ignOnTime ? $ignOnTime : null;
                $device->last_ignition_off = $ignOffTime ? $ignOffTime : null;

                return $device;
            });
        }

        // Enrich with alerts and maintenance counts
        $deviceIds = $devices->pluck('device_id')->toArray();

        if (!empty($deviceIds)) {
            // Retrieve unread events using Eloquent relationships
            $events = \App\Models\TcEvent::with(['notifications'])
                ->whereIn('tc_events.deviceid', $deviceIds)
                ->where('is_read', 0)
                ->withEnabledNotifications()
                ->get();

            // Group by device and count types
            $alertCounts = [];
            $maintenanceCounts = [];
            $frequentIgnitionCounts = [];

            foreach ($events as $event) {
                $dId = $event->deviceid;
                
                if ($event->type === 'frequentIgnition') {
                    if (!isset($frequentIgnitionCounts[$dId])) $frequentIgnitionCounts[$dId] = 0;
                    $frequentIgnitionCounts[$dId]++;
                }

                if ($event->type === 'maintenance') {
                    if (!isset($maintenanceCounts[$dId])) $maintenanceCounts[$dId] = 0;
                    $maintenanceCounts[$dId]++;
                } else {
                    if (!isset($alertCounts[$dId])) $alertCounts[$dId] = 0;
                    $alertCounts[$dId]++;
                }
            }

            $devices->getCollection()->transform(function ($device) use ($alertCounts, $maintenanceCounts, $frequentIgnitionCounts) {
                $dId = $device->device_id;
                $device->alert_count = $alertCounts[$dId] ?? 0;
                $device->frequent_ignition_count = $frequentIgnitionCounts[$dId] ?? 0;
                $mCount = $maintenanceCounts[$dId] ?? 0;
                $device->maintenance_count = $mCount;

                // Add vehicle_no for detail modal
                $attrs = $device->tcDevice && $device->tcDevice->attributes
                    ? (is_array($device->tcDevice->attributes) ? $device->tcDevice->attributes : json_decode($device->tcDevice->attributes, true))
                    : [];
                $device->vehicle_no = $attrs['vehicleNo'] ?? ($attrs['vehicle_id'] ?? ($attrs['vehicleId'] ?? ($attrs['vehicleID'] ?? null)));

                // Format maintenance string for display
                if ($mCount > 0) {
                     $device->maintenance_display = $mCount . ' Due';
                } else {
                     $device->maintenance_display = 'N/A';
                }

                return $device;
            });
        }

        // Calculate Global Stats
        $allDeviceIds = $query->pluck('device_id')->toArray();
        $totalVehicles = count($allDeviceIds);

        $ignitionOn = 0;
        $ignitionOff = 0;
        $maintenanceVehicles = 0;
        $alertVehicles = 0;
        $movingVehicles = 0;
        $stoppedVehicles = 0;
        $idleVehicles = 0;

        if ($totalVehicles > 0) {
            // Ignition Stats (from tc_positions via TcDevice)
            // Assuming 'attributes' column in tc_positions has {"ignition": true/false}
            // We use whereIn on TcDevice (pgsql)
            $ignitionOn = \App\Models\TcDevice::whereIn('id', $allDeviceIds)
                ->whereHas('position', function ($q) {
                    // Postgres JSON operator ->> requires casting text column to json
                    $q->whereRaw("CAST(attributes AS json)->>'ignition' = 'true'")
                      ->orWhereRaw("CAST(attributes AS json)->>'ignition' = '1'");
                })
                ->count();

            $ignitionOff = $totalVehicles - $ignitionOn;

            $movingVehicles = \App\Models\TcDevice::whereIn('id', $allDeviceIds)
                ->whereHas('position', function ($q) {
                    $q->whereRaw("CAST(attributes AS json)->>'motion' = '1'")
                      ->orWhere('speed', '>', 0);
                })
                ->count();

            $idleVehicles = \App\Models\TcDevice::whereIn('id', $allDeviceIds)
                ->whereHas('position', function ($q) {
                    $q->where(function ($w1) {
                        $w1->whereRaw("CAST(attributes AS json)->>'motion' = '0'")
                           ->where(function ($wIgn) {
                               $wIgn->whereRaw("CAST(attributes AS json)->>'ignition' = 'true'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' = '1'");
                           });
                    })->orWhere(function ($w2) {
                        $w2->where('speed', '=', 0)
                           ->where(function ($wIgn) {
                               $wIgn->whereRaw("CAST(attributes AS json)->>'ignition' = 'true'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' = '1'");
                           });
                    });
                })
                ->count();

            $stoppedVehicles = \App\Models\TcDevice::whereIn('id', $allDeviceIds)
                ->whereHas('position', function ($q) {
                    $q->where(function ($w1) {
                        $w1->whereRaw("CAST(attributes AS json)->>'motion' = '0'")
                           ->where(function ($wIgn) {
                               $wIgn->whereRaw("CAST(attributes AS json)->>'ignition' = 'false'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' = '0'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' IS NULL");
                           });
                    })->orWhere(function ($w2) {
                        $w2->where('speed', '=', 0)
                           ->where(function ($wIgn) {
                               $wIgn->whereRaw("CAST(attributes AS json)->>'ignition' = 'false'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' = '0'")
                                    ->orWhereRaw("CAST(attributes AS json)->>'ignition' IS NULL");
                           });
                    });
                })
                ->count();

            // Maintenance & Alerts Stats (from tc_events)
            // We reuse the logic for filtering relevant notifications
            $globalEventsQuery = \App\Models\TcEvent::query()
                ->whereIn('tc_events.deviceid', $allDeviceIds)
                ->where('is_read', 0)
                ->withEnabledNotifications();

            // Count distinct devices with maintenance
            $maintenanceVehicles = (clone $globalEventsQuery)
                ->where('tc_events.type', 'maintenance')
                ->distinct('tc_events.deviceid')
                ->count('tc_events.deviceid');

            // Count distinct devices with alerts (type != maintenance)
            $alertVehicles = (clone $globalEventsQuery)
                ->where('tc_events.type', '!=', 'maintenance')
                ->distinct('tc_events.deviceid')
                ->count('tc_events.deviceid');
        }

        $response = $devices->toArray();
        $response['stats'] = [
            'total' => $totalVehicles,
            'ignitionOn' => $ignitionOn,
            'ignitionOff' => $ignitionOff,
            'moving' => $movingVehicles,
            'stopped' => $stoppedVehicles,
            'idle' => $idleVehicles,
            'maintenance' => $maintenanceVehicles,
            'alerts' => $alertVehicles,
        ];

        return response()->json($response);
    }

    /**
     * Get detailed vehicle information for monitoring.
     */
    public function show($id)
    {
        // Search by device_id (Traccar ID) first, then fallback to internal ID
        $device = Devices::with(['tcDevice.position', 'manager'])
            ->where('device_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        // Get last ignition events
        // Note: TcEvent uses deviceid which corresponds to tc_devices.id
        // User requested to use tc_devices.lastupdate instead of querying tc_events for ignition times
        // to avoid column errors (servertime/eventtime) and simplify logic.
        $lastUpdate = $device->tcDevice ? $device->tcDevice->lastupdate : null;

        // $lastIgnitionOn = \App\Models\TcEvent::where('deviceid', $device->device_id)
        //     ->where('type', 'ignitionOn')
        //     ->orderBy('eventtime', 'desc')
        //     ->first();

        // $lastIgnitionOff = \App\Models\TcEvent::where('deviceid', $device->device_id)
        //     ->where('type', 'ignitionOff')
        //     ->orderBy('eventtime', 'desc')
        //     ->first();

        // Get maintenance count
        $maintenanceCount = \App\Models\TcEvent::where('deviceid', $device->device_id)
            ->where('type', 'maintenance')
            ->where('is_read', 0)
            ->withEnabledNotifications()
            ->count();

        // Get alert count (non-maintenance)
        $alertCount = \App\Models\TcEvent::where('deviceid', $device->device_id)
            ->where('type', '!=', 'maintenance')
            ->where('is_read', 0)
            ->withEnabledNotifications()
            ->count();

        $data = $device->toArray();
        // Use lastupdate for both as requested/implied fallback
        $data['last_ignition_on'] = $lastUpdate ?? 'N/A';
        $data['last_ignition_off'] = $lastUpdate ?? 'N/A';
        $data['maintenance_count'] = $maintenanceCount;
        $data['alert_count'] = $alertCount;

        // Parse attributes to find vehicle ID if present
        $vehicleIdAttr = null;
        if ($device->tcDevice && $device->tcDevice->attributes) {
            $attrs = json_decode($device->tcDevice->attributes, true);
            // Check for various possible keys for vehicle id, including vehicleNo
            $vehicleIdAttr = $attrs['vehicleNo'] ?? $attrs['vehicle_id'] ?? $attrs['vehicleId'] ?? $attrs['vehicleID'] ?? null;
        }
        $data['vehicle_id_attr'] = $vehicleIdAttr;

        return response()->json($data);
    }

    /**
     * Get unread alert events for a specific device.
     */
    public function getDeviceEvents($id)
    {
        // Resolve device ID (Traccar ID)
        $device = Devices::where('device_id', $id)->orWhere('id', $id)->firstOrFail();

        $events = \App\Models\TcEvent::where('deviceid', $device->device_id)
            ->where('type', '!=', 'maintenance')
            ->where('is_read', 0)
            ->withEnabledNotifications()
            ->orderBy('eventtime', 'desc')
            ->get();

        return response()->json($events);
    }

    /**
     * Acknowledge an event by adding remarks and marking as read.
     */
    public function acknowledgeEvent(Request $request, $eventId)
    {
        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $event = \App\Models\TcEvent::findOrFail($eventId);

        // Update attributes with remarks
        $attributes = $event->attributes ?? [];
        // Ensure attributes is an array (it should be cast, but safety check)
        if (!is_array($attributes)) {
            $attributes = json_decode($attributes, true) ?? [];
        }

        $attributes['remarks'] = $request->input('remarks');
        $attributes['acknowledged_at'] = now()->toIso8601String();
        $attributes['acknowledged_by'] = $request->user()->name;

        // Save updates
        // We also mark as read to clear it from the count, assuming acknowledgement implies handling.
        $event->attributes = $attributes;
        $event->is_read = true;
        $event->save();

        return response()->json(['message' => 'Alert acknowledged successfully', 'event' => $event]);
    }

    public function updateAlertStatus(Request $request, $id)
    {
        $data = $request->validate([
            'alert_status' => 'nullable|string|in:enabled,disabled',
            'maintenance_status' => 'nullable|string|in:enabled,disabled',
            'status' => 'nullable|string|in:enabled,disabled',
        ]);

        $device = Devices::with(['tcDevice'])->where('device_id', $id)->orWhere('id', $id)->firstOrFail();
        $tc = $device->tcDevice;
        if (!$tc) {
            return response()->json(['message' => 'Tracker device not found'], 404);
        }

        $svc = app(DeviceService::class);
        $raw = $svc->getDeviceRaw($request->user(), (int) $device->device_id);
        if (!$raw) {
            return response()->json(['message' => 'Tracking server device not found'], 404);
        }
        $rawAttrs = [];
        if (isset($raw['attributes'])) {
            $rawAttrs = is_array($raw['attributes']) ? $raw['attributes'] : (json_decode($raw['attributes'], true) ?? []);
        }
        $alert = $data['alert_status'] ?? $data['status'] ?? null;
        $maint = $data['maintenance_status'] ?? null;
        if ($alert) {
            $rawAttrs['alert_status'] = $alert;
        }
        if ($maint) {
            $rawAttrs['maintenance_status'] = $maint;
        }
        $payload = [
            'deviceInfo' => [
                'id' => $raw['id'] ?? (int) $device->device_id,
                'name' => $raw['name'] ?? null,
                'uniqueId' => $raw['uniqueId'] ?? null,
                'phone' => $raw['phone'] ?? null,
                'model' => $raw['model'] ?? null,
                'category' => $raw['category'] ?? null,
                'groupId' => $raw['groupId'] ?? null,
                'calendarId' => $raw['calendarId'] ?? null,
                'contact' => $raw['contact'] ?? null,
                'disabled' => $raw['disabled'] ?? false,
                'expirationTime' => $raw['expirationTime'] ?? null,
                'attributes' => $rawAttrs,
            ],
        ];
        $updateReq = new Request($payload);
        $updateReq->setUserResolver(fn () => $request->user());
        $resp = $svc->deviceUpdate($updateReq);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            return response()->json(['message' => 'Failed to update device on tracking server', 'code' => $resp->responseCode ?? 0, 'error' => $resp->error ?? null], 502);
        }

        if ($alert === 'disabled') {
            \App\Models\TcEvent::where('deviceid', $device->device_id)
                ->where('type', '!=', 'maintenance')
                ->where('is_read', 0)
                ->update(['is_read' => 1]);
        }
        if ($maint === 'disabled') {
            \App\Models\TcEvent::where('deviceid', $device->device_id)
                ->where('type', 'maintenance')
                ->where('is_read', 0)
                ->update(['is_read' => 1]);
        }

        return response()->json(['alert_status' => $alert, 'maintenance_status' => $maint]);
    }

    /**
     * Check if a point is inside a polygon using Ray Casting algorithm.
     *
     * @param float $lat
     * @param float $lon
     * @param array $polygon Array of ['lat' => float, 'lon' => float]
     * @return bool
     */
    private function isPointInPolygon($lat, $lon, $polygon)
    {
        $inside = false;
        $count = count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lon'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lon'];

            $intersect = (($xi > $lat) != ($xj > $lat))
                && ($lon < ($yj - $yi) * ($lat - $xi) / ($xj - $xi) + $yi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }
}
