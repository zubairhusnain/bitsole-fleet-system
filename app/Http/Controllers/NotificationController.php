<?php

namespace App\Http\Controllers;

use App\Events\DeleteAlertEvent;
use App\Models\Devices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\AlertsUpdated;

class NotificationController extends Controller
{
    public function broadcast(Request $request)
    {
        $user = $request->user();
        if ($user) {
            broadcast(new AlertsUpdated($user));
        }
        return response()->json(['ok' => true]);
    }

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

             if ($role === User::ROLE_FLEET_MANAGER) {
                  $query->where('manager_id', $user->id);
             } else {
                  $query->whereHas('users', function($q) use ($user) {
                      $q->where('users.id', $user->id);
                  });
             }
        }
        $deviceIds = $query->pluck('device_id')->toArray();

        // List notifications by joining tc_notifications based on type,
        // then left joining tc_device_notification to check for assignment.
        // We include the event if it's assigned OR if the notification is marked 'always' (global).
        $events = DB::connection('pgsql')->table('tc_events as e')
            ->join('tc_notifications as n', 'e.type', '=', 'n.type')
            ->leftJoin('tc_device_notification as dn', function($join) {
                $join->on('e.deviceid', '=', 'dn.deviceid')
                     ->on('n.id', '=', 'dn.notificationid');
            })
            ->join('tc_devices as d', 'e.deviceid', '=', 'd.id')
            ->whereIn('e.deviceid', $deviceIds)
            ->where(function($query) {
                $query->whereNotNull('dn.deviceid')
                      ->orWhere('n.always', true);
            })
            // Use distinct to avoid duplicates if multiple notifications match same type
            // PostgreSQL requires ORDER BY to match DISTINCT ON columns
            ->distinct('e.id')
            ->select('e.*', 'n.attributes as notification_attributes', 'n.type as notification_type', 'd.name as device_name')
            ->orderBy('e.id', 'desc')
            ->limit(100)
            ->get();

        return response()->json($events);
    }

    public function unreadCount(Request $request)
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

             if ($role === User::ROLE_FLEET_MANAGER) {
                  $query->where('manager_id', $user->id);
             } else {
                  $query->whereHas('users', function($q) use ($user) {
                      $q->where('users.id', $user->id);
                  });
             }
        }
        $deviceIds = $query->pluck('device_id')->toArray();

        $count = DB::connection('pgsql')->table('tc_events as e')
            ->join('tc_notifications as n', 'e.type', '=', 'n.type')
            ->leftJoin('tc_device_notification as dn', function($join) {
                $join->on('e.deviceid', '=', 'dn.deviceid')
                     ->on('n.id', '=', 'dn.notificationid');
            })
            ->whereIn('e.deviceid', $deviceIds)
            ->where(function($query) {
                $query->whereNotNull('dn.deviceid')
                      ->orWhere('n.always', true);
            })
            ->where('e.is_read', 0)
            ->distinct('e.id')
            ->count('e.id');

        return response()->json(['count' => $count]);
    }

    public function markAllRead(Request $request)
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

             if ($role === User::ROLE_FLEET_MANAGER) {
                  $query->where('manager_id', $user->id);
             } else {
                  $query->whereHas('users', function($q) use ($user) {
                      $q->where('users.id', $user->id);
                  });
             }
        }
        $deviceIds = $query->pluck('device_id')->toArray();

        $eventIds = DB::connection('pgsql')->table('tc_events as e')
            ->join('tc_notifications as n', 'e.type', '=', 'n.type')
            ->leftJoin('tc_device_notification as dn', function($join) {
                $join->on('e.deviceid', '=', 'dn.deviceid')
                     ->on('n.id', '=', 'dn.notificationid');
            })
            ->whereIn('e.deviceid', $deviceIds)
            ->where(function($query) {
                $query->whereNotNull('dn.deviceid')
                      ->orWhere('n.always', true);
            })
            ->where('e.is_read', 0)
            ->distinct('e.id')
            ->pluck('e.id');

        if ($eventIds->isNotEmpty()) {
            DB::connection('pgsql')->table('tc_events')
                ->whereIn('id', $eventIds)
                ->update(['is_read' => 1]);
        }

        return response()->json(['success' => true]);
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

             if ($role === User::ROLE_FLEET_MANAGER) {
                  $query->where('manager_id', $user->id);
             } else {
                  $query->whereHas('users', function($q) use ($user) {
                      $q->where('users.id', $user->id);
                  });
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
