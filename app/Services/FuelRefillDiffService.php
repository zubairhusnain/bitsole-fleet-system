<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detects fuel refills on tc_positions and stores fuelRefillDiff.
 * Fuel litres use the same resolution rules as resources/js/utils/telemetry.js formatFuel().
 */
class FuelRefillDiffService
{
    public const ATTR_KEY = 'fuelRefillDiff';

    private const MIN_INCREASE_LITRES = 3.0;

    /** @var array<int, array> */
    private array $deviceAttrsCache = [];

    /**
     * Preload tc_devices.attributes for a batch (one query instead of N).
     *
     * @param  array<int, int>  $deviceIds
     */
    public function preloadDeviceAttrs(array $deviceIds): void
    {
        $missing = array_values(array_filter(
            array_map('intval', $deviceIds),
            fn ($id) => $id > 0 && ! array_key_exists($id, $this->deviceAttrsCache)
        ));

        if (empty($missing)) {
            return;
        }

        $rows = DB::connection('pgsql')
            ->table('tc_devices')
            ->whereIn('id', $missing)
            ->get(['id', 'attributes']);

        foreach ($missing as $id) {
            $this->deviceAttrsCache[$id] = [];
        }

        foreach ($rows as $row) {
            $this->deviceAttrsCache[(int) $row->id] = $this->decodeAttributes($row->attributes ?? null);
        }
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $positions  ordered by deviceid, fixtime, id
     * @param  array<int, float>  $carryOverLitres  last fuel litres per device from prior batch
     * @return array{updated: int, carryOverLitres: array<int, float>}
     */
    public function processBatch(iterable $positions, array $carryOverLitres = []): array
    {
        if ($positions instanceof Collection) {
            $rows = $positions->all();
        } else {
            $rows = is_array($positions) ? $positions : iterator_to_array($positions, false);
        }

        if ($rows === []) {
            return ['updated' => 0, 'carryOverLitres' => $carryOverLitres];
        }

        $deviceIds = array_values(array_unique(array_map(
            fn ($p) => (int) ($p->deviceid ?? 0),
            $rows
        )));
        $this->preloadDeviceAttrs($deviceIds);

        $lastLitresByDevice = $carryOverLitres;
        $pendingUpdates = [];

        foreach ($rows as $position) {
            $deviceId = (int) ($position->deviceid ?? 0);
            $positionId = (int) ($position->id ?? 0);
            if ($deviceId <= 0 || $positionId <= 0) {
                continue;
            }

            $devAttrs = $this->deviceAttrsCache[$deviceId] ?? [];
            $posAttrs = $this->decodeAttributes($position->attributes ?? null);
            if ($this->existingRefillDiff($posAttrs) !== null) {
                $litres = $this->fuelLitres($posAttrs, $devAttrs);
                if ($litres !== null) {
                    $lastLitresByDevice[$deviceId] = $litres;
                }
                continue;
            }

            $currentLitres = $this->fuelLitres($posAttrs, $devAttrs);
            if ($currentLitres === null) {
                continue;
            }

            $previousLitres = $lastLitresByDevice[$deviceId] ?? null;
            if ($previousLitres !== null && $currentLitres > ($previousLitres + self::MIN_INCREASE_LITRES)) {
                $cap = $this->num($devAttrs['fuelTankCapacity'] ?? $devAttrs['FuelTankCapacity'] ?? null);
                if (! $cap || $cap <= 0) {
                    $cap = 50.0;
                }
                $diff = round(min($currentLitres - $previousLitres, $cap * 0.95), 1);
                if ($diff >= self::MIN_INCREASE_LITRES) {
                    $pendingUpdates[$positionId] = $diff;
                }
            }

            $lastLitresByDevice[$deviceId] = $currentLitres;
        }

        $updated = $this->persistRefillDiffBulk($pendingUpdates);

        return [
            'updated' => $updated,
            'carryOverLitres' => $lastLitresByDevice,
        ];
    }

    /**
     * Single-position entry point (observer / manual use).
     *
     * @return array{litres: ?float, updated: bool}
     */
    public function processPosition(object $position, ?float $previousLitres = null): array
    {
        $deviceId = (int) ($position->deviceid ?? 0);
        $carryOver = [];

        if ($previousLitres !== null && $deviceId > 0) {
            $carryOver[$deviceId] = $previousLitres;
        } elseif ($deviceId > 0) {
            $this->preloadDeviceAttrs([$deviceId]);
            $prev = $this->lookupPreviousFuelLitres($deviceId, $position);
            if ($prev !== null) {
                $carryOver[$deviceId] = $prev;
            }
        }

        $result = $this->processBatch([$position], $carryOver);
        $litres = $result['carryOverLitres'][$deviceId] ?? null;

        return [
            'litres' => $litres,
            'updated' => $result['updated'] > 0,
        ];
    }

    private function lookupPreviousFuelLitres(int $deviceId, object $position): ?float
    {
        $fixtime = $position->fixtime ?? null;
        $positionId = (int) ($position->id ?? 0);
        if (! $fixtime || $positionId <= 0) {
            return null;
        }

        $devAttrs = $this->deviceAttrsCache[$deviceId] ?? [];

        $prev = DB::connection('pgsql')
            ->table('tc_positions')
            ->select('attributes')
            ->where('deviceid', $deviceId)
            ->where(function ($q) use ($fixtime, $positionId) {
                $q->where('fixtime', '<', $fixtime)
                    ->orWhere(function ($q2) use ($fixtime, $positionId) {
                        $q2->where('fixtime', '=', $fixtime)->where('id', '<', $positionId);
                    });
            })
            ->orderByDesc('fixtime')
            ->orderByDesc('id')
            ->limit(1)
            ->value('attributes');

        if ($prev === null) {
            return null;
        }

        return $this->fuelLitres($this->decodeAttributes($prev), $devAttrs);
    }

    /**
     * @param  array<int, float>  $updates  positionId => diff litres
     */
    public function persistRefillDiffBulk(array $updates): int
    {
        if ($updates === []) {
            return 0;
        }

        $attrKey = self::ATTR_KEY;
        $chunks = array_chunk($updates, 500, true);
        $total = 0;

        foreach ($chunks as $chunk) {
            $ids = [];
            $caseParts = [];
            $bindings = [];

            foreach ($chunk as $positionId => $diff) {
                $ids[] = (int) $positionId;
                $caseParts[] = 'WHEN ? THEN ?::numeric';
                $bindings[] = (int) $positionId;
                $bindings[] = (float) $diff;
            }

            if ($ids === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $caseSql = implode(' ', $caseParts);

            $sql = "
                UPDATE tc_positions
                SET attributes = jsonb_set(
                    COALESCE(attributes::jsonb, '{}'::jsonb),
                    '{".$attrKey."}',
                    to_jsonb((CASE id {$caseSql} END)),
                    true
                )
                WHERE id IN ({$placeholders})
            ";

            $total += DB::connection('pgsql')->update($sql, array_merge($bindings, $ids));
        }

        return $total;
    }

    /**
     * Litres from merged device + position attrs using telemetry.js formatFuel rules.
     */
    private function fuelLitres(array $posAttrs, array $devAttrs): ?float
    {
        $attrs = array_merge($devAttrs, $posAttrs);
        $cap = $this->num($attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? null);
        if (! $cap || $cap <= 0) {
            $cap = 50.0;
        }

        $fuel = $this->resolveFuelLikeFrontend($attrs, [
            'capacity' => $cap,
            'fuelTankCapacity' => $cap,
            'fuelAttr_key' => $devAttrs['fuelAttr_key'] ?? null,
            'fuelAttr' => $devAttrs['fuelAttr'] ?? null,
            'fuelReverse' => $devAttrs['fuelReverse'] ?? null,
        ]);

        if ($fuel === null) {
            return null;
        }

        if (($fuel['liters'] ?? null) !== null) {
            return (float) $fuel['liters'];
        }

        if (($fuel['percent'] ?? null) !== null) {
            return round((($cap * floatval($fuel['percent']) / 100.0) * 10)) / 10;
        }

        return null;
    }

    /**
     * Resolve fuel litres/percent from device + position attributes (same rules as telemetry.js).
     *
     * @return array{liters: ?float, percent: ?int}|null
     */
    public function fuelTelemetry(array $posAttrs, array $devAttrs): ?array
    {
        $attrs = array_merge($devAttrs, $posAttrs);
        $cap = $this->num($attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? null);
        if (! $cap || $cap <= 0) {
            $cap = 50.0;
        }

        $fuel = $this->resolveFuelLikeFrontend($attrs, [
            'capacity' => $cap,
            'fuelTankCapacity' => $cap,
            'fuelAttr_key' => $devAttrs['fuelAttr_key'] ?? null,
            'fuelAttr' => $devAttrs['fuelAttr'] ?? null,
            'fuelReverse' => $devAttrs['fuelReverse'] ?? null,
        ]);

        if ($fuel === null) {
            return null;
        }

        $percent = isset($fuel['percent']) ? (int) $fuel['percent'] : null;
        $liters = isset($fuel['liters']) ? (float) $fuel['liters'] : null;

        if ($percent === null && $liters !== null && $cap > 0) {
            $percent = max(0, min(100, (int) round(($liters / $cap) * 100)));
        }

        if ($percent === null && $liters === null) {
            return null;
        }

        return ['liters' => $liters, 'percent' => $percent];
    }

    /**
     * PHP port of resources/js/utils/telemetry.js formatFuel().
     *
     * @return array{key: ?string, liters: ?float, percent: ?int, raw: ?float}|null
     */
    private function resolveFuelLikeFrontend(array $attrs, array $ctx = []): ?array
    {
        $fuelAttrName = trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? ''));
        if (strtolower($fuelAttrName) === 'none') {
            return null;
        }

        $cap = $this->num($ctx['capacity'] ?? $ctx['fuelTankCapacity'] ?? $attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? null);
        $getV = fn (string $k) => $this->num($this->getAttrValue($attrs, $k));

        $emptyCal = $getV('fuelanalogempty') ?? $getV('fuelAnalogEmpty') ?? $getV('fuel_empty') ?? $getV('analog_empty') ?? $getV('analogEmpty') ?? $getV('fuelMin') ?? $getV('fuel_min');
        $fullCal = $getV('fuelanalogfull') ?? $getV('fuelAnalogFull') ?? $getV('fuel_full') ?? $getV('analog_full') ?? $getV('analogFull') ?? $getV('fuelMax') ?? $getV('fuel_max');
        $aScale = $getV('fuelanalogscale') ?? $getV('fuelAnalogScale') ?? $getV('analog_scale') ?? $getV('analogScale') ?? 1.0;
        $aOff = $getV('fuelanalogoffset') ?? $getV('fuelAnalogOffset') ?? $getV('analog_offset') ?? $getV('analogOffset') ?? 0.0;
        $fuelReverse = $this->isFuelReverseFlag($attrs['fuelReverse'] ?? $ctx['fuelReverse'] ?? false);

        $analogCompute = function (float $rawVal, string $rawKey) use ($emptyCal, $fullCal, $aScale, $aOff, $cap, $fuelReverse): ?array {
            $adj = ($rawVal * $aScale) + $aOff;
            if (! $cap || $cap <= 0) {
                return null;
            }
            $fuel = $this->analogFuelFromMv($adj, $cap, $emptyCal, $fullCal, $fuelReverse);
            if (! $fuel) {
                return null;
            }

            return [
                'pRes' => ['k' => $rawKey, 'v' => $fuel['percent']],
                'lRes' => ['k' => $rawKey, 'v' => $fuel['liters']],
            ];
        };

        $pref = trim((string) ($attrs['fuelAttr_key'] ?? $ctx['fuelAttr_key'] ?? ''));
        if ($pref !== '') {
            $val = $getV($pref);
            if ($val !== null && $val > -1) {
                $fuelAttrName = strtolower(trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? '')));
                $needsAnalogCal = $this->isIoKey($pref) && $this->isAnalogFuelAttr($attrs, $ctx) && $emptyCal !== null && $fullCal !== null;
                if ($needsAnalogCal) {
                    $a = $analogCompute($val, $pref);
                    if ($a && ($a['pRes'] || $a['lRes'])) {
                        return $this->mkFuel(
                            $a['lRes']['k'] ?? $a['pRes']['k'] ?? $pref,
                            $a['lRes']['v'] ?? null,
                            $a['pRes']['v'] ?? null,
                            $val
                        );
                    }

                    return $this->mkFuel($pref, 0.0, 0, $val);
                }

                $prefLower = strtolower($pref);
                $isCanScaled = (str_contains($prefLower, 'can') || in_array($prefLower, ['io84', '84'], true))
                    && ! str_contains($fuelAttrName, 'percent');
                $val = $val * ($isCanScaled ? 0.1 : 1.0);

                $l = null;
                $p = null;
                if ($this->isIoKey($pref)) {
                    if ($cap && $cap > 0 && $val >= 0) {
                        if ($this->isPercentFuelIoKey($pref) && $val <= 100) {
                            $p = (int) round($val);
                            $l = round(($cap * $p / 100) * 10) / 10;
                        } else {
                            $l = round($val * 10) / 10;
                            $p = max(0, min(100, (int) round(($l / $cap) * 100)));
                        }
                    }
                } elseif ($cap && $cap > 0 && $val >= 0 && $val <= 100) {
                    $p = (int) round($val);
                    $l = round(($cap * $p / 100) * 10) / 10;
                } else {
                    $l = round($val * 10) / 10;
                }

                return $this->mkFuel($pref, $l, $p);
            }

