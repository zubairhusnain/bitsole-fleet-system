<?php

namespace App\Console\Commands;

use App\Models\Devices;
use App\Models\TcDevice;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Validate Trip Summary fuel-related columns against live DB + ReportService output.
 */
class ValidateTripSummaryFuelColumns extends Command
{
    protected $signature = 'reports:validate-fuel-columns
                            {--from= : Start date (Y-m-d), default 7 days ago}
                            {--to= : End date (Y-m-d), default today}
                            {--device=* : Limit to specific tracking server device IDs}
                            {--only-issues : Print only rows with validation flags}
                            {--limit=0 : Max flagged rows to print (0 = all)}';

    protected $description = 'Review Trip Summary fuel columns (avg litres, refills, consumption) using real database values';

    public function handle(ReportService $reportService): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::today()->subDays(6)->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        if ($from->gt($to)) {
            $this->error('--from must be on or before --to');

            return self::FAILURE;
        }

        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        $filterIds = collect($this->option('device'))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (! empty($filterIds)) {
            $deviceIds = $filterIds;
        } else {
            $deviceIds = Devices::query()
                ->select('device_id')
                ->distinct()
                ->pluck('device_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            if (empty($deviceIds)) {
                $deviceIds = TcDevice::query()
                    ->where(function ($q) {
                        $q->where('disabled', false)->orWhereNull('disabled');
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }

        if (empty($deviceIds)) {
            $this->warn('No devices found to validate.');

            return self::SUCCESS;
        }

        $devices = TcDevice::query()
            ->whereIn('id', $deviceIds)
            ->orderBy('name')
            ->get(['id', 'name', 'attributes']);

        $fuelConfig = $this->buildFuelConfig($devices);

        $request = (object) [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
        ];

        $this->info(sprintf(
            'Trip Summary fuel validation — %d device(s), %s → %s (%d days)',
            count($deviceIds),
            $request->from_date,
            $request->to_date,
            $days
        ));
        $this->line(str_repeat('─', 120));

        $t0 = microtime(true);
        $rows = $reportService->fetchFleetSummaryDb($request, $deviceIds);
        $elapsed = round(microtime(true) - $t0, 2);
        $byId = collect($rows)->keyBy('key');

        $this->line("fetchFleetSummaryDb completed in {$elapsed}s");
        $this->newLine();

        $headers = [
            'ID',
            'Vehicle',
            'Dist km',
            'Avg L/day',
            'Refill L',
            'Ref Freq',
            'Consumption L',
            'Flags',
        ];

        $onlyIssues = (bool) $this->option('only-issues');
        $printLimit = (int) $this->option('limit');
        $issues = [];
        $stats = [
            'rows' => 0,
            'configured' => 0,
            'with_consumption' => 0,
            'with_refill' => 0,
            'flagged' => 0,
        ];

        $tableRows = [];

        foreach ($devices as $device) {
            $id = (int) $device->id;
            $row = $byId->get($id);
            if (! $row) {
                $issues[] = "Device {$id}: missing from fetchFleetSummaryDb result";

                continue;
            }

            $stats['rows']++;
            $cfg = $fuelConfig[$id] ?? ['hasFuel' => false, 'cap' => 50.0];

            if ($cfg['hasFuel']) {
                $stats['configured']++;
            }

            $parsed = $this->parseFuelRow($row, $days, $cfg['cap']);
            $flags = $this->validateFuelRow($parsed, $cfg, $days);

            if ($parsed['consumptionL'] > 0) {
                $stats['with_consumption']++;
            }
            if ($parsed['refillL'] > 0 || $parsed['refFreq'] > 0) {
                $stats['with_refill']++;
            }

            if ($flags) {
                $stats['flagged']++;
                foreach ($flags as $flag) {
                    $issues[] = "[{$id}] ".($device->name ?? '?').": {$flag}";
                }
            }

            $flagStr = $flags ? implode(', ', $flags) : ($cfg['hasFuel'] ? 'ok' : 'no-fuel-config');

            if ($onlyIssues && ! $flags) {
                continue;
            }

            $tableRows[] = [
                $id,
                mb_substr((string) ($device->name ?? '?'), 0, 28),
                number_format($parsed['distKm'], 1, '.', ''),
                number_format($parsed['avgL'], 2, '.', ''),
                number_format($parsed['refillL'], 1, '.', ''),
                $parsed['refFreq'],
                number_format($parsed['consumptionL'], 1, '.', ''),
                $flagStr,
            ];
        }

        if ($printLimit > 0 && count($tableRows) > $printLimit) {
            $tableRows = array_slice($tableRows, 0, $printLimit);
            $this->warn("Showing first {$printLimit} rows only (--limit).");
        }

        $this->table($headers, $tableRows);

        $this->newLine();
        $this->info('Summary');
        $this->line("  Rows validated:        {$stats['rows']}");
        $this->line("  Fuel sensor configured: {$stats['configured']}");
        $this->line("  Non-zero consumption:  {$stats['with_consumption']}");
        $this->line("  With refill data:      {$stats['with_refill']}");
        $this->line("  Flagged rows:          {$stats['flagged']}");
        $this->line("  Query time:            {$elapsed}s");

        if ($issues) {
            $this->newLine();
            $this->warn('Issues ('.count($issues).'):');
            foreach (array_slice($issues, 0, 50) as $issue) {
                $this->line('  - '.$issue);
            }
            if (count($issues) > 50) {
                $this->line('  ... and '.(count($issues) - 50).' more');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All fuel column checks passed.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, TcDevice>  $devices
     * @return array<int, array{hasFuel: bool, fuelAttr: string, cap: float}>
     */
    private function buildFuelConfig(Collection $devices): array
    {
        $config = [];
        foreach ($devices as $device) {
            $attrs = is_string($device->attributes)
                ? (json_decode($device->attributes, true) ?: [])
                : (is_array($device->attributes) ? $device->attributes : []);

            $fuelKey = trim((string) ($attrs['fuelAttr_key'] ?? ''));
            $fuelAttr = trim((string) ($attrs['fuelAttr'] ?? ''));
            $cap = (float) ($attrs['fuelTankCapacity'] ?? 50);
            $hasFuel = strtolower($fuelAttr) !== 'none' && ($fuelKey !== '' || $fuelAttr !== '');

            $config[(int) $device->id] = [
                'hasFuel' => $hasFuel,
                'fuelAttr' => $fuelAttr ?: $fuelKey,
                'cap' => $cap > 0 ? $cap : 50.0,
            ];
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{distKm: float, avgL: float, refillL: float, refFreq: int, consumptionL: float, consumptionRaw: string}
     */
    private function parseFuelRow(array $row, int $days, float $tankCap): array
    {
        $consumptionRaw = (string) ($row['fuelConsumption'] ?? '0 L');
        $refillRaw = (string) ($row['fuelRefill'] ?? '0 L');

        return [
            'distKm' => (float) ($row['distTotal'] ?? 0),
            'avgL' => (float) ($row['avgLitres'] ?? 0),
            'refillL' => $this->parseLitresString($refillRaw),
            'refFreq' => (int) ($row['fuelRefillFreq'] ?? 0),
            'consumptionL' => $this->parseLitresString($consumptionRaw),
            'consumptionRaw' => $consumptionRaw,
            'tankCap' => $tankCap,
            'days' => $days,
        ];
    }

    private function parseLitresString(string $value): float
    {
        return (float) preg_replace('/[^0-9.]/', '', $value);
    }

    /**
     * @param  array{distKm: float, avgL: float, refillL: float, refFreq: int, consumptionL: float, consumptionRaw: string, tankCap: float, days: int}  $parsed
     * @param  array{hasFuel: bool, fuelAttr: string, cap: float}  $cfg
     * @return list<string>
     */
    private function validateFuelRow(array $parsed, array $cfg, int $days): array
    {
        $flags = [];
        $cap = $cfg['cap'];
        $maxPerDay = $cap * 0.85;

        if ($parsed['consumptionRaw'] !== '' && ! preg_match('/^\d+(\.\d+)?\s*L$/i', trim($parsed['consumptionRaw']))) {
            $flags[] = 'CONSUMPTION_FORMAT';
        }

        if ($parsed['consumptionL'] < 0) {
            $flags[] = 'CONSUMPTION_NEGATIVE';
        }

        $expectedAvg = round(min($parsed['consumptionL'] / $days, $maxPerDay), 2);
        if (abs($parsed['avgL'] - $expectedAvg) > 0.05) {
            $flags[] = 'AVG_VS_CONSUMPTION_MISMATCH';
        }

        if ($parsed['consumptionL'] > 0 && $parsed['avgL'] <= 0) {
            $flags[] = 'CONSUMPTION_WITHOUT_AVG';
        }

        if ($parsed['avgL'] > $maxPerDay + 0.01) {
            $flags[] = 'AVG_EXCEEDS_TANK_CAP';
        }

        if ($parsed['distKm'] > 10 && $parsed['consumptionL'] > ($parsed['distKm'] / 4.0) + 0.5) {
            $flags[] = 'CONSUMPTION_EXCEEDS_DISTANCE_CAP';
        }

        if ($cfg['hasFuel'] && $parsed['distKm'] > 5 && $parsed['consumptionL'] <= 0 && $parsed['avgL'] <= 0) {
            $flags[] = 'ZERO_FUEL_MOVING';
        }

        if (! $cfg['hasFuel'] && ($parsed['consumptionL'] > 0 || $parsed['avgL'] > 0 || $parsed['refillL'] > 0)) {
            $flags[] = 'FUEL_WITHOUT_CONFIG';
        }

        if ($parsed['refFreq'] > 0 && $parsed['refillL'] <= 0) {
            $flags[] = 'FREQ_WITHOUT_LITRES';
        }

        if ($parsed['refillL'] > 0 && $parsed['refFreq'] <= 0) {
            $flags[] = 'LITRES_WITHOUT_FREQ';
        }

        $maxRefillLitres = $cap * max(1, (int) ceil($days / 7)) * 4;
        if ($parsed['refillL'] > $maxRefillLitres) {
            $flags[] = 'REFILL_L_HIGH';
        }

        if ($parsed['refFreq'] > $days * 2) {
            $flags[] = 'REFILL_FREQ_HIGH';
        }

        if ($parsed['consumptionL'] > 0 && $parsed['avgL'] > 0 && $parsed['avgL'] < $maxPerDay - 0.01) {
            $impliedTotal = round($parsed['avgL'] * $days, 2);
            if (abs($impliedTotal - $parsed['consumptionL']) > max(0.5, $parsed['consumptionL'] * 0.05)) {
                $flags[] = 'CONSUMPTION_AVG_TOTAL_DRIFT';
            }
        }

        return $flags;
    }
}
