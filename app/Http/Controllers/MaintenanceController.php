<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceService;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use App\Models\Devices;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    protected MaintenanceService $maintenanceService;
    protected DeviceService $deviceService;

    public function __construct(MaintenanceService $maintenanceService, DeviceService $deviceService)
    {
        $this->maintenanceService = $maintenanceService;
        $this->deviceService = $deviceService;
    }

    public function index(Request $request)
    {
        // dd($request);
        $resp = $this->maintenanceService->getAll($request);

        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
             return response()->json([
                'message' => 'Failed to fetch maintenance records',
                'error' => $resp->error ?? null,
                'details' => $resp->response ?? null
            ], $resp->responseCode ?? 500);
        }

        $maintenance = json_decode($resp->response, true) ?? [];

        // Filter based on managerId
        $user = $request->user();
        $filterManagerId = null;

        if ($user->role === User::ROLE_USER) {
            $filterManagerId = $user->manager_id;
        } elseif ($user->role === User::ROLE_FLEET_MANAGER) {
            $filterManagerId = $user->id;
        }

        if ($filterManagerId !== null) {
            $maintenance = array_filter($maintenance, function ($item) use ($filterManagerId) {
                $attributes = $item['attributes'] ?? [];
                // Check if managerId exists and matches
                return isset($attributes['managerId']) && $attributes['managerId'] == $filterManagerId;
            });
            // Re-index array to ensure JSON array, not object with missing keys
            $maintenance = array_values($maintenance);
        }

        // Fetch assigned devices for each maintenance record
        // This might be N+1 query problem if not careful, but for now we iterate.
        // Tracking API doesn't support "include=devices" in maintenance list easily.
        foreach ($maintenance as &$item) {
            // Check attributes for device_ids (Source of Truth)
            if (isset($item['attributes']['device_ids']) && is_array($item['attributes']['device_ids'])) {
                $item['deviceIds'] = $item['attributes']['device_ids'];
            } else {
                // Fallback to fetching from API (Legacy / Tracking Permissions)
                $assignedDevices = $this->maintenanceService->getDevicesForMaintenance($request, $item['id']);
                $item['deviceIds'] = $assignedDevices ? array_map(function($d) {
                    return (int) (is_array($d) ? $d['id'] : $d->id);
                }, $assignedDevices) : [];
            }
        }

        return response()->json($maintenance);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'start' => 'required|numeric',
            'period' => 'required|numeric',
            'deviceId' => 'nullable' // 0 or null for all, or specific ID
        ]);

        // 1. Calculate Target Device IDs
        $targetDeviceIds = [];
        if ($request->has('deviceId')) {
            $deviceId = $request->input('deviceId');

            // Get allowed devices
            $allowedOptions = $this->vehicleOptions($request)->getData(true);
            $allowedDeviceIds = array_map('intval', array_column($allowedOptions, 'id'));

            if ($deviceId === 'all' || $deviceId === 0 || $deviceId === '0') {
                $targetDeviceIds = $allowedDeviceIds;
            } elseif (!empty($deviceId)) {
                $dIdInt = (int)$deviceId;
                if (in_array($dIdInt, $allowedDeviceIds)) {
                    $targetDeviceIds = [$dIdInt];
                }
            }
            $targetDeviceIds = array_values($targetDeviceIds);
        }

        $data = $request->only(['name', 'type', 'start', 'period']);

        $attributes = [];

        // Add managerId to attributes
        $user = $request->user();
        $managerId = null;
        if ($user->role === User::ROLE_USER) {
            $managerId = $user->manager_id;
        } elseif ($user->role === User::ROLE_FLEET_MANAGER) {
            $managerId = $user->id;
        }

        if ($managerId !== null) {
            $attributes['managerId'] = $managerId;
        }

        // Add device_ids to attributes
        if (!empty($targetDeviceIds)) {
            $attributes['device_ids'] = $targetDeviceIds;
        }

        if (!empty($attributes)) {
            $data['attributes'] = $attributes;
        }

        // Create Maintenance
        $resp = $this->maintenanceService->create($request, $data);

        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
             return response()->json([
                'message' => 'Failed to create maintenance record',
                'error' => $resp->error ?? null,
                'details' => $resp->response ?? null
            ], $resp->responseCode ?? 500);
        }

        $maintenance = json_decode($resp->response, true);

        if (isset($maintenance['id']) && !empty($targetDeviceIds)) {
            // Sync Permissions (Legacy)
            foreach ($targetDeviceIds as $dId) {
                $this->maintenanceService->assignToDevice($request, $maintenance['id'], $dId);
            }
        }

        $maintenance['deviceIds'] = $targetDeviceIds;

        return response()->json($maintenance);
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'start' => 'required|numeric',
            'period' => 'required|numeric',
            'deviceId' => 'nullable'
        ]);

        // 1. Calculate Target Device IDs (needed for attributes)
        $targetDeviceIds = [];
        if ($request->has('deviceId')) {
            $deviceId = $request->input('deviceId');

            // Get allowed devices
            $allowedOptions = $this->vehicleOptions($request)->getData(true);
            $allowedDeviceIds = array_map('intval', array_column($allowedOptions, 'id'));

            if ($deviceId === 'all' || $deviceId === 0 || $deviceId === '0') {
                $targetDeviceIds = $allowedDeviceIds;
            } elseif (!empty($deviceId)) {
                $dIdInt = (int)$deviceId;
                if (in_array($dIdInt, $allowedDeviceIds)) {
                    $targetDeviceIds = [$dIdInt];
                }
            }
            // Ensure array is values only
            $targetDeviceIds = array_values($targetDeviceIds);
        }

        $data = $request->only(['name', 'type', 'start', 'period']);

        // Prepare Attributes
        $attributes = [];

        // Add managerId
        $user = $request->user();
        $managerId = null;
        if ($user->role === User::ROLE_USER) {
            $managerId = $user->manager_id;
        } elseif ($user->role === User::ROLE_FLEET_MANAGER) {
            $managerId = $user->id;
        }
        if ($managerId !== null) {
            $attributes['managerId'] = $managerId;
        }

        // Add device_ids if we have them
        if ($request->has('deviceId')) {
             $attributes['device_ids'] = $targetDeviceIds;
        }

        if (!empty($attributes)) {
            $data['attributes'] = $attributes;
        }

        $resp = $this->maintenanceService->update($request, $id, $data);

        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
             return response()->json([
                'message' => 'Failed to update maintenance record',
                'error' => $resp->error ?? null,
                'details' => $resp->response ?? null
            ], $resp->responseCode ?? 500);
        }

        $maintenance = json_decode($resp->response, true);

        // Update assignment (Permissions) - Legacy/Sync attempt
        // We still try to update permissions for other Traccar features, but we rely on attributes for display.
        if ($request->has('deviceId')) {

            // 2. Get current assignments from tracking server (for diffing)
            $currentAssignedDevices = $this->maintenanceService->getDevicesForMaintenance($request, $id);

            $currentDeviceIds = [];
            if ($currentAssignedDevices !== null) {
                $currentDeviceIds = array_map(function($d) {
                    return (int) (is_array($d) ? $d['id'] : $d->id);
                }, $currentAssignedDevices);
            }

            // We re-calculate allowedDeviceIds just to be safe (already done above)
            $allowedOptions = $this->vehicleOptions($request)->getData(true);
            $allowedDeviceIds = array_map('intval', array_column($allowedOptions, 'id'));

            $visibleCurrentDeviceIds = array_intersect($currentDeviceIds, $allowedDeviceIds);

            // 4. Reset Strategy: Remove all current (visible), then add all target
            $toAdd = $targetDeviceIds;
            $toRemove = $visibleCurrentDeviceIds;

            $debugLog = [];

            foreach ($toRemove as $dId) {
                $res = $this->maintenanceService->removeAssignment($request, $id, $dId);
                // Log but ignore failures since Tracking API is buggy for DELETE
            }
            foreach ($toAdd as $dId) {
                $this->maintenanceService->assignToDevice($request, $id, $dId);
            }
        }

        // Return updated object with deviceIds from our calculation (Source of Truth)
        if ($request->has('deviceId')) {
            $maintenance['deviceIds'] = $targetDeviceIds;
        }

        return response()->json($maintenance);
    }

    public function vehicleOptions(Request $request)
    {
        $user = $request->user();

        if ($request->boolean('mine')) {
            $query = \App\Models\Devices::accessibleByUser($user);
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } else {
            $query = \App\Models\Devices::accessibleByUser($user);
        }

        $query->with(['tcDevice']);

        $list = $query->get();

        $options = $list->map(function ($d) {
            $tc = $d->tcDevice;
            $unique = data_get($tc, 'uniqueId', data_get($tc, 'uniqueid', data_get($d, 'uniqueid', data_get($d, 'uniqueId', ''))));
            $name = data_get($tc, 'name', data_get($d, 'name', ''));
            $idFallback = (int) data_get($d, 'device_id');
            $tcId = (int) data_get($tc, 'id');
            $labelBase = trim(($unique ? ($unique . ' - ') : '') . $name);
            $label = $labelBase !== '' ? $labelBase : ('Device #' . ($idFallback ?: $tcId));

            // Try to get attributes from tcDevice if available, maybe maintenanceIds are there?
            // Usually they are not in the attributes column unless synced.
            // We return what we have.

            return [
                'id' => $tcId ?: $idFallback,
                'deviceId' => $idFallback,
                'name' => $name,
                'uniqueid' => $unique,
                'label' => $label,
                // 'maintenanceIds' => [] // We don't have this from local DB
            ];
        })->values();

        return response()->json($options);
    }
    public function destroy(Request $request, $id)
    {
        $resp = $this->maintenanceService->delete($request, $id);

        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
             return response()->json([
                'message' => 'Failed to delete maintenance record',
                'error' => $resp->error ?? null,
                'details' => $resp->response ?? null
            ], $resp->responseCode ?? 500);
        }

        return response()->json(['status' => 'success']);
    }
}
