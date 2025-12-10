<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Drivers;
use App\Models\TcGeofence;

class VehicleController extends Controller
{
    /**
     * List vehicles with tracking server join/eager load, role-aware.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        // Eager load tcDevice and its current position; include soft-deleted (blocked) devices
        $query = Devices::withTrashed()->with(['tcDevice.position']);

        // Optional: scope strictly to current user's assignment when requested
        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            // Role-based scoping
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId);

                if ($role === User::ROLE_FLEET_MANAGER) {
                    $query->where('manager_id', $user->id);
                } else {
                    $query->where('user_id', $user->id);
                }
            }
            // Admin sees all devices; no additional where
        }

        $devices = $query->orderByDesc('id')->paginate(25);

        return response()->json($devices);
    }

    /**
     * Provide simple options for Assigned Vehicle dropdown.
     * Filters to devices that have a current position (positionid > 0).
     */
    public function options(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        $query = \App\Models\Devices::query()->with(['tcDevice']);

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId);

                if ($role === \App\Models\User::ROLE_FLEET_MANAGER) {
                    $query->where('manager_id', $user->id);
                } else {
                    $query->where('user_id', $user->id);
                }
            }
        }

        $list = $query->get();
        // Include all devices when explicitly requested; otherwise default to devices with current position
        $includeAll = $request->boolean('includeAll') || $request->boolean('all');
        $filtered = $includeAll ? $list : $list->filter(function ($d) {
            $tc = $d->tcDevice;
            return $tc && (int)($tc->positionid ?? 0) > 0;
        });
        if (!$includeAll && ($filtered->count() === 0)) {
            $filtered = $list;
        }

        $options = $filtered->map(function ($d) {
            $tc = $d->tcDevice;
            $unique = data_get($tc, 'uniqueId', data_get($tc, 'uniqueid', data_get($d, 'uniqueid', data_get($d, 'uniqueId', ''))));
            $name = data_get($tc, 'name', data_get($d, 'name', ''));
            $idFallback = (int) data_get($d, 'device_id');
            $tcId = (int) data_get($tc, 'id');
            $labelBase = trim(($unique ? ($unique . ' - ') : '') . $name);
            $label = $labelBase !== '' ? $labelBase : ('Device #' . ($idFallback ?: $tcId));
            return [
                'id' => $tcId ?: $idFallback,
                'deviceId' => $idFallback,
                'name' => $name,
                'uniqueId' => $unique,
                'label' => $label,
            ];
        })->values();

        return response()->json(['options' => $options]);
    }

    /**
     * Create a device on the tracking server, then persist locally.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        // Minimal validation for tracking server device creation
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'uniqueId' => ['required', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:16384'],
        ]);

        // Normalize attributes payload and attach optional photo paths
        $attributes = $request->input('attributes', []);
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            $attributes = is_array($decoded) ? $decoded : [];
        }
        // Sanitize attributes: whitelist known keys only
        $allowedKeys = [
            'type','manufacturer','color','registration','plate','odometer','fuelAverage','maxSpeed','speedLimit','photos','fuelTankCapacity','trackerModel',
            'fuelType','fuel_type'
        ];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate numeric attributes
        if (array_key_exists('maxSpeed', $attributes) && $attributes['maxSpeed'] !== null && $attributes['maxSpeed'] !== '') {
            $msStr = (string) $attributes['maxSpeed'];
            if (!preg_match('/^\d+$/', $msStr)) {
                return response()->json(['message' => 'Max Speed must be numeric and >= 0'], 422);
            }
        }
        if (array_key_exists('speedLimit', $attributes) && $attributes['speedLimit'] !== null && $attributes['speedLimit'] !== '') {
            $slStr = (string) $attributes['speedLimit'];
            if (!preg_match('/^\d+$/', $slStr)) {
                return response()->json(['message' => 'Speed Limit must be numeric and >= 0'], 422);
            }
        }
        if (array_key_exists('fuelTankCapacity', $attributes) && $attributes['fuelTankCapacity'] !== null && $attributes['fuelTankCapacity'] !== '') {
            $capStr = (string) $attributes['fuelTankCapacity'];
            if (!preg_match('/^\d+(?:\.\d+)?$/', $capStr)) {
                return response()->json(['message' => 'Fuel Tank Capacity must be numeric and >= 0'], 422);
            }
        }
        if (array_key_exists('registration', $attributes) && $attributes['registration'] !== null && $attributes['registration'] !== '') {
            $regStr = (string) $attributes['registration'];
            if (!preg_match('/^\d+$/', $regStr)) {
                return response()->json(['message' => 'Registration Number must be numeric and >= 0'], 422);
            }
        }


        // Save uploaded photos and add paths to attributes.photos
        $savedImagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                try {
                    $path = $file->store('vehicle-images', 'public');
                    $savedImagePaths[] = $path;
                } catch (\Throwable $e) {
                    Log::warning('Failed to store uploaded image', ['error' => $e->getMessage()]);
                }
            }
        }
        if (!empty($savedImagePaths)) {
            $attributes['photos'] = $savedImagePaths;
        }

        // Merge back into request for DeviceService consumption
        $request->merge(['attributes' => $attributes]);

        // Call tracking server via DeviceService
        $tracking = \App\Services\DeviceService::deviceAdd($request);
        if (!isset($tracking->responseCode) || $tracking->responseCode < 200 || $tracking->responseCode >= 300) {
            // rollback uploaded photos when device creation fails
            if (!empty($savedImagePaths)) {
                try {
                    foreach ($savedImagePaths as $p) {
                        Storage::disk('public')->delete($p);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to rollback uploaded images', ['paths' => $savedImagePaths, 'error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'message' => 'Failed to create device on tracking server',
                'code' => $tracking->responseCode ?? 0,
                'error' => $tracking->error ?? null,
            ], 502);
        }

        $payload = json_decode($tracking->response, false);
        if (!$payload || !isset($payload->id)) {
            Log::warning('Tracking server device create returned unexpected payload', ['payload' => $tracking->response]);
            // rollback uploaded photos when payload is invalid
            if (!empty($savedImagePaths)) {
                try {
                    foreach ($savedImagePaths as $p) {
                        Storage::disk('public')->delete($p);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to rollback uploaded images', ['paths' => $savedImagePaths, 'error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'message' => 'Unexpected response from tracking server',
                'response' => $tracking->response,
            ], 500);
        }

        // Derive local user/distributor based on role (default to admin when null)
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        $managerIdLocal = null;
        $userIdLocal = null;
        $distributorIdLocal = $user->id;

        if ($role === User::ROLE_ADMIN) {
            $distributorIdLocal = $user->id;
            $userIdLocal = $user->id;
        } elseif ($role === User::ROLE_DISTRIBUTOR) {
            $distributorIdLocal = $user->id;
            $userIdLocal = $user->id;
        } elseif ($role === User::ROLE_FLEET_MANAGER) {
            $distributorIdLocal = $user->distributor_id ?? $user->id;
            $managerIdLocal = $user->id;
        } else {
            // Fleet Viewer
            $distributorIdLocal = $user->distributor_id ?? $user->id;
            $managerIdLocal = $user->manager_id;
            $userIdLocal = $user->id;
        }

        // Persist locally only after tracking server success
        $local = Devices::create([
            'device_id' => (int) $payload->id,
            'user_id' => $userIdLocal,
            'manager_id' => $managerIdLocal,
            'distributor_id' => $distributorIdLocal,
        ]);

        return response()->json([
            'message' => 'Vehicle created',
            'traccar' => $payload,
            'device' => $local,
        ], 201);
    }

    /**
     * Show a single local record by tracking server device ID.
     */
    public function show(Request $request, int $deviceId)
    {
        // Include tracking server device join for edit prefill
        $device = Devices::with('tcDevice')->where('device_id', $deviceId)->firstOrFail();
        return response()->json($device);
    }

    /**
     * Update device on tracking server and sync local record.
     */
    public function update(Request $request, int $deviceId)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'uniqueId' => ['required', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:16384'],
        ]);

        // Normalize attributes payload
        $attributes = $request->input('attributes', []);
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            $attributes = is_array($decoded) ? $decoded : [];
        }
        // Sanitize attributes: whitelist known keys only
        $allowedKeys = [
            'type','manufacturer','color','registration','plate','odometer','fuelAverage','maxSpeed','speedLimit','photos','fuelTankCapacity','trackerModel',
            'fuelType','fuel_type'
        ];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate numeric attributes
        if (array_key_exists('maxSpeed', $attributes) && $attributes['maxSpeed'] !== null && $attributes['maxSpeed'] !== '') {
            $msStr = (string) $attributes['maxSpeed'];
            if (!preg_match('/^\d+$/', $msStr)) {
                return response()->json(['message' => 'Max Speed must be numeric and >= 0'], 422);
            }
        }
        if (array_key_exists('speedLimit', $attributes) && $attributes['speedLimit'] !== null && $attributes['speedLimit'] !== '') {
            $slStr = (string) $attributes['speedLimit'];
            if (!preg_match('/^\d+$/', $slStr)) {
                return response()->json(['message' => 'Speed Limit must be numeric and >= 0'], 422);
            }
        }
        if (array_key_exists('registration', $attributes) && $attributes['registration'] !== null && $attributes['registration'] !== '') {
            $regStr = (string) $attributes['registration'];
            if (!preg_match('/^\d+$/', $regStr)) {
                return response()->json(['message' => 'Registration Number must be numeric and >= 0'], 422);
            }
        }


        // Load existing photos from tracking server attributes
        $existingPhotos = [];
        try {
            $row = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
            $tc = $row ? $row->tcDevice : null;
            if ($tc && isset($tc->attributes)) {
                $existingAttrs = is_array($tc->attributes)
                    ? $tc->attributes
                    : (json_decode($tc->attributes, true) ?? []);
                $existingPhotos = array_values(array_filter(is_array($existingAttrs['photos'] ?? []) ? $existingAttrs['photos'] : []));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load existing vehicle photos for update', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
        }

        // Save uploaded photos
        $savedImagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                try {
                    $path = $file->store('vehicle-images', 'public');
                    $savedImagePaths[] = $path;
                } catch (\Throwable $e) {
                    Log::warning('Failed to store uploaded image', ['error' => $e->getMessage()]);
                }
            }
        }

        // Determine final photos list (preserve, add new, or clear)
        $incomingPhotos = array_key_exists('photos', $attributes)
            ? (is_array($attributes['photos']) ? $attributes['photos'] : [])
            : null;

        if ($incomingPhotos !== null) {
            $finalPhotos = !empty($savedImagePaths)
                ? array_values(array_unique(array_merge($incomingPhotos, $savedImagePaths)))
                : $incomingPhotos;
        } else {
            $finalPhotos = !empty($savedImagePaths)
                ? array_values(array_unique(array_merge($existingPhotos, $savedImagePaths)))
                : $existingPhotos;
        }
        $attributes['photos'] = $finalPhotos;

        // Track removals to clean up storage later
        $removedPaths = array_diff($existingPhotos, $finalPhotos);

        // Merge back into request
        $request->merge([
            'id' => $deviceId,
            'name' => $request->input('name'),
            'uniqueId' => $request->input('uniqueId'),
            'model' => $request->input('model'),
            'attributes' => $attributes,
        ]);

        $tracking = app(\App\Services\DeviceService::class)->deviceUpdate($request);
        if (!isset($tracking->responseCode) || $tracking->responseCode < 200 || $tracking->responseCode >= 300) {
            return response()->json([
                'message' => 'Failed to update device on tracking server',
                'code' => $tracking->responseCode ?? 0,
                'error' => $tracking->error ?? null,
            ], 502);
        }
        $payload = json_decode($tracking->response, false);

        // Sync local record by tracking server device id
        $device = Devices::where('device_id', $deviceId)->first();
        if ($device) {
            $user = $request->user();
            $role = (int) ($user->role ?? User::ROLE_ADMIN);
            if ($role === User::ROLE_ADMIN || $role === User::ROLE_DISTRIBUTOR) {
                $userIdLocal = $user->id;
                $distributorIdLocal = $user->id;
            } else {
                $userIdLocal = $user->id;
                $distributorIdLocal = $user->distributor_id ?? $user->id;
            }

            $device->update([
                'user_id' => $userIdLocal,
                'distributor_id' => $distributorIdLocal,
            ]);
        }

        // Delete removed images from storage
        if (!empty($removedPaths)) {
            foreach ($removedPaths as $p) {
                try { Storage::disk('public')->delete($p); } catch (\Throwable $e) {
                    Log::warning('Failed to delete removed vehicle image', ['path' => $p, 'device_id' => $deviceId, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json([
            'message' => 'Vehicle updated',
            'traccar' => $payload,
            'device' => $device,
        ], 200);
    }

    public function destroy(Request $request, int $deviceId)
    {
        // Soft delete by default (block). Use force=1 (or hard=1) to permanently delete remotely + locally.
        $force = $request->boolean('force') || $request->boolean('hard');

        if (!$force) {
            // SOFT DELETE (block): mark local record as deleted, do not delete on tracking server
            $device = Devices::where('device_id', $deviceId)->first();
            if (!$device) {
                return response()->json(['message' => 'Vehicle not found'], 404);
            }
            if (method_exists($device, 'trashed') && $device->trashed()) {
                return response()->json(['message' => 'Vehicle already blocked'], 200);
            }
            try {
                $device->delete();
            } catch (\Throwable $e) {
                Log::warning('Vehicle soft delete failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to block vehicle',
                    'error' => $e->getMessage(),
                ], 500);
            }
            return response()->json(['message' => 'Vehicle blocked'], 200);
        }

        // HARD DELETE: delete device on tracking server (Traccar), permanently remove local record,
        // and clean up any locally stored vehicle photos referenced in attributes

        // Load existing photo paths from local attributes
        $existingPhotos = [];
        try {
            $row = Devices::with(['tcDevice'])->where('device_id', $deviceId)->first();
            $tc = $row ? $row->tcDevice : null;
            if ($tc && isset($tc->attributes)) {
                $attrs = is_array($tc->attributes)
                    ? $tc->attributes
                    : (json_decode($tc->attributes, true) ?? []);
                $photos = $attrs['photos'] ?? [];
                $existingPhotos = array_values(array_filter(is_array($photos) ? $photos : [$photos]));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to read existing vehicle photos before delete', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
        }

        // 1) Attempt to delete on tracking server
        try {
            // DeviceService expects device_detail_id on the request
            $request->merge(['device_detail_id' => $deviceId]);
            $tracking = \App\Services\DeviceService::deviceDelete($request);
        } catch (\Throwable $e) {
            Log::warning('Tracking server delete call failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete device on tracking server',
                'error' => $e->getMessage(),
            ], 502);
        }

        // Accept 2xx as success; treat 404 (not found) as non-fatal (already deleted remotely)
        $code = (int) ($tracking->responseCode ?? 0);
        if (!($code >= 200 && $code < 300) && $code !== 404) {
            return response()->json([
                'message' => 'Failed to delete device on tracking server',
                'code' => $code,
            ], 502);
        }

        // 2) Clean up locally stored photos (ignore external URLs)
        if (!empty($existingPhotos)) {
            foreach ($existingPhotos as $p) {
                try {
                    $isExternal = is_string($p) && preg_match('/^https?:\/\//i', $p);
                    if (!$isExternal && is_string($p) && trim($p) !== '') {
                        Storage::disk('public')->delete($p);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete vehicle photo', ['path' => $p, 'device_id' => $deviceId, 'error' => $e->getMessage()]);
                }
            }
        }

        // 3) Permanently delete local record if present
        $device = Devices::withTrashed()->where('device_id', $deviceId)->first();
        if ($device) {
            try {
                // Ensure hard delete even if previously soft-deleted
                $device->forceDelete();
            } catch (\Throwable $e) {
                Log::warning('Local device hard delete failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Deleted on tracking server, but failed to delete locally',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => $code === 404
                ? 'Vehicle deleted locally; not found on tracking server'
                : 'Vehicle deleted from tracking server and locally',
        ], 200);
    }



    /**
     * Return assigned driver details for a specific vehicle.
     */
    public function driver(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        $query = \App\Models\Drivers::query()->with(['tcDriver','tcDevice'])->where('device_id', $deviceId);
        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)->where('user_id', $user->id);
            }
        }

        $row = $query->first();
        if (!$row) {
            return response()->json(['driver' => null]);
        }

        $tc = $row->tcDriver;
        $attrs = [];
        if ($tc && isset($tc->attributes)) {
            $attrs = is_array($tc->attributes) ? $tc->attributes : (json_decode($tc->attributes, true) ?? []);
        }
        $licenseImagePath = $attrs['licenseImage'] ?? null;
        $avatarImagePath = $attrs['avatarImage'] ?? null;

        return response()->json([
            'driver' => [
                'id' => $tc->id ?? null,
                'uniqueId' => $tc->uniqueId ?? ($tc->uniqueid ?? null),
                'name' => $tc->name ?? null,
                'attributes' => $attrs,
                'deviceId' => $row->device_id ?? $deviceId,
                'licenseImageUrl' => $licenseImagePath ? \Illuminate\Support\Facades\Storage::url($licenseImagePath) : null,
                'avatarImageUrl' => $avatarImagePath ? \Illuminate\Support\Facades\Storage::url($avatarImagePath) : null,
            ],
        ]);
    }

    /**
     * Device detail payload: latest position with device and drivers.
     * Trips are fetched via the separate /trips endpoint to avoid delay.
     */
    public function detail(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        // Release session lock early to allow other concurrent requests (performance/trips)
        try { if (PHP_SESSION_ACTIVE === session_status()) { @session_write_close(); } } catch (\Throwable $e) {}
        // Simple timing instrumentation to help diagnose slow responses
        $start = microtime(true);
        $payload = app(\App\Services\DeviceService::class)->getDeviceDetailWithTrips($user, $deviceId, []);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        Log::info('Vehicle detail latency', ['deviceId' => $deviceId, 'ms' => $elapsedMs]);

        return response()->json(['detail' => $payload, 'latencyMs' => $elapsedMs]);
    }

    /**
     * Return raw device object from tracking server for this vehicle.
     */
    public function deviceRaw(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $device = app(\App\Services\DeviceService::class)->getDeviceRaw($user, $deviceId);
        return response()->json(['device' => $device]);
    }

    /**
     * Return the latest position for this vehicle using its current positionId.
     */
    public function positionCurrent(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $pos = app(\App\Services\DeviceService::class)->getCurrentPosition($user, $deviceId);
        return response()->json(['position' => $pos]);
    }

    /**
     * Return trips for a device over a time window.
     * Query params: from, to
     */
    public function trips(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        // Release session lock early to allow concurrency with other endpoints
        try { if (PHP_SESSION_ACTIVE === session_status()) { @session_write_close(); } } catch (\Throwable $e) {}
        $start = microtime(true);
        $options = [];
        $from = $request->query('from');
        $to = $request->query('to');
        if ($from) { $options['from'] = $from; }
        if ($to) { $options['to'] = $to; }
        $trips = app(\App\Services\DeviceService::class)->getTrips($user, $deviceId, $options);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        Log::info('Vehicle trips latency', ['deviceId' => $deviceId, 'ms' => $elapsedMs]);
        return response()->json(['trips' => $trips, 'latencyMs' => $elapsedMs]);
    }

    /**
     * Return all drivers assigned to this device from tracking server.
     */
    public function driversList(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $drivers = app(\App\Services\DeviceService::class)->getDriversForDevice($user, $deviceId);
        return response()->json(['drivers' => $drivers]);
    }

    /**
     * List geofences currently assigned to the device from tracking server.
     */
    public function geofences(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $geofences = $this->geofencesService->deviceGeofences($request, $deviceId);
        return response()->json(['geofences' => $geofences]);
    }

    /**
     * Assign one or more drivers to a device.
     */
    public function assignDrivers(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('driverIds', []);
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        $results = [];
        foreach ($ids as $driverId) {
            try {
                $resp = $this->permissionService->assignDriver($request, $deviceId, $driverId);
                $results[] = ['driverId' => $driverId, 'ok' => true, 'response' => $resp];
            } catch (\Throwable $e) {
                $results[] = ['driverId' => $driverId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return response()->json(['assigned' => $results]);
    }

    /**
     * Unassign one or more drivers from a device.
     */
    public function unassignDrivers(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('driverIds', []);
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        $results = [];
        foreach ($ids as $driverId) {
            try {
                $resp = $this->permissionService->unassignDriver($request, $deviceId, $driverId);
                $results[] = ['driverId' => $driverId, 'ok' => true, 'response' => $resp];
            } catch (\Throwable $e) {
                $results[] = ['driverId' => $driverId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return response()->json(['unassigned' => $results]);
    }

    /**
     * Assign one or more geofences to a device.
     */
    public function assignZones(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('geofenceIds', []);
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        $results = [];
        foreach ($ids as $geoId) {
            try {
                $resp = $this->permissionService->assignGeofence($request, $deviceId, $geoId, 'POST');
                $results[] = ['geofenceId' => $geoId, 'ok' => true, 'response' => $resp->response ?? null];
            } catch (\Throwable $e) {
                $results[] = ['geofenceId' => $geoId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return response()->json(['assigned' => $results]);
    }

    /**
     * Unassign one or more geofences from a device.
     */
    public function unassignZones(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('geofenceIds', []);
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        $results = [];
        foreach ($ids as $geoId) {
            try {
                $resp = $this->permissionService->assignGeofence($request, $deviceId, $geoId, 'DELETE');
                $results[] = ['geofenceId' => $geoId, 'ok' => true, 'response' => $resp->response ?? null];
            } catch (\Throwable $e) {
                $results[] = ['geofenceId' => $geoId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return response()->json(['unassigned' => $results]);
    }

    /**
     * List driver options (id, label) under vehicles namespace.
     */
    public function driversOptions(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);
        $query = Drivers::withTrashed()->with(['tcDriver']);
        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)->where('user_id', $user->id);
            }
        }
        $rows = $query->orderByDesc('id')->limit(500)->get();
        $options = $rows->map(function ($row) {
            $tc = $row->tcDriver;
            $id = (int) ($tc->id ?? $row->id ?? 0);
            $name = $tc->name ?? $row->name ?? ('Driver #' . $id);
            return ['id' => $id, 'label' => $name];
        })->filter(fn($o) => $o['id'] > 0)->values();
        return response()->json(['options' => $options]);
    }

    /**
     * List geofence options under vehicles namespace.
     * Role-aware: filters geofences based on local Zones ownership for non-admins.
     */
    public function geofencesOptions(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);
        $pageSize = max(1, min((int) ($request->query('pageSize', 100)), 500));

        // 1. Determine allowed geofence IDs based on role (via local Zones table)
        $allowedIds = null;
        if ($role !== User::ROLE_ADMIN) {
            $zoneQuery = \App\Models\Zones::query();
            if ($request->boolean('mine')) {
                $zoneQuery->where('user_id', $user->id);
            } else {
                if ($role === User::ROLE_DISTRIBUTOR) {
                    $zoneQuery->where('distributor_id', $user->id);
                } else {
                    $distId = $user->distributor_id ?? $user->id;
                    $zoneQuery->where('distributor_id', $distId)
                          ->where('user_id', $user->id);
                }
            }
            $allowedIds = $zoneQuery->pluck('geofence_id')->filter()->unique();
            // If user has no assigned zones, return empty immediately
            if ($allowedIds->isEmpty()) {
                return response()->json(['data' => [], 'current_page' => 1, 'total' => 0]);
            }
        }

        // 2. Query Traccar geofences
        $query = TcGeofence::query()->orderByDesc('id');
        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }
        if ($name = $request->query('name')) {
            $query->where('name', 'like', "%{$name}%");
        }

        $list = $query->paginate($pageSize);
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
     * Device-specific notifications list via NotificationService.
     */
    public function notificationsDevice(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $req = new Request(array_merge($request->all(), ['device_detail_id' => $deviceId]));
        $payload = $this->notificationService->deviceNotification($req);
        return response()->json($payload);
    }

    /**
     * Assign/unassign notification for a device (already_xist true=assign, false=unassign).
     * Supports bulk updates if 'items' array is provided.
     */
    public function notificationsAssign(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        // Handle bulk assignment
        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            $results = [];
            foreach ($items as $item) {
                $notificationId = (int) ($item['notificationId'] ?? 0);
                if (!$notificationId) continue;

                // Create a new request instance with merged data for this item
                $itemReq = $request->duplicate(array_merge($request->all(), $item));

                // assignNotification relies on $request->user()
                $itemReq->setUserResolver(fn () => $request->user());

                try {
                    $resp = $this->permissionService->assignNotification($itemReq, $deviceId, $notificationId);
                    $results[] = ['notificationId' => $notificationId, 'ok' => true, 'response' => $resp];
                } catch (\Throwable $e) {
                    $results[] = ['notificationId' => $notificationId, 'ok' => false, 'error' => $e->getMessage()];
                }
            }
            return response()->json(['results' => $results]);
        }

        // Single assignment
        $notificationId = (int) $request->input('notificationId');
        if (!$notificationId) {
            return response()->json(['message' => 'notificationId is required'], 422);
        }
        $resp = $this->permissionService->assignNotification($request, $deviceId, $notificationId);
        return response()->json(['response' => $resp]);
    }

    /**
     * Return positions for a specific vehicle over a time window.
     * Accepts query params: from, to, hours (or hour), limit.
     */
    public function positions(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        // Authorize via local Devices mapping
        $query = \App\Models\Devices::query()->with(['tcDevice'])->where('device_id', $deviceId);
        if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
            $query->where('user_id', $user->id)->where('distributor_id', $user->id);
        } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)->where('user_id', $user->id);
        }
        $mapping = $query->firstOrFail();

        // Derive time window
        $from = $request->query('from');
        $to = $request->query('to');
        $hoursRaw = $request->query('hours', $request->query('hour', 24));
        $hours = is_numeric($hoursRaw) ? max(1, (int) $hoursRaw) : 24;
        $nowUtc = \Carbon\Carbon::now('UTC');
        $toIso = $to ? \Carbon\Carbon::parse($to)->timezone('UTC')->format('Y-m-d\TH:i:s\Z') : $nowUtc->format('Y-m-d\TH:i:s\Z');
        $fromIso = $from ? \Carbon\Carbon::parse($from)->timezone('UTC')->format('Y-m-d\TH:i:s\Z') : $nowUtc->copy()->subHours($hours)->format('Y-m-d\TH:i:s\Z');

        $sessionId = $user->traccarSession ?? session('cookie');
        $resp = static::curl('/api/positions?deviceId=' . $deviceId . '&from=' . $fromIso . '&to=' . $toIso, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json']);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            return response()->json([
                'message' => 'Failed to fetch positions from tracking server',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        $list = json_decode($resp->response, true) ?? [];
        $limitRaw = $request->query('limit');
        if (is_numeric($limitRaw)) {
            $n = max(1, (int) $limitRaw);
            if (count($list) > $n) {
                $list = array_slice($list, count($list) - $n);
            }
        }

        return response()->json(['positions' => $list]);
    }

    /**
     * Fetch raw device logs from tracking server for a time window.
     * Attempts Traccar logs report API and returns raw entries for client-side decoding.
     * Query params: from, to, hours (default 24)
     */
    public function logsRaw(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        // Authorization via local Devices mapping
        $query = \App\Models\Devices::query()->with(['tcDevice'])->where('device_id', $deviceId);
        if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
            $query->where('user_id', $user->id)->where('distributor_id', $user->id);
        } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)->where('user_id', $user->id);
        }
        $mapping = $query->firstOrFail();

        // Derive time window (UTC ISO)
        $from = $request->query('from');
        $to = $request->query('to');
        $hoursRaw = $request->query('hours', 24);
        $hours = is_numeric($hoursRaw) ? max(1, (int) $hoursRaw) : 24;
        $nowUtc = \Carbon\Carbon::now('UTC');
        $toIso = $to ? \Carbon\Carbon::parse($to)->timezone('UTC')->format('Y-m-d\TH:i:s\Z') : $nowUtc->format('Y-m-d\TH:i:s\Z');
        $fromIso = $from ? \Carbon\Carbon::parse($from)->timezone('UTC')->format('Y-m-d\TH:i:s\Z') : $nowUtc->copy()->subHours($hours)->format('Y-m-d\TH:i:s\Z');

        $sessionId = $user->traccarSession ?? session('cookie');
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // Prefer POST reports API; fallback to GET; if unavailable, return clear message
        $payload = json_encode(['deviceId' => $deviceId, 'from' => $fromIso, 'to' => $toIso]);
        $resp = static::curl('/api/reports/logs', 'POST', $sessionId, $payload, $headers);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300 || !trim((string)($resp->response ?? ''))) {
            $resp = static::curl('/api/reports/logs?deviceId=' . $deviceId . '&from=' . $fromIso . '&to=' . $toIso, 'GET', $sessionId, '', $headers);
        }

        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            $code = (int) ($resp->responseCode ?? 0);
            $notAvailable = in_array($code, [404, 405, 501]);
            $msg = $notAvailable ? 'Logs API not available on this Traccar version' : 'Failed to fetch logs from tracking server';
            return response()->json(['message' => $msg, 'code' => $code, 'error' => $resp->error ?? null], $notAvailable ? 404 : 502);
        }

        $raw = json_decode($resp->response ?? '[]', true);
        $entries = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                // Normalize various possible shapes
                $entries[] = [
                    'time' => $row['time'] ?? ($row['timestamp'] ?? null),
                    'protocol' => $row['protocol'] ?? null,
                    'server' => $row['server'] ?? null,
                    'remote' => $row['remote'] ?? null,
                    'hex' => $row['hex'] ?? ($row['data'] ?? ($row['raw'] ?? null)),
                ];
            }
        }

        return response()->json(['logs' => $entries, 'from' => $fromIso, 'to' => $toIso]);
    }

    /**
     * Return rating metrics for a specific vehicle using ReportService summary.
     */
    public function rating(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        // Release session lock early to allow other concurrent requests (detail/performance)
        try { if (PHP_SESSION_ACTIVE === session_status()) { @session_write_close(); } } catch (\Throwable $e) {}
        // Default window: last 7 days, overrideable via query
        $from = $request->query('from', now()->subDays(7)->toDateTimeString());
        $to = $request->query('to', now()->toDateTimeString());

        // Prepare payload for ReportService
        $request->merge([
            'device_id' => $deviceId,
            'from_date' => $from,
            'to_date' => $to,
            // Filter event types to reduce payload size and improve latency
            'event_types' => 'harshBraking,harshAcceleration,overspeed',
        ]);

        try {
            $summaryList = $this->reportService->report_summary($request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Report summary failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
            return response()->json(['rating' => null], 200);
        }

        $first = is_iterable($summaryList) ? (collect($summaryList)->first() ?? null) : null;
        if (!$first) {
            return response()->json(['rating' => null], 200);
        }

        $avgFuel = (float) ($first['avgFuel_l_per_100km'] ?? 0);
        $avgSpeed = (float) ($first['avgSpeed_kph'] ?? 0);
        $maxSpeed = (float) ($first['maxSpeed_kph'] ?? 0);
        $engineHours = (float) ($first['engineHours'] ?? 0);
        $idleMinutes = (float) ($first['idleTime_minutes'] ?? 0);
        $harshBraking = (int) ($first['harshBraking'] ?? 0);
        $overspeed = (int) ($first['overspeedEvents'] ?? 0);
        $tripCount = (int) ($first['tripCount'] ?? 0);

        // Simple scoring: start at 100, subtract penalties capped
        $score = 100;
        $score -= min($overspeed * 2.0, 40);
        $score -= min($harshBraking * 1.5, 30);
        $score -= min($idleMinutes / 10.0, 20);
        $score = max(0, min(100, round($score, 1)));

        return response()->json([
            'rating' => [
                'avgFuel_l_per_100km' => $avgFuel,
                'avgSpeed_kph' => $avgSpeed,
                'maxSpeed_kph' => $maxSpeed,
                'engineHours' => $engineHours,
                'idleTime_minutes' => $idleMinutes,
                'harshBraking' => $harshBraking,
                'overspeedEvents' => $overspeed,
                'tripCount' => $tripCount,
                'overallScore' => $score,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Consolidated performance payload for dashboard:
     * - Summary metrics (/api/reports/summary)
     * - Severe events counts (/api/reports/events)
     * - Maintenance status derived from /api/maintenances and /api/permissions
     * - Overall rating: 100 - (harshBraking * 5)
     */
    public function performance(Request $request, int $deviceId): \Illuminate\Http\JsonResponse
    {
        // Release session lock early to allow concurrency with detail/trips
        try { if (PHP_SESSION_ACTIVE === session_status()) { @session_write_close(); } } catch (\Throwable $e) {}
        $start = microtime(true);
        // Accept window; default last 7 days
        $from = $request->query('from', now()->subDays(7)->toDateTimeString());
        $to = $request->query('to', now()->toDateTimeString());

        // Prepare payload for ReportService
        $request->merge([
            'device_id' => $deviceId,
            'from_date' => $from,
            'to_date' => $to,
        ]);

        // Summary/events/stops via ReportService
        $first = null;
        try {
            $summaryList = $this->reportService->report_summary($request);
            $first = is_iterable($summaryList) ? (collect($summaryList)->first() ?? null) : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Performance summary failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
        }

        // Fallback when no summary
        if (!$first) {
            $first = [
                'distance_km' => 0,
                'spentFuel_litres' => 0,
                'avgFuel_l_per_100km' => 0,
                'engineHours' => 0,
                'maxSpeed_kph' => 0,
                'avgSpeed_kph' => 0,
                'tripCount' => 0,
                'stopCount' => 0,
                'idleTime_minutes' => 0,
                'harshBraking' => 0,
                'harshAcceleration' => 0,
                'overspeedEvents' => 0,
            ];
        }

        // Current position attributes for maintenance math
        $user = $request->user();
        $position = null;
        try {
            $position = app(\App\Services\DeviceService::class)->getCurrentPosition($user, $deviceId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Current position fetch failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
        }
        $attrs = is_array($position) ? (is_array($position['attributes'] ?? null) ? $position['attributes'] : (json_decode($position['attributes'] ?? '[]', true) ?? [])) : [];
        $odometerKm = $this->extractOdometerKm($attrs);
        $engineHours = (float) ($first['engineHours'] ?? ($attrs['hours'] ?? 0));

        // Maintenance status from /api/maintenances + /api/permissions
        $maintenance = $this->computeMaintenanceStatus($request, $deviceId, $odometerKm, $engineHours);

        // Overall rating per requirement
        $harshBraking = (int) ($first['harshBraking'] ?? 0);
        $overallRating = max(0, min(100, 100 - ($harshBraking * 5)));

        return response()->json([
            'performance' => [
                'summary' => [
                    'distance_km' => (float) ($first['distance_km'] ?? 0),
                    'spentFuel_litres' => (float) ($first['spentFuel_litres'] ?? 0),
                    'avgFuel_l_per_100km' => (float) ($first['avgFuel_l_per_100km'] ?? 0),
                    'avgSpeed_kph' => (float) ($first['avgSpeed_kph'] ?? 0),
                    'maxSpeed_kph' => (float) ($first['maxSpeed_kph'] ?? 0),
                    'engineHours' => (float) ($first['engineHours'] ?? 0),
                    'tripCount' => (int) ($first['tripCount'] ?? 0),
                    'stopCount' => (int) ($first['stopCount'] ?? 0),
                    'idleTime_minutes' => (float) ($first['idleTime_minutes'] ?? 0),
                ],
                'events' => [
                    'harshBraking' => (int) ($first['harshBraking'] ?? 0),
                    'harshAcceleration' => (int) ($first['harshAcceleration'] ?? 0),
                    'overspeedEvents' => (int) ($first['overspeedEvents'] ?? 0),
                ],
                'maintenance' => $maintenance,
                'rating' => [
                    'overallScore' => $overallRating,
                ],
                'from' => $from,
                'to' => $to,
            ],
            'latencyMs' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    private function extractOdometerKm(array $attrs): float
    {
        $metersKeys = ['totalDistance', 'distance', 'odometer_m', 'tripDistance'];
        $kmKeys = ['odometerKm', 'mileage'];
        $numeric = function($v) { return is_numeric($v) ? (float)$v : null; };

        foreach ($kmKeys as $k) {
            if (array_key_exists($k, $attrs)) {
                $val = $numeric($attrs[$k]);
                if ($val !== null) return round($val, 2);
            }
        }
        foreach ($metersKeys as $k) {
            if (array_key_exists($k, $attrs)) {
                $val = $numeric($attrs[$k]);
                if ($val !== null) return round($val / 1000.0, 2);
            }
        }
        // Some trackers store odometer as km directly
        if (array_key_exists('odometer', $attrs) && is_numeric($attrs['odometer'])) {
            $odo = (float) $attrs['odometer'];
            // Heuristic: values > 100000 likely meters
            return round(($odo >= 100000 ? ($odo / 1000.0) : $odo), 2);
        }
        return 0.0;
    }

    private function computeMaintenanceStatus(Request $request, int $deviceId, float $odometerKm, float $engineHours): array
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // Fetch maintenances
        $maintRaw = static::curl('/api/maintenances', 'GET', $sessionId, '', $headers);
        $maint = json_decode($maintRaw->response ?? '[]', true) ?? [];
        // Fetch permissions to map maintenance -> device
        $permRaw = static::curl('/api/permissions', 'GET', $sessionId, '', $headers);
        $perms = json_decode($permRaw->response ?? '[]', true) ?? [];

        $assignedIds = collect($perms)
            ->filter(function ($p) use ($deviceId) {
                return (int) ($p['deviceId'] ?? 0) === (int) $deviceId && isset($p['maintenanceId']);
            })
            ->pluck('maintenanceId')
            ->unique()
            ->values();

        $schedules = collect($maint)
            ->filter(function ($m) use ($assignedIds, $deviceId) {
                $matchAssigned = $assignedIds->contains($m['id'] ?? null);
                $directDevice = (int)($m['deviceId'] ?? 0) === (int) $deviceId;
                return $matchAssigned || $directDevice;
            })
            ->values();

        if ($schedules->isEmpty()) {
            return [
                'statusPercent' => 100,
                'remainingKm' => null,
                'remainingHours' => null,
                'nextDueDays' => null,
                'scheduleName' => null,
                'message' => 'No maintenance schedule assigned',
            ];
        }

        // Prefer odometer-based schedule; then hours; then time
        $picked = $schedules->first(function ($m) { return strtolower($m['type'] ?? '') === 'odometer' || strtolower($m['type'] ?? '') === 'distance'; })
            ?? $schedules->first(function ($m) { return strtolower($m['type'] ?? '') === 'enginehours' || strtolower($m['type'] ?? '') === 'hours'; })
            ?? $schedules->first();

        $type = strtolower($picked['type'] ?? '');
        $periodRaw = (float) ($picked['period'] ?? 0);
        $startRaw = $picked['start'] ?? null;

        $statusPercent = 100.0;
        $remainingKm = null;
        $remainingHours = null;
        $nextDueDays = null;

        if (in_array($type, ['odometer', 'distance'])) {
            $periodKm = $periodRaw >= 1000 ? ($periodRaw / 1000.0) : $periodRaw; // meters → km heuristic
            $periodKm = max(1.0, $periodKm);
            $sinceCycle = $odometerKm > 0 ? fmod($odometerKm, $periodKm) : 0.0;
            $remainingKm = round(max(0.0, $periodKm - $sinceCycle), 1);
            $statusPercent = round(($remainingKm / $periodKm) * 100.0, 1);
        } elseif (in_array($type, ['enginehours', 'hours'])) {
            $periodHours = max(1.0, $periodRaw);
            $sinceCycle = $engineHours > 0 ? fmod($engineHours, $periodHours) : 0.0;
            $remainingHours = round(max(0.0, $periodHours - $sinceCycle), 1);
            $statusPercent = round(($remainingHours / $periodHours) * 100.0, 1);
        } else {
            // time-based (ms or seconds). Approximate in days using start.
            $now = \Carbon\Carbon::now('UTC');
            $start = $startRaw ? \Carbon\Carbon::parse($startRaw)->timezone('UTC') : $now->copy()->subDays((int) $periodRaw);
            // Guess unit: if very large, assume milliseconds
            $periodSeconds = $periodRaw >= 86400 * 365 ? ($periodRaw / 1000.0) : $periodRaw; // ms → s heuristic
            $periodDays = max(1.0, $periodSeconds / 86400.0);
            $elapsedDays = max(0.0, $now->diffInDays($start));
            $sinceCycle = fmod($elapsedDays, $periodDays);
            $nextDueDays = round(max(0.0, $periodDays - $sinceCycle), 1);
            $statusPercent = round(($nextDueDays / $periodDays) * 100.0, 1);
        }

        return [
            'statusPercent' => max(0.0, min(100.0, $statusPercent)),
            'remainingKm' => $remainingKm,
            'remainingHours' => $remainingHours,
            'nextDueDays' => $nextDueDays,
            'scheduleName' => $picked['name'] ?? null,
            'type' => $picked['type'] ?? null,
        ];
    }

    /**
     * Restore (activate) a soft-deleted vehicle.
     */
    public function restore(Request $request, int $deviceId)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        $query = \App\Models\Devices::withTrashed()->where('device_id', $deviceId);
        if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
            $query->where('user_id', $user->id)->where('distributor_id', $user->id);
        } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)->where('user_id', $user->id);
        }

        $device = $query->first();
        if (!$device) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }
        if (method_exists($device, 'trashed') && !$device->trashed()) {
            return response()->json(['message' => 'Vehicle already active'], 200);
        }

        try {
            $device->restore();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Vehicle restore failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to activate vehicle',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Vehicle activated'], 200);
    }
}
