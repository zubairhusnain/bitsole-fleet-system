<?php

namespace App\Console\Commands;

use App\Services\FuelRefillDiffService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ObserveFuelRefillDiffCommand extends Command
{
    protected $signature = 'positions:observe-fuel-refill
                            {--once : Process one batch and exit}
                            {--limit=2000 : Max positions per batch}
                            {--from= : Backfill start fixtime (Y-m-d)}
                            {--to= : Backfill end fixtime (Y-m-d)}
                            {--device=* : Limit to tracking server device IDs}
                            {--reprocess : Include positions that already have fuelRefillDiff}';

    protected $description = 'Detect fuel increases on new positions and store fuelRefillDiff attribute';

    private const CARRY_CACHE_KEY = 'fuel_refill_diff_last_litres';

    public function handle(FuelRefillDiffService $service): int
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
            $updated = $this->processNewPositions($service, $limit, $deviceIds);
            $this->info("Processed batch. Updated {$updated} position(s) with fuelRefillDiff.");

            return self::SUCCESS;
        }

        $this->info('Starting fuel refill observer (continuous)...');

        while (true) {
            try {
                $updated = $this->processNewPositions($service, $limit, $deviceIds);
                if ($updated > 0) {
                    $this->info("Updated {$updated} position(s) with fuelRefillDiff.");
                }
            } catch (\Throwable $e) {
                $this->error('Fuel refill observer error: '.$e->getMessage());
                sleep(5);
            }

            sleep(2);
        }

        return self::SUCCESS;
    }

    private function processNewPositions(FuelRefillDiffService $service, int $limit, array $deviceIds): int
    {
        $dbMaxId = (int) (DB::connection('pgsql')->table('tc_positions')->max('id') ?? 0);
        $lastId = (int) Cache::get('fuel_refill_diff_last_position_id', max(0, $dbMaxId - 1));

        if ($dbMaxId - $lastId > 50000) {
            $this->warn("Last ID {$lastId} is far behind max {$dbMaxId}; skipping ahead.");
            $lastId = max(0, $dbMaxId - $limit);
        }

        $skipExisting = ! $this->option('reprocess');
        $query = $this->basePositionQuery($deviceIds, $skipExisting)
            ->where('p.id', '>', $lastId)
            ->orderBy('p.deviceid')
            ->orderBy('p.fixtime')
            ->orderBy('p.id')
            ->limit($limit);

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $carryOver = $this->loadCarryOverLitres();
        $result = $service->processBatch($rows, $carryOver);
        $this->saveCarryOverLitres($result['carryOverLitres']);

        $newLastId = (int) $rows->last()->id;
        Cache::put('fuel_refill_diff_last_position_id', $newLastId, now()->addDay());

        return $result['updated'];
    }

    private function runBackfill(FuelRefillDiffService $service, int $limit, array $deviceIds): int
    {
        $from = $this->option('from').' 00:00:00';
        $to = ($this->option('to') ?: now()->format('Y-m-d')).' 23:59:59';
        $cacheKey = 'fuel_refill_diff_backfill_id:'.md5($from.'|'.$to.'|'.implode(',', $deviceIds));
        $lastId = (int) Cache::get($cacheKey, 0);

        $this->info("Backfilling fuelRefillDiff from {$from} to {$to}...");

        $carryOver = [];
        $totalUpdated = 0;

        while (true) {
            $skipExisting = ! $this->option('reprocess');
            $query = $this->basePositionQuery($deviceIds, $skipExisting)
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

            $result = $service->processBatch($rows, $carryOver);
            $carryOver = $result['carryOverLitres'];
            $totalUpdated += $result['updated'];

            $lastId = (int) $rows->last()->id;
            Cache::put($cacheKey, $lastId, now()->addHours(6));
            $this->line("  ... up to position id {$lastId} ({$result['updated']} updated this batch)");
        }

        Cache::forget($cacheKey);
        $this->info("Backfill complete. Updated {$totalUpdated} position(s).");

        return self::SUCCESS;
    }

    /**
     * Only load positions for fuel-configured devices; skip rows already tagged unless --reprocess.
     */
    private function basePositionQuery(array $deviceIds, bool $skipExisting)
    {
        $query = DB::connection('pgsql')
            ->table('tc_positions as p')
            ->select('p.id', 'p.deviceid', 'p.fixtime', 'p.attributes');

        if (! empty($deviceIds)) {
            $query->whereIn('p.deviceid', $deviceIds);
        }

        if ($skipExisting) {
            $query->whereRaw("NOT jsonb_exists(COALESCE(p.attributes::jsonb, '{}'::jsonb), 'fuelRefillDiff')");
        }

        return $query;
    }

    /** @return array<int, float> */
    private function loadCarryOverLitres(): array
    {
        $raw = Cache::get(self::CARRY_CACHE_KEY, []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $deviceId => $litres) {
            if (is_numeric($litres)) {
                $out[(int) $deviceId] = (float) $litres;
            }
        }

        return $out;
    }

    /** @param  array<int, float>  $litresByDevice */
    private function saveCarryOverLitres(array $litresByDevice): void
    {
        if ($litresByDevice === []) {
            return;
        }

        $existing = $this->loadCarryOverLitres();
        Cache::put(self::CARRY_CACHE_KEY, array_replace($existing, $litresByDevice), now()->addDay());
    }
}
