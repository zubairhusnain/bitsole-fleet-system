<?php

namespace App\Http\Controllers;

use App\Models\DriverAssignment;
use App\Models\Drivers;
use App\Models\TcEvent;
use App\Models\TcDevice;
use App\Events\AlertsUpdated;
use App\Services\DeviceService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverAssignmentController extends Controller
{
    public function __construct(DeviceService $deviceService, PermissionService $permissionService)
    {
        $this->deviceService = $deviceService;
        $this->permissionService = $permissionService;
    }

    public function index(Request $request)
    {
        try {
            $query = DriverAssignment::with(['driver.tcDriver', 'vehicle']);

            if ($request->has('driver_id')) {
                $query->where('driver_assignments.driver_id', $request->driver_id);
            }

            if ($request->has('vehicle_id')) {
                $query->where('driver_assignments.vehicle_id', $request->vehicle_id);
            }

            if ($request->has('status')) {
                $query->where('driver_assignments.status', $request->status);
            }

            return response()->json($query->orderByDesc('driver_assignments.start_time')->get());
        } catch (\Throwable $e) {
            Log::error("DriverAssignment Index Error", ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
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

        // Sync with Server Permissions
        try {
            $driver = Drivers::find($validated['driver_id']);
            if ($driver && $driver->driver_id) {
                $this->permissionService->assignDriver($request, $validated['vehicle_id'], $driver->driver_id);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to assign Server permission", ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }

        // Notification for changing vehicle driver (Assignment created)
        // Create Server event so it shows up in Alerts
        $startTimeStr = \Carbon\Carbon::parse($validated['start_time'])->format('Y-m-d H:i');
        $endTimeStr = !empty($validated['end_time'])
            ? " to " . \Carbon\Carbon::parse($validated['end_time'])->format('Y-m-d H:i')
            : "";

        $this->createEvent($validated['vehicle_id'], 'driverChanged', [
            'message' => "Driver assigned: " . $assignment->driver->name . " (Start: {$startTimeStr}{$endTimeStr})",
            'driver_id' => $assignment->driver_id,
            'assignment_id' => $assignment->id
        ]);
        broadcast(new AlertsUpdated($request->user()));

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

        $oldVehicleId = $assignment->vehicle_id;
        $assignment->update($validated);

        // Sync Server Permissions
        try {
            $driver = $assignment->driver;
            if ($driver && $driver->driver_id) {
                // If trip ended
                if ($request->filled('end_time') || ($request->input('status') === 'completed')) {
                    $this->permissionService->unassignDriver($request, $assignment->vehicle_id, $driver->driver_id);
                }
                // If vehicle changed (and not ended)
                elseif ($request->has('vehicle_id') && $oldVehicleId != $request->vehicle_id) {
                     // Unassign old
                     $this->permissionService->unassignDriver($request, $oldVehicleId, $driver->driver_id);
                     // Assign new
                     $this->permissionService->assignDriver($request, $request->vehicle_id, $driver->driver_id);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to sync Server permission on update", ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }

        // Check if trip ended
        if ($request->filled('end_time') || ($request->input('status') === 'completed')) {
             $duration = '';
             if ($assignment->start_time && $assignment->end_time) {
                 $diff = \Carbon\Carbon::parse($assignment->start_time)->diffForHumans(\Carbon\Carbon::parse($assignment->end_time), true);
                 $duration = " Duration: {$diff}.";
             }

             $this->createEvent($assignment->vehicle_id, 'driverChanged', [
                'message' => "Trip ended for driver: " . $assignment->driver->name . "." . $duration,
                'driver_id' => $assignment->driver_id,
                'status' => 'completed'
             ]);
             broadcast(new AlertsUpdated($request->user()));
             Log::info("Trip Ended: Assignment {$id}");
        }

        return response()->json($assignment);
    }

    public function destroy(Request $request, $id)
    {
        $assignment = DriverAssignment::with('driver')->find($id);
        if ($assignment) {
             try {
                if ($assignment->driver && $assignment->driver->driver_id) {
                    $this->permissionService->unassignDriver($request, $assignment->vehicle_id, $assignment->driver->driver_id);
                }
             } catch (\Throwable $e) {
                Log::warning("Failed to unassign Server permission on destroy", ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
             }
             $assignment->delete();
        }
        return response()->json(['message' => 'Assignment deleted']);
    }

    public function history(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id'
        ]);

        $driverId = $request->driver_id;
        $assignments = DriverAssignment::with('vehicle')
            ->where('driver_id', $driverId)
            ->orderByDesc('start_time')
            ->get();

        // Format as trips to match frontend expectations
        $trips = $assignments->map(function ($a) {
            $deviceName = $a->vehicle->name ?? 'Unknown Vehicle';

            // Calculate distance/duration if available (placeholders for now as local assignment doesn't track distance)
            $duration = '';
            if ($a->start_time && $a->end_time) {
                $duration = $a->start_time->diffForHumans($a->end_time, true);
            }

            return [
                'deviceId' => $a->vehicle_id,
                'deviceName' => $deviceName,
                'startTime' => $a->start_time->toIso8601String(),
                'endTime' => $a->end_time ? $a->end_time->toIso8601String() : null,
                'startAddress' => 'Assigned', // Local history doesn't have addresses
                'endAddress' => $a->status === 'completed' ? 'Completed' : $a->status,
                'status' => $a->status,
                'distance' => 0, // Not tracked in assignment
                'averageSpeed' => 0,
                'maxSpeed' => 0,
                'duration' => $duration,
                'spentFuel' => 0,
            ];
        });

        return response()->json($trips);
    }

    protected function createEvent($deviceId, $type, $attributes = [])
    {
        try {
            $positionId = 0;
            $device = TcDevice::find($deviceId);
            if ($device && $device->positionid) {
                $positionId = $device->positionid;
            }

            TcEvent::create([
                'type' => $type,
                'eventtime' => now(),
                'deviceid' => $deviceId,
                'positionid' => $positionId,
                'geofenceid' => 0,
                'attributes' => $attributes,
                'maintenanceid' => 0,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to create event: " . $e->getMessage());
        }
    }
}
