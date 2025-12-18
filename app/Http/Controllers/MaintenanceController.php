<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceService;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use App\Models\Devices;
use App\Models\User;

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

        $data = $request->only(['name', 'type', 'start', 'period']);

        // Add managerId to attributes
        $user = $request->user();
        $managerId = null;
        if ($user->role === User::ROLE_USER) {
            $managerId = $user->manager_id;
        } elseif ($user->role === User::ROLE_FLEET_MANAGER) {
            $managerId = $user->id;
        }

        if ($managerId !== null) {
            $data['attributes'] = ['managerId' => $managerId];
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

        if (isset($maintenance['id'])) {
            $deviceId = $request->input('deviceId');

            if ($deviceId === 'all' || $deviceId === 0) {
                // Assign to all allowed devices
                // Use getData(true) to get array from JsonResponse
                $allowedOptions = $this->vehicleOptions($request)->getData(true);

                foreach ($allowedOptions as $device) {
                    $dId = $device['id']; // vehicleOptions returns array with 'id' key
                     $this->maintenanceService->assignToDevice($request, $maintenance['id'], $dId);
                }
            } elseif (!empty($deviceId)) {
                $this->maintenanceService->assignToDevice($request, $maintenance['id'], $deviceId);
            }
        }

        return response()->json($maintenance);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'start' => 'required|numeric',
            'period' => 'required|numeric',
            'deviceId' => 'nullable'
        ]);

        $data = $request->only(['name', 'type', 'start', 'period']);

        // Add managerId to attributes
        $user = $request->user();
        $managerId = null;
        if ($user->role === User::ROLE_USER) {
            $managerId = $user->manager_id;
        } elseif ($user->role === User::ROLE_FLEET_MANAGER) {
            $managerId = $user->id;
        }

        if ($managerId !== null) {
            $data['attributes'] = ['managerId' => $managerId];
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

        // Update assignment
        if ($request->has('deviceId')) {
            $deviceId = $request->input('deviceId');

            // 1. Get allowed devices using vehicleOptions logic
            // Use getData(true) to get array from JsonResponse
            $allowedOptions = $this->vehicleOptions($request)->getData(true);
            $allowedDeviceIds = array_column($allowedOptions, 'id');

            // 2. Get current assignments from tracking server (scoped to this maintenance)
            $currentAssignedDevices = $this->maintenanceService->getDevicesForMaintenance($request, $id);
            $currentDeviceIds = array_map(function($d) {
                return is_array($d) ? $d['id'] : $d->id;
            }, $currentAssignedDevices);

            // Filter current assignments to only those we are allowed to see/manage
            // This prevents unassigning devices that the user doesn't have access to
            $visibleCurrentDeviceIds = array_intersect($currentDeviceIds, $allowedDeviceIds);

            // 3. Determine Target Device IDs
            $targetDeviceIds = [];
            if ($deviceId === 'all' || $deviceId === 0) {
                // Target is ALL ALLOWED devices
                $targetDeviceIds = $allowedDeviceIds;
            } elseif (!empty($deviceId)) {
                // Target is specific device. Verify it is allowed.
                if (in_array($deviceId, $allowedDeviceIds)) {
                    $targetDeviceIds = [$deviceId];
                }
            }

            // 4. Calculate Diff (using only visible/allowed devices)
            $toAdd = array_diff($targetDeviceIds, $visibleCurrentDeviceIds);
            $toRemove = array_diff($visibleCurrentDeviceIds, $targetDeviceIds);

            foreach ($toAdd as $dId) {
                $this->maintenanceService->assignToDevice($request, $id, $dId);
            }
            foreach ($toRemove as $dId) {
                $this->maintenanceService->removeAssignment($request, $id, $dId);
            }
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
