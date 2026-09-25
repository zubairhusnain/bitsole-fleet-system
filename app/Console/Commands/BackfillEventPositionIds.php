<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillEventPositionIds extends Command
{
    protected $signature = 'traccar:backfill-event-positions
                            {--limit=25 : Max events to process per run (keep low to avoid disk pressure)}
                            {--device_id= : Limit to one device}
                            {--continuous : Keep running until no rows remain}';

    protected $description = 'Set tc_events.positionid from nearest tc_positions (lightweight, one event at a time)';

    public function handle(): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $deviceId = $this->option('device_id') ? (int) $this->option('device_id') : null;
        $continuous = (bool) $this->option('continuous');

        do {
            $updated = $this->processBatch($limit, $deviceId);

            if ($updated === 0) {
                if (! $continuous) {
                    $this->info('No events missing positionid.');
                }
                break;
            }

            $this->info("Updated positionid on {$updated} event(s).");
            $this->safeLog("Updated positionid on {$updated} event(s).");
        } while ($continuous);

        return self::SUCCESS;
    }

    private function processBatch(int $limit, ?int $deviceId): int
    {
        $query = DB::connection('pgsql')->table('tc_events')
            ->select('id', 'deviceid', 'eventtime')
            ->where(function ($q) {
                $q->whereNull('positionid')->orWhere('positionid', 0);
            })
            ->orderByDesc('id')
            ->limit($limit);

        if ($deviceId) {
            $query->where('deviceid', $deviceId);
        }

        $events = $query->get();
        if ($events->isEmpty()) {
            return 0;
        }

        $updated = 0;

        foreach ($events as $event) {
            $positionId = $this->resolvePositionId(
                (int) $event->deviceid,
                (string) $event->eventtime
            );

            if (! $positionId) {
                continue;
            }

            $affected = DB::connection('pgsql')->table('tc_events')
                ->where('id', (int) $event->id)
                ->where(function ($q) {
                    $q->whereNull('positionid')->orWhere('positionid', 0);
                })
                ->update(['positionid' => $positionId]);

            if ($affected > 0) {
                $updated++;
            }
        }

        return $updated;
    }

    private function resolvePositionId(int $deviceId, string $eventTime): ?int
    {
        if ($deviceId <= 0) {
            return null;
        }

        $before = $this->findPositionId(
            'deviceid = ? AND fixtime <= ? AND latitude <> 0 AND longitude <> 0',
            [$deviceId, $eventTime],
            'fixtime DESC'
        );
        if ($before) {
            return $before;
        }

        $around = $this->findPositionId(
            "deviceid = ? AND fixtime BETWEEN ?::timestamp - interval '30 minutes' AND ?::timestamp + interval '30 minutes'
             AND latitude <> 0 AND longitude <> 0",
            [$deviceId, $eventTime, $eventTime],
            'ABS(EXTRACT(EPOCH FROM (fixtime - ?::timestamp)))',
            [$eventTime]
        );
        if ($around) {
            return $around;
        }

        return $this->findDeviceCurrentPositionId($deviceId);
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @param  array<int, mixed>  $orderBindings
     */
    private function findPositionId(string $where, array $bindings, string $orderBy, array $orderBindings = []): ?int
    {
        $orderSql = str_contains($orderBy, '?') ? $orderBy : $orderBy;
        $allBindings = array_merge($bindings, $orderBindings);

        $row = DB::connection('pgsql')->selectOne("
            SELECT id
            FROM tc_positions
            WHERE {$where}
            ORDER BY {$orderSql}
            LIMIT 1
        ", $allBindings);

        return $row ? (int) $row->id : null;
    }

    private function findDeviceCurrentPositionId(int $deviceId): ?int
    {
        $row = DB::connection('pgsql')->selectOne('
            SELECT d.positionid AS id
            FROM tc_devices d
            INNER JOIN tc_positions p ON p.id = d.positionid
            WHERE d.id = ?
              AND d.positionid IS NOT NULL
              AND d.positionid <> 0
              AND p.latitude <> 0
              AND p.longitude <> 0
            LIMIT 1
        ', [$deviceId]);

        return $row ? (int) $row->id : null;
    }

    private function safeLog(string $message): void
    {
        try {
            Log::info('[BackfillEventPositionIds] '.$message);
        } catch (\Throwable $e) {
        }
    }
}
