<?php

namespace App\Http\Controllers;

use App\Events\DeleteAlertEvent;
use App\Models\Devices;
use App\Models\User;
use App\Support\AlertTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $deviceIds = Devices::accessibleByUser($user)
            ->pluck('device_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($deviceIds)) {
            $perPage = max(1, min(500, (int) $request->input('per_page', 25)));

            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
            ]);
        }

        $eventsQuery = \App\Models\TcEvent::query()
            ->select([
                'tc_events.id',
                'tc_events.type',
                'tc_events.eventtime',
                'tc_events.deviceid',
                'tc_events.positionid',
                'tc_events.attributes',
                'tc_events.is_read',
            ])
            ->with(['device:id,name', 'position:id,latitude,longitude,address'])
            ->whereIn('deviceid', $deviceIds)
            ->withEnabledNotifications();

        $deviceId = (int) $request->input('device_id', 0);
        if ($deviceId > 0 && in_array($deviceId, $deviceIds, true)) {
            $eventsQuery->where('deviceid', $deviceId);
        }

        $types = $this->resolveEventTypeFilters($request);
        if ($types !== []) {
            $eventsQuery->where(function ($q) use ($types) {
                foreach ($types as $type) {
                    $q->orWhere(function ($inner) use ($type) {
                        $inner->where('type', $type);
                        if ($type !== 'alarm') {
                            $inner->orWhere(function ($alarm) use ($type) {
                                $alarm->where('type', 'alarm')
                                    ->whereRaw("CAST(attributes AS json)->>'alarm' = ?", [$type]);
                            });
                        }
                    });
                }
            });
        }
 
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        if ($dateFrom !== '' || $dateTo !== '') {
            try {
                $from = $dateFrom !== '' ? $this->parseEventFilterDate($dateFrom, true) : null;
                $to = $dateTo !== '' ? $this->parseEventFilterDate($dateTo, false) : null;
                if ($from && $to) {
                    $eventsQuery->whereBetween('eventtime', [$from, $to]);
                } elseif ($from) {
                    $eventsQuery->where('eventtime', '>=', $from);
                } elseif ($to) {
                    $eventsQuery->where('eventtime', '<=', $to);
                }
            } catch (\Throwable $e) {
            }
        }

        $perPage = (int) $request->input('per_page', (int) $request->input('limit', 25));
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 500) {
            $perPage = 500;
        }

        $page = max(1, (int) $request->input('page', 1));

        $paginator = $eventsQuery
            ->orderByDesc('tc_events.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->through(fn ($event) => [
            'id' => $event->id,
            'type' => $event->type,
            'eventtime' => $event->eventtime,
            'deviceid' => $event->deviceid,
            'positionid' => $event->positionid,
            'attributes' => $event->attributes,
            'is_read' => $event->is_read,
            'device_name' => $event->device->name ?? null,
            'notification_type' => $event->type,
            'notification_attributes' => null,
            'latitude' => $event->position->latitude ?? null,
            'longitude' => $event->position->longitude ?? null,
            'address' => $event->position->address ?? null,
        ]);

        return response()->json($paginator);
    }

    public function deviceOptions(Request $request)
    {
        $user = $request->user();

        $devices = Devices::accessibleByUser($user)
            ->with(['tcDevice'])
            ->get()
            ->map(function ($d) {
                $tc = $d->tcDevice;
                $name = data_get($tc, 'name', data_get($d, 'name', null));
                return [
                    'id' => (int) $d->device_id,
                    'name' => $name ?: ('Vehicle #' . (int) $d->device_id),
                ];
            })
            ->sortBy(function ($d) {
                return mb_strtolower((string) ($d['name'] ?? ''));
            })
            ->values();

        return response()->json($devices);
    }

    public function typeOptions(Request $request)
    {
        $user = $request->user();
        $deviceIds = Devices::accessibleByUser($user)->pluck('device_id')->toArray();

        return response()->json([
            'types' => AlertTypes::forUserDevices($deviceIds, $user?->id),
            'labels' => AlertTypes::labels(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

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

        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        return response()->json($deviceIds);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

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

    private function parseEventFilterDate(string $value, bool $isStart): Carbon
    {
        $tz = config('app.timezone', 'UTC');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $parsed = Carbon::parse($value, $tz);

            return ($isStart ? $parsed->copy()->startOfDay() : $parsed->copy()->endOfDay())->utc();
        }

        return Carbon::parse($value, $tz)->utc();
    }

    /** @return list<string> */
    private function resolveEventTypeFilters(Request $request): array
    {
        $raw = $request->input('types', $request->input('type'));

        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        $types = [];
        foreach ($values as $value) {
            $type = trim((string) $value);
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }
}
