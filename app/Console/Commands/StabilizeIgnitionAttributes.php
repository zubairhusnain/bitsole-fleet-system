<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StabilizeIgnitionAttributes extends Command
{
    /**
     * Position attribute keys to copy from last ignition-ON position when current is ignition-OFF.
     * Add keys here (e.g. io9, io12, fuel).
     */
    private const STABILIZE_KEYS = [
        'io9',
    ];

    protected $signature = 'traccar:stabilize-ignition-attributes
                            {--device-id= : Only process this Traccar device id}
                            {--limit=100 : Max devices per run}
                            {--dry-run : Log changes without updating positions}';

    protected $description = 'For devices with ignition OFF on current position, copy STABILIZE_KEYS values from the last ignition-ON position';

    public function handle(): int
    {
        $deviceFilter = $this->option('device-id') ? (int) $this->option('device-id') : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $attrKeys = self::STABILIZE_KEYS;

        if ($attrKeys === []) {
            $this->warn('No attributes in STABILIZE_KEYS — nothing to do.');

            return 0;
        }

        // 1) Devices whose current (latest) position has ignition OFF
        $devices = $this->devicesWithIgnitionOffPosition($deviceFilter, $limit);

        $devicesChecked = $devices->count();
        $positionsUpdated = 0;
        $keysUpdated = 0;

        foreach ($devices as $device) {
            try {
                $currentAttrs = $this->decodeJson($device->position_attributes ?? null);

                // 2) Last ignition-ON position for this device (before current)
                $lastOn = $this->lastIgnitionOnPosition(
                    (int) $device->device_id,
                    (int) $device->position_id,
                    $device->fixtime
                );

                if (! $lastOn) {
                    continue;
                }

                $lastOnAttrs = $this->decodeJson($lastOn->attributes ?? null);

                // 3) Copy listed attribute values from ignition-ON → current (ignition-OFF) position
                $result = $this->updateCurrentPositionAttributes(
                    (int) $device->device_id,
                    (int) $device->position_id,
                    $currentAttrs,
                    $lastOnAttrs,
                    (int) $lastOn->id,
                    $attrKeys,
                    $dryRun
                );

                $positionsUpdated += $result['positions'];
                $keysUpdated += $result['keys'];
            } catch (\Throwable $e) {
                $this->warn("Device {$device->device_id}: {$e->getMessage()}");
                Log::warning('[StabilizeIgnitionAttributes] device failed', [
                    'device_id' => $device->device_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("[StabilizeIgnitionAttributes] Ignition-OFF devices: {$devicesChecked}, updated {$positionsUpdated} position(s), {$keysUpdated} attribute key(s).");

        return 0;
    }

    /**
     * Devices where tc_devices.positionid points to a position with ignition OFF.
     */
    private function devicesWithIgnitionOffPosition(?int $deviceFilter, int $limit)
    {
        $query = DB::connection('pgsql')
            ->table('tc_devices as d')
            ->join('tc_positions as p', 'p.id', '=', 'd.positionid')
            ->where('d.positionid', '>', 0)
            ->whereColumn('p.deviceid', 'd.id')
            ->whereRaw($this->ignitionOffSql('p.attributes'))
            ->select([
                'd.id as device_id',
                'p.id as position_id',
                'p.fixtime',
                'p.attributes as position_attributes',
            ])
            ->limit($limit);

        if ($deviceFilter) {
            $query->where('d.id', $deviceFilter);
        }

        return $query->get();
    }

    private function lastIgnitionOnPosition(int $deviceId, int $currentPositionId, $currentFixtime): ?object
    {
        return DB::connection('pgsql')
            ->table('tc_positions')
            ->where('deviceid', $deviceId)
            ->where('id', '<>', $currentPositionId)
            ->where('fixtime', '<', $currentFixtime)
            ->whereRaw($this->ignitionOnSql('attributes'))
            ->orderByDesc('fixtime')
            ->limit(1)
            ->select(['id', 'fixtime', 'attributes'])
            ->first();
    }

    /**
     * @return array{positions: int, keys: int}
     */
    private function updateCurrentPositionAttributes(
        int $deviceId,
        int $currentPositionId,
        array $currentAttrs,
        array $lastOnAttrs,
        int $lastOnPositionId,
        array $attrKeys,
        bool $dryRun
    ): array {
        $keysChanged = 0;

        foreach ($attrKeys as $attrKey) {
            $jsonKey = $this->jsonKeyName($attrKey);
            $curVal = $this->readAttributeValue($currentAttrs, $attrKey);
            $prevVal = $this->readAttributeValue($lastOnAttrs, $attrKey);

            if ($curVal === null || $prevVal === null || $prevVal <= 0) {
                continue;
            }

            if (abs($prevVal - $curVal) < 0.01) {
                continue;
            }

            $this->line("Device {$deviceId}: ignition OFF — {$jsonKey} {$curVal} → {$prevVal} (from position #{$lastOnPositionId}, current #{$currentPositionId})");

            if (! $dryRun) {
                DB::connection('pgsql')->update('
                    UPDATE tc_positions
                    SET attributes = jsonb_set(
                        attributes::jsonb,
                        ?::text[],
                        to_jsonb(?::numeric),
                        true
                    )
                    WHERE id = ?
                ', [
                    '{'.$jsonKey.'}',
                    $prevVal,
                    $currentPositionId,
                ]);

                $currentAttrs[$jsonKey] = $prevVal;
            }

            $keysChanged++;
        }

        return [
            'positions' => $keysChanged > 0 ? 1 : 0,
            'keys' => $keysChanged,
        ];
    }

    private function ignitionOffSql(string $column): string
    {
        $col = "({$column}::jsonb ->> 'ignition')";

        return "({$col} IN ('false', '0', 'off', 'no') OR ({$col})::boolean = false)";
    }

    private function ignitionOnSql(string $column): string
    {
        $col = "({$column}::jsonb ->> 'ignition')";

        return "({$col} IN ('true', '1', 'on', 'yes') OR ({$col})::boolean = true)";
    }

    private function jsonKeyName(string $attrKey): string
    {
        $k = strtolower(trim($attrKey));
        if (preg_match('/^io(\d+)$/', $k, $m)) {
            return 'io'.$m[1];
        }
        if (preg_match('/^\d+$/', $k)) {
            return 'io'.$k;
        }

        return $attrKey;
    }

    private function readAttributeValue(array $attrs, string $attrKey): ?float
    {
        $candidates = array_unique([
            $attrKey,
            $this->jsonKeyName($attrKey),
            preg_replace('/^io/i', '', $attrKey),
        ]);

        foreach ($candidates as $k) {
            if ($k === '' || $k === null) {
                continue;
            }
            if (! array_key_exists($k, $attrs)) {
                continue;
            }
            $n = is_numeric($attrs[$k]) ? (float) $attrs[$k] : null;
            if ($n !== null) {
                return $n;
            }
        }

        return null;
    }

    private function decodeJson($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
