<?php

namespace App\Console\Commands;

use App\Events\AlertsUpdated;
use App\Models\Devices;
use App\Models\User;
use App\Services\LowFuelAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckLowFuelAlertsCommand extends Command
{
    protected $signature = 'alerts:check-low-fuel';

    protected $description = 'Create low-fuel alert events when fuel drops below 25% or 10%';

    public function handle(LowFuelAlertService $alerts): int
    {
        $rows = DB::connection('pgsql')
            ->table('tc_devices as d')
            ->join('tc_positions as p', 'p.id', '=', 'd.positionid')
            ->whereNotNull('d.positionid')
            ->select('d.id', 'd.attributes as dev_attrs', 'p.id as position_id', 'p.attributes as pos_attrs')
            ->get();

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $affectedUserIds = [];

        foreach ($rows as $row) {
            $deviceId = (int) $row->id;
            $devAttrs = $alerts->decodeAttributes($row->dev_attrs);
            $posAttrs = $alerts->decodeAttributes($row->pos_attrs);

            if ($alerts->fuelLowAlertsDisabled($devAttrs) || ! $alerts->hasFuelConfig($devAttrs)) {
                continue;
            }

            $percent = $alerts->fuelPercentFromPosition($posAttrs, $devAttrs);
            if ($percent === null) {
                continue;
            }

            $eventType = $alerts->liveAlertType($percent);

            if ($eventType === null) {
                Cache::forget($alerts->cooldownKey($deviceId, 'lowFuelCritical'));
                Cache::forget($alerts->cooldownKey($deviceId, 'lowFuelWarning'));
                continue;
            }

            $cacheKey = $alerts->cooldownKey($deviceId, $eventType);
            if (Cache::has($cacheKey)) {
                continue;
            }

            try {
                $alerts->createEvent(
                    $deviceId,
                    (int) $row->position_id,
                    $eventType,
                    $percent,
                    now(),
                );

                Cache::put($cacheKey, true, now()->addMinutes(LowFuelAlertService::COOLDOWN_MINUTES));

                $local = Devices::where('device_id', $deviceId)->first();
                if ($local?->user_id) {
                    $affectedUserIds[(int) $local->user_id] = true;
                }

                $this->info("Low fuel alert ({$eventType}) for device {$deviceId} at {$percent}%");
            } catch (\Throwable $e) {
                $this->error("Failed low fuel alert for device {$deviceId}: ".$e->getMessage());
            }
        }

        foreach (array_keys($affectedUserIds) as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            try {
                broadcast(new AlertsUpdated($user));
            } catch (\Throwable $e) {
                $this->warn("Broadcast failed for user {$userId}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
