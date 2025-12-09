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
        $deviceIds = Devices::where('user_id', $user->id)->pluck('device_id')->toArray();

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

        $entry = FuelEntry::create($validated);

        return response()->json($entry, 201);
    }

    /**
     * Get a single fuel entry.
     */
    public function show($id)
    {
        $entry = FuelEntry::findOrFail($id);
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
        $entry->update($validated);

        return response()->json($entry);
    }

    /**
     * Delete a fuel entry.
     */
    public function destroy(Request $request, $id)
    {
        // Handle force delete
        if ($request->boolean('force')) {
            $entry = FuelEntry::withTrashed()->findOrFail($id);
            $entry->forceDelete();
            return response()->json(['message' => 'Permanently deleted']);
        }

        $entry = FuelEntry::findOrFail($id);
        // TODO: Add stricter permission check
        $entry->delete();
        return response()->json(['message' => 'Blocked successfully']);
    }

    /**
     * Restore a deleted (blocked) fuel entry.
     */
    public function restore($id)
    {
        $entry = FuelEntry::withTrashed()->findOrFail($id);
        $entry->restore();
        return response()->json(['message' => 'Restored successfully']);
    }

    /**
     * Get summary stats.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $deviceIds = Devices::where('user_id', $user->id)->pluck('device_id')->toArray();

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
        $devices = Devices::with('tcDevice')
            ->where('user_id', $user->id)
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
