<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
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

            foreach ($events as $event) {
                $dId = $event->deviceid;
                if ($event->type === 'maintenance') {
                    if (!isset($maintenanceCounts[$dId])) $maintenanceCounts[$dId] = 0;
                    $maintenanceCounts[$dId]++;
                } else {
                    if (!isset($alertCounts[$dId])) $alertCounts[$dId] = 0;
                    $alertCounts[$dId]++;
                }
            }

            $devices->getCollection()->transform(function ($device) use ($alertCounts, $maintenanceCounts) {
                $dId = $device->device_id;
                $device->alert_count = $alertCounts[$dId] ?? 0;
                $mCount = $maintenanceCounts[$dId] ?? 0;
                $device->maintenance_count = $mCount;

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
}
