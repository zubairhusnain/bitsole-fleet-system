<?php

namespace App\Services;

use App\Models\Devices;
use App\Models\User;
 
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

        $query = \App\Models\TcEvent::query()
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

        // Filter options
        if (isset($options['unreadOnly']) && $options['unreadOnly']) {
            $query->where('is_read', 0);
        }

        // Limit
        $limit = $options['limit'] ?? 50;

        $events = $query->orderByDesc('tc_events.id')
            ->limit($limit)
            ->get();

        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->type,
                'eventtime' => $event->eventtime,
                'deviceid' => $event->deviceid,
                'positionid' => $event->positionid,
                'attributes' => $event->attributes,
                'notification_type' => $event->type,
                'notification_attributes' => null,
                'device_name' => $event->device->name ?? null,
                'is_read' => $event->is_read,
                'latitude' => $event->position->latitude ?? null,
                'longitude' => $event->position->longitude ?? null,
                'address' => $event->position->address ?? null,
            ];
        })->toArray();
    }
}
 
 