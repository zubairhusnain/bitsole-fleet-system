<?php

namespace App\Http\Controllers;

use App\Events\DeleteAlertEvent;
use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function events(Request $request)
    {
        $user = $request->user();

        // Scope devices for this user (mirror VehicleController role logic)
        $query = Devices::query();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        if ($role === User::ROLE_DISTRIBUTOR) {
             $query->where('distributor_id', $user->id);
        } elseif ($role !== User::ROLE_ADMIN) {
             $distId = $user->distributor_id ?? $user->id;
             $query->where('distributor_id', $distId);

             if ($role === User::ROLE_USER && $user->manager_id) {
                  $query->where('user_id', $user->manager_id);
             } else {
                  $query->where('user_id', $user->id);
             }
        }
        $deviceIds = $query->pluck('device_id')->toArray();

        // List notifications by joining tc_device_notifications with tc_notifications first,
        // then joining tc_events based on deviceid and type from the first join.
        // Also join tc_devices to get the device name.
        $events = DB::connection('pgsql')->table('tc_device_notification as dn')
            ->join('tc_notifications as n', 'dn.notificationid', '=', 'n.id')
            ->join('tc_events as e', function($join) {
                $join->on('e.deviceid', '=', 'dn.deviceid')
                     ->on('e.type', '=', 'n.type');
            })
            ->join('tc_devices as d', 'e.deviceid', '=', 'd.id')
            ->whereIn('e.deviceid', $deviceIds)
            ->select('e.*', 'n.attributes as notification_attributes', 'n.type as notification_type', 'd.name as device_name')
            ->orderBy('e.eventtime', 'desc')
            ->limit(100)
            ->get();

        return response()->json($events);
    }

    public function myDeviceIds(Request $request)
    {
        $user = $request->user();

        $query = Devices::query();
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        if ($role === User::ROLE_DISTRIBUTOR) {
             $query->where('distributor_id', $user->id);
        } elseif ($role !== User::ROLE_ADMIN) {
             $distId = $user->distributor_id ?? $user->id;
             $query->where('distributor_id', $distId);

             if ($role === User::ROLE_USER && $user->manager_id) {
                  $query->where('user_id', $user->manager_id);
             } else {
                  $query->where('user_id', $user->id);
             }
        }
        $deviceIds = $query->pluck('device_id')->toArray();

        return response()->json($deviceIds);
    }

    public function destroy($id)
    {
        try {
            $deleted = DB::connection('pgsql')->table('tc_events')->where('id', $id)->delete();

            if ($deleted) {
                broadcast(new DeleteAlertEvent($id));
                return response()->json(['message' => 'Event deleted successfully']);
            }

            return response()->json(['message' => 'Event not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting event: ' . $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $payload = $this->notificationService->allnotification($request);
        return response()->json($payload);
    }

    public function device(Request $request, int $deviceId)
    {
        $req = new Request(array_merge($request->all(), ['device_detail_id' => $deviceId]));
        $payload = $this->notificationService->deviceNotification($req);
        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $errors = $this->notificationService->addNotification($request);
        $ok = empty($errors);
        return response()->json(['ok' => $ok, 'errors' => $errors]);
    }

    public function assign(Request $request)
    {
        $deviceId = (int) $request->input('deviceId');
        $notificationId = (int) $request->input('notificationId');
        if (!$deviceId || !$notificationId) {
            return response()->json(['message' => 'deviceId and notificationId are required'], 422);
        }
        $resp = $this->permissionService->assignNotification($request, $deviceId, $notificationId);
        return response()->json(['response' => $resp]);
    }
}

