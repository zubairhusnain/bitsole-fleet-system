<?php

namespace App\Jobs;

use App\Events\AlertsUpdated;
use App\Models\Devices;
use App\Models\User;
use App\Services\GeofenceAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessGeofencePositionsForDevice implements ShouldQueue
{
    use Queueable;

    public const LAST_POSITION_CACHE_KEY = 'geofence_alert_last_position_id';

    public int $tries = 3;

    /** @param  list<int>  $positionIds  Ordered position ids for this device */
    public function __construct(
        public int $deviceId,
        public array $positionIds,
    ) {
        $this->onQueue('geofence');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('geofence-device-'.$this->deviceId))
                ->releaseAfter(5)
                ->expireAfter(120),
        ];
    }

    public function handle(GeofenceAlertService $service): void
    {
        $positionIds = array_values(array_unique(array_map('intval', $this->positionIds)));
        $positionIds = array_values(array_filter($positionIds, fn (int $id) => $id > 0));

        if ($positionIds === []) {
            return;
        }

        $rows = DB::connection('pgsql')
            ->table('tc_positions')
            ->where('deviceid', $this->deviceId)
            ->whereIn('id', $positionIds)
            ->orderBy('fixtime')
            ->orderBy('id')
            ->get(['id', 'deviceid', 'latitude', 'longitude', 'fixtime']);

        if ($rows->isEmpty()) {
            return;
        }

        $created = $service->processDeviceBatch($this->deviceId, $rows);

        $maxId = (int) $rows->max('id');
        $currentLastId = (int) Cache::get(self::LAST_POSITION_CACHE_KEY, 0);
        if ($maxId > $currentLastId) {
            Cache::put(self::LAST_POSITION_CACHE_KEY, $maxId, now()->addDays(7));
        }

        if ($created > 0) {
            $this->broadcastAlerts($this->deviceId);
        }
    }

    private function broadcastAlerts(int $deviceId): void
    {
        $userId = Devices::query()->where('device_id', $deviceId)->value('user_id');
        if (! $userId) {
            return;
        }

        $user = User::find((int) $userId);
        if (! $user) {
            return;
        }

        try {
            broadcast(new AlertsUpdated($user));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
