<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\TcDriver;

class DriverController extends Controller
{
    /**
     * List drivers from tracking server via DriverService.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        $query = \App\Models\Drivers::withTrashed()->with(['tcDriver', 'tcDevice']);

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId);
                if ($role === \App\Models\User::ROLE_FLEET_MANAGER) {
                    $managedIds = \App\Models\User::query()
                        ->where('manager_id', $user->id)
                        ->pluck('id')
                        ->all();
                    $query->whereIn('user_id', array_merge([$user->id], $managedIds));
                } else {
                    $query->where('user_id', $user->id);
                }
            }
        }

        $rows = $query->orderByDesc('id')->get();

        $drivers = $rows->map(function ($row) {
            $tc = $row->tcDriver;
            $attrs = [];
            if ($tc && isset($tc->attributes)) {
                $attrs = is_array($tc->attributes)
                    ? $tc->attributes
                    : (json_decode($tc->attributes, true) ?? []);
            }
            $licenseImagePath = $attrs['licenseImage'] ?? null;
            $avatarImagePath = $attrs['avatarImage'] ?? null;
            $licenseImageUrl = $licenseImagePath ? Storage::url($licenseImagePath) : null;
            $avatarImageUrl = $avatarImagePath ? Storage::url($avatarImagePath) : null;
            $device = $row->tcDevice;
            $deviceName = $device->name ?? null;
            $deviceUniqueId = $device->uniqueId ?? ($device->uniqueid ?? null);
            return [
                'id' => $tc->id ?? null,
                'localId' => $row->id,
                'uniqueId' => $tc->uniqueId ?? ($tc->uniqueid ?? null),
                'name' => $tc->name ?? null,
                'email' => $tc->email ?? null,
                'phone' => $tc->phone ?? null,
                'attributes' => $attrs,
                'deviceId' => $row->device_id ?? null,
                'deviceName' => $deviceName,
                'deviceUniqueId' => $deviceUniqueId,
            'deviceStatus' => $device->status ?? null,
                'licenseImage' => $licenseImagePath,
                'avatarImage' => $avatarImagePath,
                'licenseImageUrl' => $licenseImageUrl,
                'avatarImageUrl' => $avatarImageUrl,
                'isClientDriver' => (bool) $row->is_client_driver,
                'deletedAt' => $row->deleted_at ? $row->deleted_at->format('c') : null,
                'blocked' => method_exists($row, 'trashed') ? $row->trashed() : false,
            ];
        })->values();

        return response()->json(['drivers' => $drivers]);
    }

    /**
     * Show a single driver by tracking server driver ID.
     */
    public function show(Request $request, int $driverId)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        $query = \App\Models\Drivers::query()->with(['tcDriver', 'tcDevice'])->where('driver_id', $driverId);

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
                $query->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId);
                if ($role === \App\Models\User::ROLE_FLEET_MANAGER) {
                    $managedIds = \App\Models\User::query()
                        ->where('manager_id', $user->id)
                        ->pluck('id')
                        ->all();
                    $query->whereIn('user_id', array_merge([$user->id], $managedIds));
                } else {
                    $query->where('user_id', $user->id);
                }
            }
        }

        $row = $query->firstOrFail();
        $tc = $row->tcDriver;

        $attrs = [];
        if ($tc && isset($tc->attributes)) {
            $attrs = is_array($tc->attributes)
                ? $tc->attributes
                : (json_decode($tc->attributes, true) ?? []);
        }

        $licenseImagePath = $attrs['licenseImage'] ?? null;
        $avatarImagePath = $attrs['avatarImage'] ?? null;
        $licenseImageUrl = $licenseImagePath ? Storage::url($licenseImagePath) : null;
        $avatarImageUrl = $avatarImagePath ? Storage::url($avatarImagePath) : null;
        return response()->json([
            'id' => $tc->id ?? null,
            'uniqueId' => $tc->uniqueId ?? ($tc->uniqueid ?? null),
            'name' => $tc->name ?? null,
            'attributes' => $attrs,
            'deviceId' => $row->device_id ?? null,
            'licenseImage' => $licenseImagePath,
            'avatarImage' => $avatarImagePath,
            'licenseImageUrl' => $licenseImageUrl,
            'avatarImageUrl' => $avatarImageUrl,
            'isClientDriver' => (bool) $row->is_client_driver,
        ]);
    }

    /**
     * Create a new driver on the tracking server.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'uniqueId' => ['required', 'string', Rule::unique(TcDriver::class, 'uniqueid')],
            'attributes' => ['nullable'],
            'licenceImage' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:4096'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:4096'],
        ]);

        // Normalize attributes to array
        $attributes = $request->input('attributes', []);
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            $attributes = is_array($decoded) ? $decoded : [];
        }
        // Sanitize attributes: whitelist keys used by frontend forms
        $allowedKeys = ['driverId','email','phone','gender','dob','idCard','passport','healthOk','address','telephone','licence','license','licenseExpiry','assignedVehicle','licenseImage','avatarImage','status','lastRide'];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate DOB: must be before today
        if (array_key_exists('dob', $attributes) && $attributes['dob']) {
            try { $dobDate = new \DateTime($attributes['dob']); } catch (\Throwable $e) { $dobDate = null; }
            $today = new \DateTime('today');
            if (!$dobDate || $dobDate >= $today) {
                return response()->json(['message' => 'Date of Birth must be before today'], 422);
            }
        }

        // Validate License Expiry: must be after today
        if (array_key_exists('licenseExpiry', $attributes) && $attributes['licenseExpiry']) {
            try { $expDate = new \DateTime($attributes['licenseExpiry']); } catch (\Throwable $e) { $expDate = null; }
            $today = new \DateTime('today');
            if (!$expDate || $expDate <= $today) {
                return response()->json(['message' => 'License Expiry must be after today'], 422);
            }
        }

        // Validate License Expiry: must be after today
        if (array_key_exists('licenseExpiry', $attributes) && $attributes['licenseExpiry']) {
            try { $expDate = new \DateTime($attributes['licenseExpiry']); } catch (\Throwable $e) { $expDate = null; }
            $today = new \DateTime('today');
            if (!$expDate || $expDate <= $today) {
                return response()->json(['message' => 'License Expiry must be after today'], 422);
            }
        }

        // Validate Email format if provided
        if (array_key_exists('email', $attributes) && $attributes['email']) {
            if (!filter_var($attributes['email'], FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'Email Address must be a valid email'], 422);
            }
        }
        // Validate Phone numeric and non-negative if provided
        if (array_key_exists('phone', $attributes) && $attributes['phone'] !== null && $attributes['phone'] !== '') {
            $phoneStr = (string) $attributes['phone'];
            if (!preg_match('/^\d+$/', $phoneStr)) {
                return response()->json(['message' => 'Phone Number must be numeric and >= 0'], 422);
            }
        }
        // Validate Telephone numeric and non-negative if provided
        if (array_key_exists('telephone', $attributes) && $attributes['telephone'] !== null && $attributes['telephone'] !== '') {
            $telStr = (string) $attributes['telephone'];
            if (!preg_match('/^\d+$/', $telStr)) {
                return response()->json(['message' => 'Telephone must be numeric and >= 0'], 422);
            }
        }
        // Validate ID Card numeric and non-negative if provided
        if (array_key_exists('idCard', $attributes) && $attributes['idCard'] !== null && $attributes['idCard'] !== '') {
            $idStr = (string) $attributes['idCard'];
            if (!preg_match('/^\d+$/', $idStr)) {
                return response()->json(['message' => 'ID Card Number must be numeric and >= 0'], 422);
            }
        }
        // Validate Passport numeric and non-negative if provided
        if (array_key_exists('passport', $attributes) && $attributes['passport'] !== null && $attributes['passport'] !== '') {
            $passStr = (string) $attributes['passport'];
            if (!preg_match('/^\d+$/', $passStr)) {
                return response()->json(['message' => 'Passport Number must be numeric and >= 0'], 422);
            }
        }
        // Validate License Number numeric and non-negative if provided
        if (array_key_exists('licence', $attributes) && $attributes['licence'] !== null && $attributes['licence'] !== '') {
            $licStr = (string) $attributes['licence'];
            if (!preg_match('/^\d+$/', $licStr)) {
                return response()->json(['message' => 'License Number must be numeric and >= 0'], 422);
            }
        }

        // Save uploaded licence image and add to attributes
        $savedImagePath = null;
        if ($request->hasFile('licenceImage')) {
            try {
                $savedImagePath = $request->file('licenceImage')->store('driver-licence-images', 'public');
            } catch (\Throwable $e) {
                Log::warning('Failed to store driver licence image', ['error' => $e->getMessage()]);
            }
            if ($savedImagePath) {
                $attributes['licenseImage'] = $savedImagePath;
                $request->merge(['attributes' => $attributes]);
            }
        }
        // Save uploaded avatar image and add to attributes
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            try {
                $avatarPath = $request->file('avatar')->store('driver-avatars', 'public');
            } catch (\Throwable $e) {
                Log::warning('Failed to store driver avatar image', ['error' => $e->getMessage()]);
            }
            if ($avatarPath) {
                $attributes['avatarImage'] = $avatarPath;
                $request->merge(['attributes' => $attributes]);
            }
        }

        $resp = \App\Services\DriverService::driverAdd($request);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            // rollback uploaded licence image when driver creation fails
            if ($savedImagePath) {
                try { Storage::disk('public')->delete($savedImagePath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback licence image', ['path' => $savedImagePath, 'error' => $e->getMessage()]);
                }
            }
            // rollback uploaded avatar image on failure
            if ($avatarPath) {
                try { Storage::disk('public')->delete($avatarPath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback avatar image', ['path' => $avatarPath, 'error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'message' => 'Failed to create driver on tracking server',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        $payload = json_decode($resp->response, false);
        if (!$payload || !isset($payload->id)) {
            // rollback uploaded licence image when payload is invalid (align with VehicleController)
            if ($savedImagePath) {
                try { Storage::disk('public')->delete($savedImagePath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback licence image after invalid payload', ['path' => $savedImagePath, 'error' => $e->getMessage()]);
                }
            }
            // rollback uploaded avatar image when payload invalid
            if ($avatarPath) {
                try { Storage::disk('public')->delete($avatarPath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback avatar image after invalid payload', ['path' => $avatarPath, 'error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'message' => 'Tracking server driver create returned unexpected payload',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        // Persist locally: driver_id, device_id (nullable), user_id, distributor_id
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);
        if ($role === \App\Models\User::ROLE_ADMIN || $role === \App\Models\User::ROLE_DISTRIBUTOR) {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->id;
        } else {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->distributor_id ?? $user->id;
        }

        $local = \App\Models\Drivers::create([
            'driver_id' => (int) ($payload->id ?? 0),
            'device_id' => null,
            'user_id' => $userIdLocal,
            'distributor_id' => $distributorIdLocal,
            'is_client_driver' => $request->boolean('is_client_driver'),
        ]);

        // Handle initial assignment if provided
        try {
            $assigned = $attributes['assignedVehicle'] ?? null;
            $deviceId = is_numeric($assigned) ? (int) $assigned : 0;
            $newDriverId = (int) ($payload->id ?? 0);
            if ($deviceId > 0 && $newDriverId > 0) {
                // Grant permission on tracking server
                $this->permissionService->assignDriver($request, $deviceId, $newDriverId);
                // Sync local mapping
                $local->update(['device_id' => $deviceId]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Initial driver assignment failed', [
                'driver_id' => $payload->id ?? null,
                'assignedVehicle' => $attributes['assignedVehicle'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Driver created',
            'driver' => $payload,
            'local' => $local,
        ], 201);
    }

    /**
     * Update driver details on the tracking server.
     */
    public function update(Request $request, int $driverId)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'uniqueId' => ['required', 'string', Rule::unique(TcDriver::class, 'uniqueid')->ignore($driverId)],
            'attributes' => ['nullable'],
            'licenceImage' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:4096'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpeg,png', 'max:4096'],
        ]);

        // Normalize attributes to array
        $attributes = $request->input('attributes', []);
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            $attributes = is_array($decoded) ? $decoded : [];
        }
        // Sanitize attributes: whitelist keys used by frontend forms
        $allowedKeys = ['driverId','email','phone','gender','dob','idCard','passport','healthOk','address','telephone','licence','license','licenseExpiry','assignedVehicle','licenseImage','avatarImage','status','lastRide'];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate DOB: must be before today
        if (array_key_exists('dob', $attributes) && $attributes['dob']) {
            try { $dobDate = new \DateTime($attributes['dob']); } catch (\Throwable $e) { $dobDate = null; }
            $today = new \DateTime('today');
            if (!$dobDate || $dobDate >= $today) {
                return response()->json(['message' => 'Date of Birth must be before today'], 422);
            }
        }

        // Validate License Expiry: must be after today
        if (array_key_exists('licenseExpiry', $attributes) && $attributes['licenseExpiry']) {
            try { $expDate = new \DateTime($attributes['licenseExpiry']); } catch (\Throwable $e) { $expDate = null; }
            $today = new \DateTime('today');
            if (!$expDate || $expDate <= $today) {
                return response()->json(['message' => 'License Expiry must be after today'], 422);
            }
        }

        // Validate Email format if provided
        if (array_key_exists('email', $attributes) && $attributes['email']) {
            if (!filter_var($attributes['email'], FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'Email Address must be a valid email'], 422);
            }
        }
        // Validate Phone numeric and non-negative if provided
        if (array_key_exists('phone', $attributes) && $attributes['phone'] !== null && $attributes['phone'] !== '') {
            $phoneStr = (string) $attributes['phone'];
            if (!preg_match('/^\d+$/', $phoneStr)) {
                return response()->json(['message' => 'Phone Number must be numeric and >= 0'], 422);
            }
        }
        // Validate Telephone numeric and non-negative if provided
        if (array_key_exists('telephone', $attributes) && $attributes['telephone'] !== null && $attributes['telephone'] !== '') {
            $telStr = (string) $attributes['telephone'];
            if (!preg_match('/^\d+$/', $telStr)) {
                return response()->json(['message' => 'Telephone must be numeric and >= 0'], 422);
            }
        }
        // Validate ID Card numeric and non-negative if provided
        if (array_key_exists('idCard', $attributes) && $attributes['idCard'] !== null && $attributes['idCard'] !== '') {
            $idStr = (string) $attributes['idCard'];
            if (!preg_match('/^\d+$/', $idStr)) {
                return response()->json(['message' => 'ID Card Number must be numeric and >= 0'], 422);
            }
        }
        // Validate Passport numeric and non-negative if provided
        if (array_key_exists('passport', $attributes) && $attributes['passport'] !== null && $attributes['passport'] !== '') {
            $passStr = (string) $attributes['passport'];
            if (!preg_match('/^\d+$/', $passStr)) {
                return response()->json(['message' => 'Passport Number must be numeric and >= 0'], 422);
            }
        }
        // Validate License Number numeric and non-negative if provided
        if (array_key_exists('licence', $attributes) && $attributes['licence'] !== null && $attributes['licence'] !== '') {
            $licStr = (string) $attributes['licence'];
            if (!preg_match('/^\d+$/', $licStr)) {
                return response()->json(['message' => 'License Number must be numeric and >= 0'], 422);
            }
        }

        // Load existing attributes to preserve images when no new upload
        $existingAttrs = [];
        try {
            $existing = \App\Models\Drivers::with(['tcDriver'])->where('driver_id', $driverId)->first();
            $tc = $existing ? $existing->tcDriver : null;
            if ($tc && isset($tc->attributes)) {
                $existingAttrs = is_array($tc->attributes)
                    ? $tc->attributes
                    : (json_decode($tc->attributes, true) ?? []);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load existing driver attributes for update', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
        }

        // Optionally save new licence image before updating driver
        $newImagePath = null;
        if ($request->hasFile('licenceImage')) {
            try {
                $newImagePath = $request->file('licenceImage')->store('driver-licence-images', 'public');
            } catch (\Throwable $e) {
                Log::warning('Failed to store driver licence image (update)', ['error' => $e->getMessage()]);
            }
            if ($newImagePath) {
                $attributes['licenseImage'] = $newImagePath;
            }
        }
        // Preserve existing licence image if not re-uploaded and not explicitly cleared
        if (!$newImagePath && (!array_key_exists('licenseImage', $attributes) || $attributes['licenseImage'] === null || $attributes['licenseImage'] === '')) {
            if (array_key_exists('licenseImage', $existingAttrs)) {
                $attributes['licenseImage'] = $existingAttrs['licenseImage'];
            }
        }

        // Optionally save new avatar image before updating driver
        $newAvatarPath = null;
        if ($request->hasFile('avatar')) {
            try {
                $newAvatarPath = $request->file('avatar')->store('driver-avatars', 'public');
            } catch (\Throwable $e) {
                Log::warning('Failed to store driver avatar image (update)', ['error' => $e->getMessage()]);
            }
            if ($newAvatarPath) {
                $attributes['avatarImage'] = $newAvatarPath;
            }
        }
        // Preserve existing avatar image if not re-uploaded and not explicitly cleared
        if (!$newAvatarPath && (!array_key_exists('avatarImage', $attributes) || $attributes['avatarImage'] === null || $attributes['avatarImage'] === '')) {
            if (array_key_exists('avatarImage', $existingAttrs)) {
                $attributes['avatarImage'] = $existingAttrs['avatarImage'];
            }
        }

        $request->merge([
            'id' => $driverId,
            'name' => $request->input('name'),
            'uniqueId' => $request->input('uniqueId'),
            'attributes' => $attributes,
        ]);

        $resp = $this->driverService->updateDriver($request, $driverId);
        if (!isset($resp->responseCode) || $resp->responseCode < 200 || $resp->responseCode >= 300) {
            // rollback newly uploaded image when update fails
            if ($newImagePath) {
                try { Storage::disk('public')->delete($newImagePath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback licence image (update)', ['path' => $newImagePath, 'error' => $e->getMessage()]);
                }
            }
            // rollback newly uploaded avatar on failure
            if ($newAvatarPath) {
                try { Storage::disk('public')->delete($newAvatarPath); } catch (\Throwable $e) {
                    Log::warning('Failed to rollback avatar image (update)', ['path' => $newAvatarPath, 'error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'message' => 'Failed to update driver on tracking server',
                'code' => $resp->responseCode ?? 0,
                'error' => $resp->error ?? null,
            ], 502);
        }

        $payload = json_decode($resp->response, false);

        // Update local record
        $local = \App\Models\Drivers::where('driver_id', $driverId)->first();
        if ($local) {
            $local->update([
                'is_client_driver' => $request->boolean('is_client_driver'),
            ]);
        }

        // Handle assignment changes
        try {
            // Incoming desired device assignment
            $assigned = $attributes['assignedVehicle'] ?? null;
            $newDeviceId = is_numeric($assigned) ? (int) $assigned : 0;

            // Only act when a target device is provided
            if ($newDeviceId > 0) {
                $currentDeviceId = $local ? (int) ($local->device_id ?? 0) : 0;

                // Revoke previous if different, then apply new assignment
                if ($currentDeviceId > 0 && $currentDeviceId !== $newDeviceId) {
                    $this->permissionService->unassignDriver($request, $currentDeviceId, $driverId);
                }
                $this->permissionService->assignDriver($request, $newDeviceId, $driverId);

                // Sync local mapping only (do not create new local record)
                if ($local) {
                    $local->update(['device_id' => $newDeviceId]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Driver assignment update failed', [
                'driver_id' => $driverId,
                'desiredDevice' => $attributes['assignedVehicle'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }



        return response()->json([
            'message' => 'Driver updated',
            'driver' => $payload,
        ], 200);
    }

    /**
     * Hard delete a driver: remove from tracking server and permanently delete locally.
     */
    public function destroy(Request $request, int $driverId)
    {
        // Soft delete by default (block). Use force=1 (or hard=1) to permanently delete remotely + locally.
        $force = $request->boolean('force') || $request->boolean('hard');

        if (!$force) {
            // SOFT DELETE (block): mark local record as deleted, do not delete on tracking server
            $local = \App\Models\Drivers::where('driver_id', $driverId)->first();
            if (!$local) {
                return response()->json(['message' => 'Driver not found'], 404);
            }
            if (method_exists($local, 'trashed') && $local->trashed()) {
                return response()->json(['message' => 'Driver already blocked'], 200);
            }
            try {
                $local->delete();
            } catch (\Throwable $e) {
                Log::warning('Driver soft delete failed', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Failed to block driver',
                    'error' => $e->getMessage(),
                ], 500);
            }
            return response()->json(['message' => 'Driver blocked'], 200);
        }

        // HARD DELETE: delete driver on tracking server (Traccar), permanently remove local record,
        // and clean up any locally stored images referenced in attributes

        // Load existing local attributes to clean up images later
        $licenseImagePath = null;
        $avatarImagePath = null;
        try {
            $row = \App\Models\Drivers::with(['tcDriver'])->where('driver_id', $driverId)->first();
            $tc = $row ? $row->tcDriver : null;
            if ($tc && isset($tc->attributes)) {
                $attrs = is_array($tc->attributes)
                    ? $tc->attributes
                    : (json_decode($tc->attributes, true) ?? []);
                $licenseImagePath = $attrs['licenseImage'] ?? null;
                $avatarImagePath = $attrs['avatarImage'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to read existing driver images before delete', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
        }

        // 1) Attempt to delete on tracking server
        try {
            $resp = \App\Services\DriverService::driverDelete($request, $driverId);
        } catch (\Throwable $e) {
            Log::warning('Tracking server driver delete call failed', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete driver on tracking server',
                'error' => $e->getMessage(),
            ], 502);
        }

        $code = (int) ($resp->responseCode ?? 0);
        if (!($code >= 200 && $code < 300) && $code !== 404) {
            return response()->json([
                'message' => 'Failed to delete driver on tracking server',
                'code' => $code,
            ], 502);
        }

        // 2) Clean up locally stored images (ignore external URLs)
        foreach ([$licenseImagePath, $avatarImagePath] as $p) {
            if (!$p) continue;
            try {
                $isExternal = is_string($p) && preg_match('/^https?:\/\//i', $p);
                if (!$isExternal && is_string($p) && trim($p) !== '') {
                    Storage::disk('public')->delete($p);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete driver image', ['path' => $p, 'driver_id' => $driverId, 'error' => $e->getMessage()]);
            }
        }

        // 3) Permanently delete local record if present
        $local = \App\Models\Drivers::withTrashed()->where('driver_id', $driverId)->first();
        if ($local) {
            try {
                $local->forceDelete();
            } catch (\Throwable $e) {
                Log::warning('Local driver hard delete failed', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Deleted on tracking server, but failed to delete locally',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => $code === 404
                ? 'Driver deleted locally; not found on tracking server'
                : 'Driver deleted from tracking server and locally',
        ], 200);
    }

    /**
     * Restore (activate) a soft-deleted driver.
     */
    public function restore(Request $request, int $driverId)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        $query = \App\Models\Drivers::withTrashed()->where('driver_id', $driverId);
        if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
            $query->where('distributor_id', $user->id);
        } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)->where('user_id', $user->id);
        }

        $local = $query->first();
        if (!$local) {
            return response()->json(['message' => 'Driver not found'], 404);
        }
        if (method_exists($local, 'trashed') && !$local->trashed()) {
            return response()->json(['message' => 'Driver already active'], 200);
        }

        try {
            $local->restore();
        } catch (\Throwable $e) {
            Log::warning('Driver restore failed', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to activate driver',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Driver activated'], 200);
    }

}