            return $this->mkFuel($pref, 0.0, 0);
        }

        $pRes = null;
        foreach (['fuelPercent', 'fuelLevel', 'fuel_percent', 'fuelpercentage', 'io89', '89', 'io48', '48'] as $k) {
            $v = $getV($k);
            if ($v !== null && $v > -1) {
                $pRes = ['k' => $k, 'v' => max(0, min(100, (int) round($v)))];
                break;
            }
        }

        $lRes = null;
        $raw = null;
        $rawKey = null;
        foreach (['canFuel', 'can_fuel', 'can_fuel_level', 'fuelLiter', 'fuelLiters', 'fuel', 'io84', '84'] as $k) {
            $v = $getV($k);
            if ($v === null || $v <= -1) {
                continue;
            }
            $kLower = strtolower($k);
            $isCan = str_contains($kLower, 'can') || in_array($kLower, ['io84', '84'], true);
            $lRes = ['k' => $k, 'v' => round(($v * ($isCan ? 0.1 : 1.0)) * 10) / 10];
            break;
        }

        if ($pRes === null && $lRes === null) {
            $rKeys = ['io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243', 'fuelRaw', 'analog1', 'analog2', 'analog3', 'adc1', 'adc2', 'adc3', 'adc'];
            $sum = 0.0;
            $count = 0;
            foreach ($rKeys as $k) {
                $v = $getV($k);
                if ($v !== null && $v > 0) {
                    $sum += $v;
                    $count++;
                    if ($rawKey === null) {
                        $rawKey = $k;
                    }
                }
            }
            if ($count > 0) {
                $raw = $sum / $count;
                if ($count > 1) {
                    $rawKey = 'analog_avg';
                }
            }

            if ($raw !== null) {
                $useAnalogCal = $this->isAnalogFuelAttr($attrs, $ctx) && $emptyCal !== null && $fullCal !== null;
                if ($useAnalogCal) {
                    $a = $analogCompute($raw, (string) $rawKey);
                    if ($a) {
                        $pRes = $a['pRes'];
                        $lRes = $a['lRes'];
                    }
                } elseif ($this->isPercentFuelIoKey($rawKey) && $cap && $cap > 0 && $raw <= 100) {
                    $pRes = ['k' => $rawKey, 'v' => (int) round($raw)];
                } elseif ($raw > 0) {
                    $lRes = ['k' => $rawKey, 'v' => round($raw * 10) / 10];
                }
            }
        }

        // No ignition filter — fuelRefillDiff uses the raw saved position fuel value.
        if ($cap && $cap > 0) {
            if ($pRes && ! $lRes) {
                $lRes = ['k' => $pRes['k'], 'v' => round(($cap * $pRes['v'] / 100) * 10) / 10];
            } elseif ($lRes && ! $pRes) {
                $pRes = ['k' => $lRes['k'], 'v' => max(0, min(100, (int) round(($lRes['v'] / $cap) * 100)))];
            } elseif ($pRes && $lRes && abs(($cap * $pRes['v'] / 100) - $lRes['v']) > 1) {
                $lRes = ['k' => $pRes['k'], 'v' => round(($cap * $pRes['v'] / 100) * 10) / 10];
            }
        }

        if ($lRes || $pRes || $raw !== null) {
            return $this->mkFuel(
                $lRes['k'] ?? $pRes['k'] ?? $rawKey,
                $lRes['v'] ?? null,
                $pRes['v'] ?? null,
                $raw
            );
        }

        return null;
    }

    /**
     * @return array{key: ?string, liters: ?float, percent: ?int, raw: ?float}
     */
    private function mkFuel(?string $key, ?float $liters, ?int $percent, ?float $raw = null): array
    {
        return [
            'key' => $key,
            'liters' => $liters,
            'percent' => $percent,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{liters: float, percent: int}|null
     */
    private function analogFuelFromMv(float $ai1, float $capacity, ?float $fuelMin, ?float $fuelMax, bool $isReverse): ?array
    {
        $cal = $this->resolveAnalogEmptyFull($fuelMin, $fuelMax, $isReverse);
        if ($cal === null || $capacity <= 0) {
            return null;
        }

        $empty = $cal['empty'];
        $full = $cal['full'];
        $range = $empty - $full;
        if ($range == 0.0) {
            return null;
        }

        $lo = min($empty, $full);
        $hi = max($empty, $full);
        $clamped = max($lo, min($hi, $ai1));

        $ratio = ($empty - $clamped) / $range;
        $ratio = max(0.0, min(1.0, $ratio));

        return [
            'liters' => round($capacity * $ratio, 1),
            'percent' => (int) round($ratio * 100),
        ];
    }

    /**
     * @return array{empty: float, full: float}|null
     */
    private function resolveAnalogEmptyFull(?float $fuelMin, ?float $fuelMax, bool $isReverse): ?array
    {
        if ($fuelMin === null || $fuelMax === null || $fuelMin === $fuelMax) {
            return null;
        }

        $minV = $fuelMin;
        $maxV = $fuelMax;
        if ($isReverse) {
            $minV = $fuelMax;
            $maxV = $fuelMin;
        }

        if ($maxV > $minV) {
            return ['empty' => $maxV, 'full' => $minV];
        }

        return ['empty' => $minV, 'full' => $maxV];
    }

    private function isFuelReverseFlag(mixed $v): bool
    {
        return $v === true || $v === 'true' || $v === 1 || $v === '1';
    }

    private function isAnalogFuelAttr(array $attrs, array $ctx = []): bool
    {
        $name = strtolower(trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? '')));

        return str_contains($name, 'analog');
    }

    private function isPercentFuelIoKey(?string $key): bool
    {
        $s = strtolower(trim((string) $key));

        return in_array($s, ['io89', '89', 'io48', '48'], true) || str_contains($s, 'percent');
    }

    private function isIoKey(?string $key): bool
    {
        $s = strtolower(trim((string) $key));

        return (bool) preg_match('/^io\d+$/', $s) || (bool) preg_match('/^\d+$/', $s);
    }

    private function getAttrValue(array $attrs, string $key): mixed
    {
        if (array_key_exists($key, $attrs)) {
            return $attrs[$key];
        }
        $kLower = strtolower($key);
        foreach ($attrs as $k => $v) {
            if (strtolower((string) $k) === $kLower) {
                return $v;
            }
        }

        return null;
    }

    private function existingRefillDiff(array $attrs): ?float
    {
        $v = $attrs[self::ATTR_KEY] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        $n = is_numeric($v) ? (float) $v : null;

        return ($n !== null && $n > 0) ? $n : null;
    }

    private function decodeAttributes(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            $n = (float) $v;

            return is_finite($n) ? $n : null;
        }

        return null;
    }
}
