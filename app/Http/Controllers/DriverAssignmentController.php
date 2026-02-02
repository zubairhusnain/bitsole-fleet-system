<?php

namespace App\Http\Controllers;

use App\Models\DriverAssignment;
use App\Models\Drivers;
use App\Models\TcEvent;
use App\Events\AlertsUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverAssignmentController extends Controller{
    public function index(Request $request)
    {
        $query = DriverAssignment::with(['driver.tcDriver', 'vehicle']);

        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        return response()->json($query->orderByDesc('start_time')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'vehicle_id' => 'required|exists:tc_devices,id',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
        ]);

        $status = 'scheduled';
        $now = now();
        if ($now >= \Carbon\Carbon::parse($validated['start_time'])) {
            $status = 'active';
        }

        $assignment = DriverAssignment::create([
            'driver_id' => $validated['driver_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
            'status' => $status,
        ]);

        // Notification for changing vehicle driver (Assignment created)
        // Create Traccar event so it shows up in Alerts
        $this->createEvent($validated['vehicle_id'], 'driverChanged', [
            'message' => "Driver assigned: " . $assignment->driver->name,
            'driver_id' => $assignment->driver_id,
            'assignment_id' => $assignment->id
        ]);

        Log::info("Driver Assigned: Driver {$validated['driver_id']} to Vehicle {$validated['vehicle_id']}");

        return response()->json($assignment, 201);
    }

    public function update(Request $request, $id)
    {
        $assignment = DriverAssignment::with('driver')->findOrFail($id);

        $validated = $request->validate([
            'vehicle_id' => 'exists:tc_devices,id',
            'start_time' => 'date',
            'end_time' => 'nullable|date|after:start_time',
            'status' => 'string',
        ]);

        $assignment->update($validated);

        // Check if trip ended
        if ($request->filled('end_time') || ($request->input('status') === 'completed')) {
             $this->createEvent($assignment->vehicle_id, 'driverChanged', [
                'message' => "Trip ended for driver: " . $assignment->driver->name,
                'driver_id' => $assignment->driver_id,
                'status' => 'completed'
             ]);
             broadcast(new AlertsUpdated($request->user()));
             Log::info("Trip Ended: Assignment {$id}");
        }

        return response()->json($assignment);
    }

    public function destroy($id)
    {
        DriverAssignment::destroy($id);
        return response()->json(['message' => 'Assignment deleted']);
    }

    protected function createEvent($deviceId, $type, $attributes = [])
    {
        try {
            TcEvent::create([
                'type' => $type,
                'servertime' => now(),
                'eventtime' => now(),
                'deviceid' => $deviceId,
                'positionid' => 0,
                'geofenceid' => 0,
                'attributes' => json_encode($attributes),
                'maintenanceid' => 0,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create event: " . $e->getMessage());
        }
    }
}
