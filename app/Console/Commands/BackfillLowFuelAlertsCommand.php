<?php

namespace App\Console\Commands;

use App\Services\FuelRefillDiffService;
use App\Services\LowFuelAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLowFuelAlertsCommand extends Command
{
    protected $signature = 'alerts:backfill-low-fuel
                            {--from= : Start date (Y-m-d), required}
                            {--to= : End date (Y-m-d), defaults to today}
                            {--device=* : Limit to Traccar device ID(s)}
                            {--chunk=2000 : Positions per DB batch (memory-safe cursor)}
                            {--dry-run : Report events without saving}';

    protected $description = 'Backfill low-fuel alert events from historical tc_positions';

    public function handle(LowFuelAlertService $alerts, FuelRefillDiffService $fuel): int
    {
        $fromInput = trim((string) $this->option('from'));
        if ($fromInput === '') {
            $this->error('Option --from is required (e.g. --from=2026-01-01)');

            return self::FAILURE;
        }

        try {
            $from = Carbon::parse($fromInput)->startOfDay();
            $to = Carbon::parse($this->option('to') ?: now()->format('Y-m-d'))->endOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid date: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($to->lt($from)) {
            $this->error('--to must be on or after --from');

            return self::FAILURE;
        }

        $deviceFilter = collect($this->option('device'))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $chunkSize = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $deviceQuery = DB::connection('pgsql')
            ->table('tc_devices')
            ->select('id', 'attributes');

        if ($deviceFilter !== []) {
            $deviceQuery->whereIn('id', $deviceFilter);
        }

        $devices = $deviceQuery->get();
        if ($devices->isEmpty()) {
            $this->warn('No devices found.');

            return self::SUCCESS;
        }

        $fuel->preloadDeviceAttrs($devices->pluck('id')->map(fn ($id) => (int) $id)->all());

        $created = 0;
        $skipped = 0;
        $devicesProcessed = 0;

        $this->info(sprintf(
            'Backfilling low-fuel alerts from %s to %s (chunk %d)%s',
            $from->toDateTimeString(),
            $to->toDateTimeString(),
            $chunkSize,
            $dryRun ? ' (dry run)' : ''
        ));

        foreach ($devices as $device) {
            $deviceId = (int) $device->id;
            $devAttrs = $alerts->decodeAttributes($device->attributes);

            if ($alerts->fuelLowAlertsDisabled($devAttrs) || ! $alerts->hasFuelConfig($devAttrs)) {
                continue;
            }

            $devicesProcessed++;
            $seedPercent = $this->seedPreviousPercent($alerts, $deviceId, $devAttrs, $from);
            $band = $alerts->initialBandState($seedPercent);
            $wasBelowWarning = $band['wasBelowWarning'];
            $wasBelowCritical = $band['wasBelowCritical'];
            $deviceCreated = 0;
            $positionsScanned = 0;

            $positionQuery = DB::connection('pgsql')
                ->table('tc_positions')
                ->select('id', 'fixtime', 'attributes')
                ->where('deviceid', $deviceId)
                ->whereBetween('fixtime', [$from, $to])
                ->orderBy('fixtime')
                ->orderBy('id');

            foreach ($positionQuery->lazy($chunkSize) as $position) {
                $positionsScanned++;
                $posAttrs = $alerts->decodeAttributes($position->attributes);
                $percent = $alerts->fuelPercentFromPosition($posAttrs, $devAttrs);

                if ($percent === null) {
                    continue;
                }

                $entry = $alerts->bandEntryAlertTypes($wasBelowWarning, $wasBelowCritical, $percent);
                $wasBelowWarning = $entry['wasBelowWarning'];
                $wasBelowCritical = $entry['wasBelowCritical'];

                if ($entry['types'] === []) {
                    continue;
                }

                $positionId = (int) $position->id;
                $eventTime = Carbon::parse($position->fixtime);

                foreach ($entry['types'] as $type) {
                    if ($alerts->eventExists($deviceId, $type, $positionId)) {
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            '  [dry-run] device %d position %d %s at %s (%d%%)',
                            $deviceId,
                            $positionId,
                            $type,
                            $eventTime->toDateTimeString(),
                            $percent
                        ));
                        $created++;
                        $deviceCreated++;
                        continue;
                    }

                    try {
                        $alerts->createEvent($deviceId, $positionId, $type, $percent, $eventTime);
                        $created++;
                        $deviceCreated++;
                        $this->line(sprintf(
                            '  device %d position %d %s at %s (%d%%)',
                            $deviceId,
                            $positionId,
                            $type,
                            $eventTime->toDateTimeString(),
                            $percent
                        ));
                    } catch (\Throwable $e) {
                        $this->error("  Failed device {$deviceId} position {$positionId}: ".$e->getMessage());
                    }
                }
            }

            $this->line(sprintf(
                '  device %d: scanned %d positions, %s %d event(s)',
                $deviceId,
                $positionsScanned,
                $dryRun ? 'would create' : 'created',
                $deviceCreated
            ));
        }

        $this->info(sprintf(
            'Done. Devices processed: %d. Events %s: %d. Skipped (already exist): %d.',
            $devicesProcessed,
            $dryRun ? 'would be created' : 'created',
            $created,
            $skipped
        ));

        return self::SUCCESS;
    }

    private function seedPreviousPercent(
        LowFuelAlertService $alerts,
        int $deviceId,
        array $devAttrs,
        Carbon $from,
    ): ?int {
        $row = DB::connection('pgsql')
            ->table('tc_positions')
            ->select('attributes')
            ->where('deviceid', $deviceId)
            ->where('fixtime', '<', $from)
            ->orderByDesc('fixtime')
            ->orderByDesc('id')
            ->limit(1)
            ->first();

        if ($row === null) {
            return null;
        }

        $posAttrs = $alerts->decodeAttributes($row->attributes);

        return $alerts->fuelPercentFromPosition($posAttrs, $devAttrs);
    }
}
