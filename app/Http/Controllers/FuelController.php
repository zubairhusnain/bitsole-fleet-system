<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\Devices;
use Illuminate\Http\Request;
use App\Models\User;

class FuelController extends Controller
{
    /**
     * Get list of fuel entries.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get allowed device IDs
        $devQuery = Devices::accessibleByUser($user);
        $deviceIds = $devQuery->pluck('device_id')->toArray();

        $query = FuelEntry::withTrashed()->with('device:id,name')
            ->whereIn('device_id', $deviceIds);

        // Include deleted if requested (e.g. for showing blocked items)
        if ($request->boolean('withDeleted')) {
            // Already included
        }

        // Filters
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('fill_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('fill_date', '<=', $request->end_date);
        }

        $entries = $query->orderBy('fill_date', 'desc')->paginate(20);

        return response()->json($entries);
    }

    /**
     * Store a new fuel entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:tc_devices,id',
            'fill_date' => 'required|date',
            'quantity' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'odometer' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'fuel_type' => 'nullable|string',
            'payment_type' => 'nullable|string',
        ]);

        // TODO: Add stricter permission check (can user write to this device?)
        // Permission Check:
        $user = $request->user();
        $targetDeviceId = $validated['device_id'];

        $canAccess = Devices::accessibleByUser($user)
            ->where('device_id', $targetDeviceId)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        $entry = FuelEntry::create($validated);

        return response()->json($entry, 201);
    }

    /**
     * Get a single fuel entry.
     */
    public function show(Request $request, $id)
    {
        $entry = FuelEntry::findOrFail($id);

        $canAccess = Devices::accessibleByUser($request->user())
            ->where('device_id', $entry->device_id)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        return response()->json($entry);
    }

    /**
     * Update a fuel entry.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'device_id' => 'sometimes|exists:tc_devices,id',
            'fill_date' => 'sometimes|date',
            'quantity' => 'sometimes|numeric|min:0',
            'cost' => 'sometimes|numeric|min:0',
            'odometer' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'fuel_type' => 'nullable|string',
            'payment_type' => 'nullable|string',
        ]);

        $entry = FuelEntry::findOrFail($id);

        // Permission Check:
        $user = $request->user();
        $targetDeviceId = $validated['device_id'] ?? $entry->device_id; // Check new device if changing, or current if not

        $canAccess = Devices::accessibleByUser($user)
            ->where('device_id', $targetDeviceId)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        $entry->update($validated);

        return response()->json($entry);
    }

    /**
     * Delete a fuel entry.
     */
    public function destroy(Request $request, $id)
    {
        // Find entry first to check permission
        $entry = FuelEntry::withTrashed()->findOrFail($id);

        $canAccess = Devices::accessibleByUser($request->user())
            ->where('device_id', $entry->device_id)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        // Handle force delete
        if ($request->boolean('force')) {
            $entry->forceDelete();
            return response()->json(['message' => 'Permanently deleted']);
        }

        $entry->delete();
        return response()->json(['message' => 'Blocked successfully']);
    }

    /**
     * Restore a deleted (blocked) fuel entry.
     */
    public function restore(Request $request, $id)
    {
        $entry = FuelEntry::withTrashed()->findOrFail($id);

        $canAccess = Devices::accessibleByUser($request->user())
            ->where('device_id', $entry->device_id)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        $entry->restore();
        return response()->json(['message' => 'Restored successfully']);
    }

    /**
     * Get summary stats.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $devQuery = Devices::accessibleByUser($user);
        $deviceIds = $devQuery->pluck('device_id')->toArray();

        $query = FuelEntry::whereIn('device_id', $deviceIds);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('fill_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('fill_date', '<=', $request->end_date);
        }

        $totalCost = $query->sum('cost');
        $totalQuantity = $query->sum('quantity');

        return response()->json([
            'total_cost' => $totalCost,
            'total_quantity' => $totalQuantity,
        ]);
    }

    /**
     * Get list of vehicles for dropdown options.
     */
    public function vehicleOptions(Request $request)
    {
        $user = $request->user();

        // Get devices assigned to the user
        $devices = Devices::accessibleByUser($user)
            ->with('tcDevice')
            ->get();

        $options = $devices->map(function ($d) {
            $tc = $d->tcDevice;
            // Handle case where tcDevice might be null (though ideally it shouldn't be)
            if (!$tc) {
                 return null;
            }

            $unique = $tc->uniqueid ?? $tc->uniqueId;
            $name = $tc->name;
            $label = $unique ? "$unique - $name" : $name;

            // Manually decode attributes for frontend if it's a string
            if (isset($tc->attributes) && is_string($tc->attributes)) {
                $tc->attributes = json_decode($tc->attributes, true) ?? [];
            }

            return [
                'id' => $tc->id,
                'label' => $label,
                'name' => $name,
                'uniqueid' => $unique,
                'tc_device' => $tc
            ];
        })->filter()->values();

        return response()->json($options);
    }
}
