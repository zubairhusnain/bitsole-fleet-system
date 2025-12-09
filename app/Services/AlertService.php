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
        // No alerts for Super Admin (3) and Distributor (2) if needed,
        // but typically they might want to see them if they monitor devices.
        // However, NotificationController::events excludes them. I will follow that.
        if ($user->role === 3 || $user->role === 2) {
            return [];
        }

        $deviceIds = Devices::where('user_id', $user->id)->pluck('device_id')->toArray();

        if (empty($deviceIds)) {
            return [];
        }

        $query = DB::connection('pgsql')->table('tc_events as e')
            ->join('tc_notifications as n', 'e.type', '=', 'n.type')
            ->leftJoin('tc_device_notification as dn', function($join) {
                $join->on('e.deviceid', '=', 'dn.deviceid')
                     ->on('n.id', '=', 'dn.notificationid');
            })
            ->join('tc_devices as d', 'e.deviceid', '=', 'd.id')
            ->whereIn('e.deviceid', $deviceIds)
            ->where(function($q) {
                $q->whereNotNull('dn.deviceid')
                  ->orWhere('n.always', true);
            })
            ->select(
                'e.id',
                'e.type',
                'e.eventtime',
                'e.deviceid',
                'e.attributes',
                'n.attributes as notification_attributes',
                'n.type as notification_type',
                'd.name as device_name'
            );

        // Filter options
        if (isset($options['unreadOnly']) && $options['unreadOnly']) {
            $query->where('e.is_read', 0);
        }

        // Limit
        $limit = $options['limit'] ?? 50;

        $alerts = $query->orderBy('e.id', 'desc')
            ->distinct('e.id')
            ->limit($limit)
            ->get();

        return $alerts->toArray();
    }
}
