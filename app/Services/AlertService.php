<?php

namespace App\Services;

use App\Models\Devices;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AlertService
{
    /**
     * Get live alerts for a user.
     *
     * @param User $user
     * @param array $options
     * @return array
     */
    public function getLiveAlerts(User $user, array $options = [])
    {
        // 1. Get allowed device IDs based on role
        $query = Devices::accessibleByUser($user);
        $deviceIds = $query->pluck('device_id')->toArray();

        if (empty($deviceIds)) {
            return [];
        }

        $query = \App\Models\TcEvent::with(['device', 'notifications.devices'])
            ->whereIn('deviceid', $deviceIds)
            ->withEnabledNotifications();

        // Filter options
        if (isset($options['unreadOnly']) && $options['unreadOnly']) {
            $query->where('is_read', 0);
        }

        // Limit
        $limit = $options['limit'] ?? 50;

        $events = $query->orderBy('id', 'desc')
            ->distinct('id')
            ->limit($limit)
            ->get();

        // Transform to match existing structure
        return $events->map(function ($event) {
            // Find the notification definition that is assigned to this device
            // Since we used withEnabledNotifications (strict), there should be one.
            $notification = $event->notifications->first(function ($n) use ($event) {
                return $n->devices->contains('id', $event->deviceid);
            });

            return [
                'id' => $event->id,
                'type' => $event->type,
                'eventtime' => $event->eventtime,
                'deviceid' => $event->deviceid,
                'attributes' => $event->attributes, // Ensure this is cast/array if model handles it
                'notification_attributes' => $notification ? $notification->attributes : null,
                'notification_type' => $notification ? $notification->type : $event->type,
                'device_name' => $event->device->name ?? null,
                'is_read' => $event->is_read, // Helpful to have
            ];
        })->toArray();
    }
}
