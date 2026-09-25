<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGeofencePositionsForDevice;
use App\Services\GeofenceAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ObserveGeofenceAlertsCommand extends Command
{
    protected $signature = 'positions:observe-geofence-alerts
                            {--once : Poll one batch and exit}
                            {--sync : Process inline instead of queueing jobs}
                            {--interval=5 : Seconds between polls in continuous mode}
                            {--limit=2000 : Max positions per batch}
                            {--from= : Backfill start fixtime (Y-m-d)}
                            {--to= : Backfill end fixtime (Y-m-d)}
                            {--device=* : Limit to tracking server device IDs}';

    protected $description = 'Poll new positions and queue geofence enter/exit jobs (or process inline with --sync)';

    private const LAST_POSITION_CACHE_KEY = 'geofence_alert_last_position_id';

    public function handle(GeofenceAlertService $service): int
    {
        $limit = max(100, (int) $this->option('limit'));
        $deviceIds = collect($this->option('device'))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($this->option('from')) {
            return $this->runBackfill($service, $limit, $deviceIds);
        }

        if ($this->option('once')) {
            $count = $this->pollAndDispatch($service, $limit, $deviceIds);
            $this->info($this->option('sync')
                ? "Processed {$count} device batch(es)."
                : "Queued {$count} device batch job(s).");

            return self::SUCCESS;
        }

        $interval = max(1, (int) $this->option('interval'));
        $mode = $this->option('sync') ? 'sync' : 'queue';

        $this->info("Starting geofence position poller ({$mode}, every {$interval}s)...");

        while (true) {
            try {
                $dispatched = $this->pollAndDispatch($service, $limit, $deviceIds);
                if ($dispatched > 0) {
                    $this->info($this->option('sync')
                        ? "Processed {$dispatched} device batch(es)."
                        : "Queued {$dispatched} device batch job(s).");
                }
            } catch (\Throwable $e) {
                $this->error('Geofence poller error: '.$e->getMessage());
                sleep(5);
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function pollAndDispatch(GeofenceAlertService $service, int $limit, array $deviceIds): int
    {
        $rows = $this->fetchNewPositions($limit, $deviceIds);
        if ($rows->isEmpty()) {
            return 0;
        }

        $grouped = $rows->groupBy(fn ($row) => (int) $row->deviceid);
        $processed = 0;
        $maxProcessedId = 0;

        foreach ($grouped as $deviceId => $deviceRows) {
            $deviceId = (int) $deviceId;
            $positionIds = $deviceRows->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $batchMaxId = (int) $deviceRows->max('id');
            if ($batchMaxId > $maxProcessedId) {
                $maxProcessedId = $batchMaxId;
            }

            if ($this->option('sync')) {
                $created = $service->processDeviceBatch($deviceId, $deviceRows->values());
                if ($created > 0) {
                    $this->line("  device {$deviceId}: {$created} event(s)");
                }
            } else {
                ProcessGeofencePositionsForDevice::dispatch($deviceId, $positionIds);
            }

            $processed++;
        }

        if ($this->option('sync') && $maxProcessedId > 0) {
            $currentLastId = (int) Cache::get(self::LAST_POSITION_CACHE_KEY, 0);
            if ($maxProcessedId > $currentLastId) {
                Cache::put(self::LAST_POSITION_CACHE_KEY, $maxProcessedId, now()->addDays(7));
            }
        }

        return $processed;
    }

    private function fetchNewPositions(int $limit, array $deviceIds): Collection
    {
        $dbMaxId = (int) (DB::connection('pgsql')->table('tc_positions')->max('id') ?? 0);
        $lastId = (int) Cache::get(self::LAST_POSITION_CACHE_KEY, max(0, $dbMaxId - 1));

        if ($dbMaxId - $lastId > 50000) {
            $this->warn("Last ID {$lastId} is far behind max {$dbMaxId}; skipping ahead.");
            $lastId = max(0, $dbMaxId - $limit);
        }

        return $this->basePositionQuery($deviceIds)
            ->where('p.id', '>', $lastId)
            ->orderBy('p.deviceid')
            ->orderBy('p.fixtime')
            ->orderBy('p.id')
            ->limit($limit)
            ->get();
    }

    private function runBackfill(GeofenceAlertService $service, int $limit, array $deviceIds): int
    {
        $from = $this->option('from').' 00:00:00';
        $to = ($this->option('to') ?: now()->format('Y-m-d')).' 23:59:59';
        $cacheKey = 'geofence_alert_backfill_id:'.md5($from.'|'.$to.'|'.implode(',', $deviceIds));
        $lastId = (int) Cache::get($cacheKey, 0);

        $this->info("Backfilling geofence alerts from {$from} to {$to}...");

        $insideByDevice = [];
        $totalCreated = 0;

        while (true) {
            $query = $this->basePositionQuery($deviceIds)
                ->whereBetween('p.fixtime', [$from, $to])
                ->where('p.id', '>', $lastId)
                ->orderBy('p.deviceid')
                ->orderBy('p.fixtime')
                ->orderBy('p.id')
                ->limit($limit);

            $rows = $query->get();
            if ($rows->isEmpty()) {
                break;
            }

            $result = $service->processBatch($rows, $insideByDevice);
            $insideByDevice = $result['insideByDevice'];
            $totalCreated += $result['created'];

            $lastId = (int) $rows->last()->id;
            Cache::put($cacheKey, $lastId, now()->addHours(6));
            $this->line("  ... up to position id {$lastId} ({$result['created']} created this batch)");
        }

        $service->saveInsideByDevice($insideByDevice);
        Cache::forget($cacheKey);
        $this->info("Backfill complete. Created {$totalCreated} geofence event(s).");

        return self::SUCCESS;
    }

    private function basePositionQuery(array $deviceIds)
    {
        $query = DB::connection('pgsql')
            ->table('tc_positions as p')
            ->join('tc_device_geofence as dg', 'dg.deviceid', '=', 'p.deviceid')
            ->select('p.id', 'p.deviceid', 'p.latitude', 'p.longitude', 'p.fixtime')
            ->distinct();

        if ($deviceIds !== []) {
            $query->whereIn('p.deviceid', $deviceIds);
        }

        return $query;
    }
}
