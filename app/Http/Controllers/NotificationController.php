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

        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            return response()->json([]);
        }

        // Scope devices for this user
        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        // Retrieve events using Eloquent with relations and scope
        $events = \App\Models\TcEvent::with(['device', 'notifications.devices'])
            ->whereIn('deviceid', $deviceIds)
            ->withEnabledNotifications()
            ->distinct('id')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        // Transform to match expected JSON structure
        $mappedEvents = $events->map(function ($event) {
            // Find the notification definition that is assigned to this device
            // Since we used withEnabledNotifications (strict), there should be one.
            $notification = $event->notifications->first(function ($n) use ($event) {
                return $n->devices->contains('id', $event->deviceid);
            });

            return array_merge($event->toArray(), [
                'device_name' => $event->device->name ?? null,
                'notification_type' => $event->type,
                'notification_attributes' => $notification ? $notification->attributes : null,
            ]);
        });

        return response()->json($mappedEvents);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            return response()->json(['count' => 0]);
        }

        // Scope devices for this user
        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        $count = \App\Models\TcEvent::whereIn('deviceid', $deviceIds)
            ->withEnabledNotifications()
            ->where('is_read', 0)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            return response()->json(['success' => true]);
        }

        // Scope devices for this user
        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        // Use Eloquent update
        \App\Models\TcEvent::whereIn('deviceid', $deviceIds)
            ->withEnabledNotifications()
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json(['success' => true]);
    }

    public function myDeviceIds(Request $request)
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            return response()->json([]);
        }

        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        return response()->json($deviceIds);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            return response()->json(['message' => 'Notification deleted']);
        }

        // Check if event exists using Eloquent
        $event = \App\Models\TcEvent::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        // Check if user has access to the device associated with this event
        $canAccess = Devices::accessibleByUser($user)
            ->where('device_id', $event->deviceid)
            ->exists();

        if (!$canAccess) {
             return response()->json(['message' => 'Forbidden: You do not have access to this device'], 403);
        }

        try {
            $deleted = $event->delete();

            if ($deleted) {
                // broadcast(new DeleteAlertEvent($id));
                return response()->json(['message' => 'Notification deleted']);
            }

            return response()->json(['message' => 'Failed to delete notification'], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting notification', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && ($user->isAdmin() || $user->isDistributor())) {
            // Return empty structure matching what the frontend expects from allnotification
            // allnotification returns ['alarmType' => [], 'notificationType' => []]
            return response()->json(['alarmType' => [], 'notificationType' => []]);
        }

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
