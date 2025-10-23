<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    /**
     * List vehicles with tracking server join/eager load, role-aware.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        // Eager load tcDevice and its current position
        $query = Devices::query()->with(['tcDevice.position']);

        // Optional: scope strictly to current user's assignment when requested
        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            // Role-based scoping
            if ($role === User::ROLE_DISTRIBUTOR) {
                $query->where('user_id', $user->id)
                      ->where('distributor_id', $user->id);
            } elseif ($role !== User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)
                ->where('user_id', $user->id);
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
                $query->where('user_id', $user->id)
                      ->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)
                      ->where('user_id', $user->id);
            }
        }

        $list = $query->get();

        $filtered = $list->filter(function ($d) {
            $tc = $d->tcDevice;
            return $tc && (int)($tc->positionid ?? 0) > 0;
        });

        $options = $filtered->map(function ($d) {
            $tc = $d->tcDevice;
            $unique = data_get($tc, 'uniqueId', data_get($tc, 'uniqueid', ''));
            $name = data_get($tc, 'name', '');
            $label = trim(($unique ? ($unique . ' - ') : '') . $name);
            if ($label === '') { $label = $name ?: (string) data_get($tc, 'id'); }
            return [
                'id' => (int) data_get($tc, 'id'),
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
            'type','manufacturer','color','registration','plate','vin','odometer','fuelAverage','maxSpeed','speedLimit','photos'
        ];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate numeric attributes
        if (array_key_exists('odometer', $attributes) && $attributes['odometer'] !== null && $attributes['odometer'] !== '') {
            $odoStr = (string) $attributes['odometer'];
            if (!preg_match('/^\d+$/', $odoStr)) {
                return response()->json(['message' => 'Odometer Reading must be numeric and >= 0'], 422);
            }
        }
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
        if (array_key_exists('vin', $attributes) && $attributes['vin'] !== null && $attributes['vin'] !== '') {
            $vinStr = (string) $attributes['vin'];
            if (!preg_match('/^\d+$/', $vinStr)) {
                return response()->json(['message' => 'VIN Number must be numeric and >= 0'], 422);
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
        if ($role === User::ROLE_ADMIN || $role === User::ROLE_DISTRIBUTOR) {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->id;
        } else {
            $userIdLocal = $user->id;
            $distributorIdLocal = $user->distributor_id ?? $user->id;
        }

        // Persist locally only after tracking server success
        $local = Devices::create([
            'device_id' => (int) $payload->id,
            'user_id' => $userIdLocal,
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
            'type','manufacturer','color','registration','plate','vin','odometer','fuelAverage','maxSpeed','speedLimit','photos'
        ];
        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        // Validate numeric attributes
        if (array_key_exists('odometer', $attributes) && $attributes['odometer'] !== null && $attributes['odometer'] !== '') {
            $odoStr = (string) $attributes['odometer'];
            if (!preg_match('/^\d+$/', $odoStr)) {
                return response()->json(['message' => 'Odometer Reading must be numeric and >= 0'], 422);
            }
        }
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
        if (array_key_exists('vin', $attributes) && $attributes['vin'] !== null && $attributes['vin'] !== '') {
            $vinStr = (string) $attributes['vin'];
            if (!preg_match('/^\d+$/', $vinStr)) {
                return response()->json(['message' => 'VIN Number must be numeric and >= 0'], 422);
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
        // Local-only soft delete; do not remove from tracking server
        $device = Devices::withTrashed()->where('device_id', $deviceId)->first();
        if (!$device) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        if (method_exists($device, 'trashed') && $device->trashed()) {
            return response()->json(['message' => 'Vehicle already soft-deleted'], 200);
        }

        try {
            $device->delete();
        } catch (\Throwable $e) {
            Log::warning('Local device soft delete failed', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to soft-delete vehicle locally',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Vehicle soft-deleted'], 200);
    }
}
