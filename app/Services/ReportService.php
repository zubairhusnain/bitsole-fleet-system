<?php

namespace App\Services;

use App\Helpers\Curl;
use App\Models\Devices;
use App\Support\ClientDisconnect;
use App\Support\TelemetryFormat;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Class ReportService
 *
 * @method mixed fetchDailyTrips($request, $deviceIds)
 * @method mixed fetchDailyBreakdownMap($request, $deviceIds)
 * @method mixed fetchDailySummary($request, $deviceIds)
 * @method mixed fetchMonthlySummary($request, $deviceIds)
 * @method mixed fetchFleetSummary($request, $deviceIds)
 */
class ReportService
{
    use Curl;

    private function abortIfClientDisconnected(): void
    {
        ClientDisconnect::throwIfDisconnected();
    }

    private function parseJsonAttrs($raw): array
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

    private function getAttrValue(array $attrs, $key)
    {
        if ($key === null || $key === '') {
            return null;
        }
        if (array_key_exists($key, $attrs)) {
            return $attrs[$key];
        }
        $kLower = strtolower((string) $key);
        foreach ($attrs as $k => $v) {
            if (strtolower((string) $k) === $kLower) {
                return $v;
            }
        }

        return null;
    }

    private function num($v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return null;
            }
        }
        $n = null;
        if (is_int($v) || is_float($v)) {
            $n = floatval($v);
        } elseif (is_string($v)) {
            if (is_numeric($v)) {
                $n = floatval($v);
            } elseif (preg_match('/-?\d+(?:\.\d+)?/', $v, $m)) {
                $n = floatval($m[0]);
            }
        } elseif (is_numeric($v)) {
            $n = floatval($v);
        }

        return is_finite($n) ? $n : null;
    }

    private function isIoKey($k): bool
    {
        $s = strtolower(trim((string) $k));

        return (bool) preg_match('/^io\d+$/', $s) || (bool) preg_match('/^\d+$/', $s);
    }

    private function isAnalogFuelAttr(array $attrs, array $ctx = []): bool
    {
        $name = strtolower(trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? '')));

        return str_contains($name, 'analog');
    }

    private function isPercentFuelIoKey($key): bool
    {
        $s = strtolower(trim((string) $key));

        return in_array($s, ['io89', '89', 'io48', '48'], true) || str_contains($s, 'percent');
    }

    /**
     * Reverse swaps fuelMin/fuelMax; then max>min → EMPTY=max, FULL=min, else EMPTY=min, FULL=max.
     *
     * @return array{empty: float, full: float}|null
     */
    private function resolveAnalogEmptyFull(?float $fuelMin, ?float $fuelMax, bool $fuelReverse): ?array
    {
        if ($fuelMin === null || $fuelMax === null || $fuelMin === $fuelMax) {
            return null;
        }

        $minV = $fuelMin;
        $maxV = $fuelMax;
        if ($fuelReverse) {
            $minV = $fuelMax;
            $maxV = $fuelMin;
        }

        if ($maxV > $minV) {
            return ['empty' => $maxV, 'full' => $minV];
        }

        return ['empty' => $minV, 'full' => $maxV];
    }

    /**
     * LITRES = CAPACITY × (EMPTY − AI1) / (EMPTY − FULL); PERCENT = ratio × 100.
     *
     * @return array{liters: float, percent: int, empty: float, full: float, ai1: float, ratio: float}|null
     */
    private function analogFuelFromMv(float $adj, float $capacity, ?float $fuelMin, ?float $fullCal, bool $fuelReverse): ?array
    {
        $cal = $this->resolveAnalogEmptyFull($fuelMin, $fullCal, $fuelReverse);
        if ($cal === null || $capacity <= 0) {
            return null;
        }

        $emptyMv = $cal['empty'];
        $fullMv = $cal['full'];
        $range = $emptyMv - $fullMv;
        if ($range == 0.0) {
            return null;
        }

        $lo = min($emptyMv, $fullMv);
        $hi = max($emptyMv, $fullMv);
        $ai1 = max($lo, min($hi, $adj));

        $ratio = ($emptyMv - $ai1) / $range;
        $ratio = max(0.0, min(1.0, $ratio));

        $liters = round($capacity * $ratio, 1);
        $percent = (int) round($ratio * 100);

        return [
            'liters' => $liters,
            'percent' => $percent,
            'empty' => $emptyMv,
            'full' => $fullMv,
            'ai1' => $ai1,
            'ratio' => $ratio,
        ];
    }

    private function analogFuelPercentFromMv(float $adj, ?float $emptyCal, ?float $fullCal, bool $fuelReverse): ?int
    {
        $r = $this->analogFuelFromMv($adj, 100.0, $emptyCal, $fullCal, $fuelReverse);

        return $r ? $r['percent'] : null;
    }

    private function computeFuelTelemetry(array $rawAttrs, array $ctx = []): ?array
    {
        $attrs = $rawAttrs;
        $fuelAttrName = trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? ''));
        if (strtolower($fuelAttrName) === 'none') {
            $cap = $this->num($ctx['capacity'] ?? $ctx['fuelTankCapacity'] ?? $attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? $attrs['fueltankcapacity'] ?? null);

            return [
                'key' => null,
                'liters' => null,
                'percent' => null,
                'raw' => null,
                'capacity' => $cap,
                'display' => '-',
            ];
        }

        $cap = $this->num($ctx['capacity'] ?? $ctx['fuelTankCapacity'] ?? $attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? $attrs['fueltankcapacity'] ?? null);

        $getV = function ($k) use ($attrs) {
            return $this->num($this->getAttrValue($attrs, $k));
        };

        $emptyCal = $getV('fuelanalogempty') ?? $getV('fuelAnalogEmpty') ?? $getV('fuel_empty') ?? $getV('analog_empty') ?? $getV('analogEmpty') ?? $getV('fuelMin') ?? $getV('fuel_min');
        $fullCal = $getV('fuelanalogfull') ?? $getV('fuelAnalogFull') ?? $getV('fuel_full') ?? $getV('analog_full') ?? $getV('analogFull') ?? $getV('fuelMax') ?? $getV('fuel_max');
        $aScale = $getV('fuelanalogscale') ?? $getV('fuelAnalogScale') ?? $getV('analog_scale') ?? $getV('analogScale') ?? 1;
        $aOff = $getV('fuelanalogoffset') ?? $getV('fuelAnalogOffset') ?? $getV('analog_offset') ?? $getV('analogOffset') ?? 0;
        $fuelReverse = filter_var($attrs['fuelReverse'] ?? $ctx['fuelReverse'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $hasAnalogCal = (($emptyCal != null && $fullCal != null && $emptyCal !== $fullCal) || $aScale !== 1.0 || $aOff !== 0.0);

        $analogCompute = function ($rawVal, $rawKey) use ($emptyCal, $fullCal, $aScale, $aOff, $cap, $fuelReverse) {
            $rawNum = $this->num($rawVal);
            if ($rawNum == null) {
                return null;
            }
            $adj = ($rawNum * $aScale) + $aOff;

            $p = null;
            $l = null;
            if ($cap && $cap > 0) {
                $fuel = $this->analogFuelFromMv($adj, $cap, $emptyCal, $fullCal, $fuelReverse);
                if ($fuel) {
                    $p = $fuel['percent'];
                    $l = $fuel['liters'];
                }
            }

            return [
                'key' => $rawKey,
                'raw' => $rawNum,
                'percent' => $p,
                'liters' => $l,
                'capacity' => $cap,
            ];
        };

        $mk = function ($key, $l, $p, $raw = null) use ($cap) {
            return [
                'key' => $key,
                'liters' => $l,
                'percent' => $p,
                'raw' => $raw,
                'capacity' => $cap,
            ];
        };

        $pref = $attrs['fuelAttr_key'] ?? ($ctx['fuelAttr_key'] ?? null) ?? ($attrs['fuelAttr'] ?? null) ?? ($ctx['fuelAttr'] ?? null);

        if ($pref) {
            $prefStr = trim((string) $pref);
            $candidates = [$prefStr];
            if (preg_match('/^\d+$/', $prefStr)) {
                $candidates[] = 'io'.$prefStr;
                $candidates[] = 'io_'.$prefStr;
                $candidates[] = 'io-'.$prefStr;
            } elseif (preg_match('/^io(\d+)$/i', $prefStr, $m)) {
                $candidates[] = $m[1];
            }

            $val = null;
            $resolvedKey = null;
            foreach ($candidates as $ck) {
                $v = $getV($ck);
                if ($v !== null) {
                    $val = $v;
                    $resolvedKey = $ck;
                    break;
                }
            }

            if ($val !== null && $val !== -1.0) {
                $fuelAttrName = strtolower(trim((string) ($attrs['fuelAttr'] ?? $ctx['fuelAttr'] ?? '')));
                $isIo = $this->isIoKey($resolvedKey ?? $prefStr);

                $needsAnalogCal = $isIo && $this->isAnalogFuelAttr($attrs, $ctx) && $emptyCal !== null && $fullCal !== null;
                if ($needsAnalogCal) {
                    if ($val === 0.0 && $emptyCal !== null && $emptyCal > 0) {
                        return null;
                    }
                    $a = $analogCompute($val, $resolvedKey ?? $prefStr);
                    if ($a && ($a['liters'] !== null || $a['percent'] !== null)) {
                        return $mk($a['key'], $a['liters'], $a['percent'], $a['raw']);
                    }

                    return null;
                }

                if ($isIo && $this->isAnalogFuelAttr($attrs, $ctx)) {
                    return null;
                }

                $prefLower = strtolower((string) ($resolvedKey ?? $prefStr));
                $isCanScaled = (str_contains($prefLower, 'can') || in_array($prefLower, ['io84', '84'], true))
                    && ! str_contains($fuelAttrName, 'percent');
                $multiplier = $isCanScaled ? 0.1 : 1.0;
                $val = $val * $multiplier;

                $l = null;
                $p = null;
                $prefKey = $resolvedKey ?? $prefStr;
                if ($cap && $cap > 0 && $val >= 0) {
                    if ($this->isIoKey($prefKey) && $this->isPercentFuelIoKey($prefKey) && $val <= 100) {
                        $p = intval(round($val));
                        $l = round(($cap * $p / 100) * 10) / 10;
                    } elseif ($this->isIoKey($prefKey)) {
                        $l = round($val * 10) / 10;
                        $p = max(0, min(100, intval(round(($l / $cap) * 100))));
                    } elseif ($val <= 100) {
                        $p = intval(round($val));
                        $l = round(($cap * $p / 100) * 10) / 10;
                    } else {
                        $l = round($val * 10) / 10;
                    }
                } else {
                    $l = round($val * 10) / 10;
                }

                return $mk($prefKey, $l, $p, null);
            }

            return null;
        }

        $pKeys = ['fuelPercent', 'fuelLevel', 'fuel_percent', 'fuelpercentage', 'io89', '89', 'io48', '48'];
        $pRes = null;
        $pKey = null;
        foreach ($pKeys as $k) {
            $v = $getV($k);
            if ($v !== null && $v > -1) {
                $pRes = max(0, min(100, intval(round($v))));
                $pKey = $k;
                break;
            }
        }

        $lKeys = ['canFuel', 'can_fuel', 'can_fuel_level', 'fuelLiter', 'fuelLiters', 'fuel', 'io84', '84'];
        $lRes = null;
        $lKey = null;
        foreach ($lKeys as $k) {
            $v = $getV($k);
            if ($v === null) {
                continue;
            }
            if ($v <= -1) {
                continue;
            }
            $kLower = strtolower((string) $k);
            $isCan = str_contains($kLower, 'can') || in_array($kLower, ['io84', '84'], true);
            $multiplier = $isCan ? 0.1 : 1.0;
            $lRes = round(($v * $multiplier) * 10) / 10;
            $lKey = $k;
            break;
        }

        $raw = null;
        $rawKey = null;
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
                    $a = $analogCompute($raw, $rawKey);
                    if ($a) {
                        $pRes = $a['percent'];
                        $lRes = $a['liters'];
                        $pKey = $a['key'];
                        $lKey = $a['key'];
                    }
                } elseif ($this->isPercentFuelIoKey($rawKey) && $cap && $cap > 0 && $raw <= 100) {
                    $pRes = max(0, min(100, intval(round($raw))));
                    $pKey = $rawKey;
                } elseif ($raw > 0) {
                    $lRes = round($raw * 10) / 10;
                    $lKey = $rawKey;
                }
            }
        }

        if ($pRes !== null && $lRes === null) {
            $ign = $this->getAttrValue($attrs, 'ignition');
            $isOff = ($ign === false) || ($ign === 0) || (strtolower((string) $ign) === 'off') || ((string) $ign === '0');
            if ($isOff) {
                $pRes = null;
            }
        }

        if ($cap && $cap > 0) {
            if ($pRes !== null && $lRes === null) {
                $lRes = round(($cap * $pRes / 100) * 10) / 10;
                $lKey = $pKey;
            } elseif ($lRes !== null && $pRes === null) {
                $pRes = max(0, min(100, intval(round(($lRes / $cap) * 100))));
                $pKey = $lKey;
            } elseif ($pRes !== null && $lRes !== null) {
                $expectedL = ($cap * $pRes / 100);
                if (abs($expectedL - $lRes) > 1) {
                    $lRes = round($expectedL * 10) / 10;
                    $lKey = $pKey;
                }
            }
        }

        if ($lRes !== null || $pRes !== null || $raw !== null) {
            return $mk($lKey ?? $pKey ?? $rawKey, $lRes, $pRes, $raw);
        }

        return null;
    }

    /**
     * Litres from the same telemetry logic used by vehicle list / live tracking.
     */
    public function fuelLitresFromTelemetry(array $rawAttrs, array $ctx = []): ?float
    {
        $fuel = $this->computeFuelTelemetry($rawAttrs, $ctx);
        if (! $fuel) {
            return null;
        }
        if (($fuel['liters'] ?? null) !== null) {
            return (float) $fuel['liters'];
        }
        $cap = $this->num($ctx['capacity'] ?? $rawAttrs['fuelTankCapacity'] ?? null);
        if ($cap && $cap > 0 && ($fuel['percent'] ?? null) !== null) {
            return round((($cap * floatval($fuel['percent']) / 100.0) * 10)) / 10;
        }

        return null;
    }

    private function deviceHasFuelConfig(array $attrs): bool
    {
        $fuelAttr = trim((string) ($attrs['fuelAttr'] ?? ''));
        if (strtolower($fuelAttr) === 'none') {
            return false;
        }

        $fuelKey = trim((string) ($attrs['fuelAttr_key'] ?? ''));

        return $fuelKey !== '' || $fuelAttr !== '';
    }

    private function capFleetSummaryRefillStats(float &$litres, int &$count, float $tankCap): void
    {
        $cap = $tankCap > 0 ? $tankCap : 50.0;
        $maxLitres = $cap * 2.5;
        if ($litres > $maxLitres) {
            $litres = $maxLitres;
        }
        $maxCount = max(1, (int) ceil($litres / max(1.0, $cap * 0.45)));
        if ($count > $maxCount) {
            $count = $maxCount;
        }
    }

    private function tryRecordFleetRefill(
        float $fromLiters,
        float $toLiters,
        float $cap,
        float $refillMinDelta,
        int $minGapSec,
        ?int $posTs,
        ?int &$lastRefillTs,
        float &$refillLitres,
        int &$refillCount
    ): bool {
        $delta = $toLiters - $fromLiters;
        if ($delta < $refillMinDelta) {
            return false;
        }
        if ($lastRefillTs !== null && $posTs && ($posTs - $lastRefillTs) < $minGapSec) {
            return false;
        }
        $refillLitres += min($delta, $cap * 0.95);
        $refillCount++;
        $lastRefillTs = $posTs ?: $lastRefillTs;

        return true;
    }

    /** @return array<int, array> */
    private function deviceTelemetryAttrsById(array $deviceIds): array
    {
        $map = [];
        if (empty($deviceIds)) {
            return $map;
        }
        $rows = \App\Models\TcDevice::whereIn('id', $deviceIds)->get(['id', 'attributes']);
        foreach ($rows as $r) {
            $map[intval($r->id)] = $this->parseJsonAttrs($r->attributes);
        }

        return $map;
    }

    /**
     * Trip distance (metres) from ignition on/off pairs.
     * Uses device-configured odometer IO when available; otherwise totalDistance with sanity caps.
     *
     * @return array<int, float>
     */
    private function distanceMetersByDeviceFromIgnitionTrips(array $deviceIds, string $fromIso, string $toIso): array
    {
        $out = [];
        foreach ($deviceIds as $id) {
            $out[intval($id)] = 0.0;
        }
        if (empty($deviceIds)) {
            return $out;
        }

        $deviceAttrsById = $this->deviceTelemetryAttrsById($deviceIds);
        $idsStr = implode(',', array_map('intval', $deviceIds));
        try {
            $events = DB::connection('pgsql')->select("
                SELECT
                    e.deviceid,
                    e.type,
                    e.eventtime,
                    p.attributes as position_attrs
                FROM tc_events e
                LEFT JOIN tc_positions p ON e.positionid = p.id
                WHERE e.deviceid IN ($idsStr)
                  AND e.eventtime BETWEEN ? AND ?
                  AND e.type IN ('ignitionOn', 'ignitionOff')
                ORDER BY e.deviceid, e.eventtime ASC
            ", [$fromIso, $toIso]);
        } catch (\Throwable $e) {
            Log::error('distanceMetersByDeviceFromIgnitionTrips failed: '.$e->getMessage());

            return $out;
        }

        $tripStarts = [];
        foreach ($events as $event) {
            $did = intval($event->deviceid);
            $devAttrs = $deviceAttrsById[$did] ?? [];
            $posAttrs = $this->parseJsonAttrs($event->position_attrs ?? '{}');
            $reading = [
                'eventtime' => $event->eventtime,
                'resolved' => TelemetryFormat::resolveOdometerFromPosition($posAttrs, $devAttrs),
                'totalM' => TelemetryFormat::totalDistanceMetersFromPositionAttrs($posAttrs),
            ];

            if ($event->type === 'ignitionOn') {
                $tripStarts[$did] = $reading;
            } elseif ($event->type === 'ignitionOff' && isset($tripStarts[$did])) {
                $start = $tripStarts[$did];
                $duration = strtotime($event->eventtime) - strtotime($start['eventtime']);
                $dist = TelemetryFormat::tripDistanceMeters(
                    $start['resolved'],
                    $reading['resolved'],
                    $start['totalM'],
                    $reading['totalM'],
                    $duration,
                    TelemetryFormat::hasConfiguredOdometer($devAttrs)
                );
                if ($duration > 120 || $dist > 100) {
                    $out[$did] = ($out[$did] ?? 0) + $dist;
                }
                unset($tripStarts[$did]);
            }
        }

        return $out;
    }

    /**
     * Sum trip distances (metres) by calendar day from fetchTripsDb rows.
     *
     * @return array<string, float>
     */
    private function tripDistanceMetersByDay(array $trips): array
    {
        $byDay = [];
        foreach ($trips as $trip) {
            $start = $trip['startTime'] ?? null;
            if (! is_string($start) || $start === '') {
                continue;
            }
            $ts = strtotime($start);
            if ($ts === false) {
                continue;
            }
            $day = date('Y-m-d', $ts);
            $byDay[$day] = ($byDay[$day] ?? 0.0) + floatval($trip['distance'] ?? 0);
        }

        return $byDay;
    }

    private function bulkFuelUsageLitresTelemetry(array $deviceIds, string $fromIso, string $toIso, string $bucket = 'none', bool $onlyConfiguredDevices = false, bool $trackRefills = false): array
    {
        if (empty($deviceIds)) {
            return [
                'total' => 0.0,
                'byDevice' => [],
                'byBucket' => [],
                'byDeviceBucket' => [],
                'refillsByDevice' => [],
            ];
        }

        $deviceAttrsById = $this->deviceTelemetryAttrsById($deviceIds);

        if ($onlyConfiguredDevices) {
            $deviceIds = array_values(array_filter(
                array_map('intval', $deviceIds),
                fn ($id) => $this->deviceHasFuelConfig($deviceAttrsById[$id] ?? [])
            ));
            if (empty($deviceIds)) {
                return [
                    'total' => 0.0,
                    'byDevice' => [],
                    'byBucket' => [],
                    'byDeviceBucket' => [],
                    'refillsByDevice' => [],
                ];
            }
        }

        $query = DB::connection('pgsql')
            ->table('tc_positions')
            ->select('deviceid', 'fixtime', 'attributes')
            ->whereIn('deviceid', $deviceIds)
            ->whereBetween('fixtime', [$fromIso, $toIso])
            ->orderBy('deviceid')
            ->orderBy('fixtime');

        $total = 0.0;
        $byDevice = [];
        $byBucket = [];
        $byDeviceBucket = [];
        $refillsByDevice = [];

        $stableDeviceId = null;
        $curBucketKey = null;
        $prevLiters = null;
        $segMin = null;
        $segMax = null;
        $deviceConsumed = 0.0;
        $bucketConsumed = [];
        $refillLitres = 0.0;
        $refillCount = 0;
        $lastRefillTs = null;
        $lastSampleTs = null;
        $capForDevice = 50.0;
        $sampleIntervalSec = 300;
        $refillMinGapSec = 3600;

        $flushSegment = function () use (&$deviceConsumed, &$bucketConsumed, &$segMin, &$segMax, &$curBucketKey, &$capForDevice, $bucket) {
            if ($segMin === null || $segMax === null) {
                $segMin = null;
                $segMax = null;

                return;
            }
            $used = min($segMax - $segMin, $capForDevice * 0.85);
            if ($used > 0) {
                $deviceConsumed += $used;
                if ($bucket !== 'none' && $curBucketKey !== null) {
                    $bucketConsumed[$curBucketKey] = ($bucketConsumed[$curBucketKey] ?? 0) + $used;
                }
            }
            $segMin = null;
            $segMax = null;
        };

        $finalize = function () use (
            &$total,
            &$byDevice,
            &$byBucket,
            &$byDeviceBucket,
            &$refillsByDevice,
            &$stableDeviceId,
            &$deviceConsumed,
            &$bucketConsumed,
            &$refillLitres,
            &$refillCount,
            &$capForDevice,
            $bucket,
            $trackRefills,
            $flushSegment
        ) {
            $flushSegment();

            if ($stableDeviceId !== null && $deviceConsumed > 0) {
                $total += $deviceConsumed;
                $byDevice[$stableDeviceId] = ($byDevice[$stableDeviceId] ?? 0) + $deviceConsumed;

                if ($bucket !== 'none') {
                    foreach ($bucketConsumed as $bk => $litres) {
                        if ($litres <= 0) {
                            continue;
                        }
                        $byBucket[$bk] = ($byBucket[$bk] ?? 0) + $litres;
                        $byDeviceBucket[$stableDeviceId] ??= [];
                        $byDeviceBucket[$stableDeviceId][$bk] = ($byDeviceBucket[$stableDeviceId][$bk] ?? 0) + $litres;
                    }
                }
            }

            if ($trackRefills && $stableDeviceId !== null) {
                $litres = $refillLitres;
                $count = $refillCount;
                $this->capFleetSummaryRefillStats($litres, $count, $capForDevice);
                $refillsByDevice[$stableDeviceId] = ['litres' => round($litres, 2), 'count' => $count];
            }
        };

        foreach ($query->cursor() as $row) {
            $did = intval($row->deviceid);
            if ($stableDeviceId === null || $stableDeviceId !== $did) {
                $finalize();
                $stableDeviceId = $did;
                $curBucketKey = null;
                $prevLiters = null;
                $segMin = null;
                $segMax = null;
                $deviceConsumed = 0.0;
                $bucketConsumed = [];
                $refillLitres = 0.0;
                $refillCount = 0;
                $lastRefillTs = null;
                $lastSampleTs = null;
            }

            $posTs = is_string($row->fixtime) ? strtotime($row->fixtime) : strtotime((string) $row->fixtime);
            if ($posTs && $lastSampleTs !== null && ($posTs - $lastSampleTs) < $sampleIntervalSec) {
                continue;
            }

            $posAttrs = $this->parseJsonAttrs($row->attributes);
            $devAttrs = $deviceAttrsById[$did] ?? [];
            $merged = array_merge($devAttrs, $posAttrs);

            $cap = $this->num($merged['fuelTankCapacity'] ?? $merged['FuelTankCapacity'] ?? $merged['fueltankcapacity'] ?? null);
            $capForCalc = ($cap && $cap > 0) ? $cap : 50;
            $capForDevice = $capForCalc;
            $fuelAttrName = strtolower(trim((string) ($merged['fuelAttr'] ?? '')));
            $isAnalog = $fuelAttrName !== '' && str_contains($fuelAttrName, 'analog');
            $isPercentCan = str_contains($fuelAttrName, 'percentage') || str_contains($fuelAttrName, 'can');

            $noiseThreshold = $isAnalog ? max(0.8, $capForCalc * 0.01) : max(0.2, $capForCalc * 0.0025);
            $refillMinDelta = $isAnalog
                ? max(4.0, $capForCalc * 0.08)
                : ($isPercentCan ? max(8.0, $capForCalc * 0.12) : max(10.0, $capForCalc * 0.15));
            $stepJump = $isPercentCan ? ($capForCalc * 0.18) : ($capForCalc * 0.35);

            $fuel = $this->computeFuelTelemetry($merged, [
                'capacity' => $capForCalc,
                'fuelAttr_key' => $devAttrs['fuelAttr_key'] ?? null,
                'fuelAttr' => $devAttrs['fuelAttr'] ?? null,
            ]);
            $curLiters = $fuel ? ($fuel['liters'] ?? null) : null;
            if ($curLiters === null && $fuel && (($fuel['percent'] ?? null) !== null)) {
                $curLiters = round((($capForCalc * floatval($fuel['percent']) / 100.0) * 10)) / 10;
            }

            if ($curLiters === null || $curLiters < 0) {
                continue;
            }

            $b = null;
            if ($bucket !== 'none') {
                if (! $posTs) {
                    continue;
                }
                $b = $bucket === 'day' ? date('Y-m-d', $posTs) : ($bucket === 'month' ? date('Y-m', $posTs) : null);
                if ($b === null) {
                    continue;
                }
                if ($curBucketKey === null) {
                    $curBucketKey = $b;
                } elseif ($curBucketKey !== $b) {
                    $finalize();
                    $stableDeviceId = $did;
                    $curBucketKey = $b;
                    $prevLiters = null;
                    $segMin = null;
                    $segMax = null;
                    $deviceConsumed = 0.0;
                    $bucketConsumed = [];
                    $refillLitres = 0.0;
                    $refillCount = 0;
                    $lastRefillTs = null;
                    $lastSampleTs = null;
                }
            }

            if ($posTs) {
                $lastSampleTs = $posTs;
            }

            if ($prevLiters !== null && abs($curLiters - $prevLiters) > $stepJump) {
                if ($trackRefills && $curLiters > $prevLiters) {
                    $this->tryRecordFleetRefill(
                        $prevLiters,
                        $curLiters,
                        $capForCalc,
                        $refillMinDelta,
                        $refillMinGapSec,
                        $posTs,
                        $lastRefillTs,
                        $refillLitres,
                        $refillCount
                    );
                }
                $flushSegment();
                $prevLiters = $curLiters;
                $segMin = $curLiters;
                $segMax = $curLiters;

                continue;
            }

            if ($trackRefills && $prevLiters !== null && $curLiters >= ($prevLiters + $refillMinDelta)) {
                if ($this->tryRecordFleetRefill(
                    $prevLiters,
                    $curLiters,
                    $capForCalc,
                    $refillMinDelta,
                    $refillMinGapSec,
                    $posTs,
                    $lastRefillTs,
                    $refillLitres,
                    $refillCount
                )) {
                    $flushSegment();
                    $prevLiters = $curLiters;
                    $segMin = $curLiters;
                    $segMax = $curLiters;

                    continue;
                }
            }

            if ($segMin === null || $segMax === null) {
                $segMin = $curLiters;
                $segMax = $curLiters;
                $prevLiters = $curLiters;

                continue;
            }

            if ($curLiters <= ($segMin - $noiseThreshold)) {
                $segMin = $curLiters;
            } elseif ($curLiters >= ($segMax + $noiseThreshold)) {
                $segMax = $curLiters;
            }

            $prevLiters = $curLiters;
        }

        $finalize();

        $roundBuckets = function (&$arr) {
            foreach ($arr as $k => $v) {
                $arr[$k] = round(floatval($v), 2);
            }
        };

        $roundBuckets($byDevice);
        $roundBuckets($byBucket);
        foreach ($byDeviceBucket as $did => $buckets) {
            $roundBuckets($byDeviceBucket[$did]);
        }

        return [
            'total' => round($total, 2),
            'byDevice' => $byDevice,
            'byBucket' => $byBucket,
            'byDeviceBucket' => $byDeviceBucket,
            'refillsByDevice' => $refillsByDevice,
        ];
    }

    public function travelHistory($request)
    {
        return $this->fetchTravelHistoryDb($request);
    }

        public function fetchFuelEntriesDetailed($request, $deviceIds)
    {
        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::error("fetchFuelEntriesDetailed: Date parse error: " . $e->getMessage());
            return [
                'entries' => [],
                'yearly' => [],
            ];
        }

        $idsStr = implode(',', array_map('intval', $deviceIds));
        if (empty($idsStr)) {
            return [
                'entries' => [],
                'yearly' => [],
            ];
        }

        $devices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');

        $rows = [];
        try {
            $rows = DB::connection('pgsql')->select("
                WITH pos_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        COALESCE(
                            NULLIF(CAST(attributes AS json)->>'io89', ''),
                            NULLIF(CAST(attributes AS json)->>'CAN_FuelPercentage_89', ''),
                            NULLIF(CAST(attributes AS json)->>'io48', ''),
                            NULLIF(CAST(attributes AS json)->>'io16', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelLevel', '')
                        ) AS raw_fuel
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                ),
                clean_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        CAST(raw_fuel AS FLOAT) as fuel_level
                    FROM pos_data
                    WHERE raw_fuel ~ '^[0-9]+(\\.[0-9]+)?$'
                      AND CAST(raw_fuel AS FLOAT) BETWEEN 0 AND 100
                ),
                pos_with_prev AS (
                    SELECT
                        deviceid,
                        fixtime,
                        fuel_level,
                        LAG(fuel_level) OVER (PARTITION BY deviceid ORDER BY fixtime) as prev_fuel_level
                    FROM clean_data
                )
                SELECT
                    deviceid,
                    fixtime,
                    prev_fuel_level,
                    fuel_level,
                    CASE WHEN prev_fuel_level > fuel_level THEN prev_fuel_level - fuel_level ELSE 0 END AS drop_pct,
                    CASE WHEN fuel_level > prev_fuel_level + 5 THEN fuel_level - prev_fuel_level ELSE 0 END AS increase_pct
                FROM pos_with_prev
                WHERE prev_fuel_level IS NOT NULL
                  AND (
                    prev_fuel_level > fuel_level
                    OR fuel_level > prev_fuel_level + 5
                  )
                ORDER BY deviceid, fixtime
            ", [$fromIso, $toIso]);
        } catch (\Throwable $e) {
            Log::error('fetchFuelEntriesDetailed query failed: ' . $e->getMessage());
            return [
                'entries' => [],
                'yearly' => [],
            ];
        }

        $entries = [];
        $yearly = [];

        foreach ($rows as $row) {
            $deviceId = (int)($row->deviceid ?? 0);
            $dev = $devices->get($deviceId);
            $deviceName = $dev->name ?? 'Unknown';

            $attrs = $dev && $dev->attributes ? (is_string($dev->attributes) ? (json_decode($dev->attributes, true) ?: []) : (is_array($dev->attributes) ? $dev->attributes : [])) : [];
            $tankCapacity = (float)($attrs['fuelTankCapacity'] ?? 50);
            if ($tankCapacity <= 0) {
                $tankCapacity = 50;
            }

            $dropPct = (float)($row->drop_pct ?? 0);
            $increasePct = (float)($row->increase_pct ?? 0);

            $dropLitres = ($dropPct / 100.0) * $tankCapacity;
            $increaseLitres = ($increasePct / 100.0) * $tankCapacity;

            try {
                $ts = \Carbon\Carbon::parse($row->fixtime);
            } catch (\Exception $e) {
                continue;
            }

            $type = 'none';
            if ($dropPct > 0 && $increasePct <= 0) {
                $type = 'drop';
            } elseif ($increasePct > 0 && $dropPct <= 0) {
                $type = 'refill';
            } elseif ($dropPct > 0 || $increasePct > 0) {
                $type = 'mixed';
            }

            $startPctVal = round((float)($row->prev_fuel_level ?? 0), 1);
            $endPctVal = round((float)($row->fuel_level ?? 0), 1);
            $endLitres = ($endPctVal / 100.0) * $tankCapacity;

            $entry = [
                'deviceId' => $deviceId,
                'vehicleName' => $deviceName,
                'time' => $ts->toIso8601String(),
                'eventDate' => $ts->toDateString(),
                'eventTime' => $ts->format('H:i:s'),
                'type' => $type,
                'startPct' => $startPctVal,
                'endPct' => $endPctVal,
                'dropPct' => round($dropPct, 1),
                'dropLitres' => round($dropLitres, 2),
                'increasePct' => round($increasePct, 1),
                'increaseLitres' => round($increaseLitres, 2),
                'tankCapacity' => round($tankCapacity, 1),
                'endLitres' => round($endLitres, 2),
            ];

            $entries[] = $entry;

            $monthKey = $ts->format('Y-m');
            if (!isset($yearly[$monthKey])) {
                $yearly[$monthKey] = [
                    'year' => (int)$ts->format('Y'),
                    'month' => (int)$ts->format('m'),
                    'label' => $ts->format('M Y'),
                    'spentLitres' => 0.0,
                    'refillLitres' => 0.0,
                    'events' => 0,
                ];
            }

            $yearly[$monthKey]['spentLitres'] += $dropLitres;
            $yearly[$monthKey]['refillLitres'] += $increaseLitres;
            $yearly[$monthKey]['events'] += 1;
        }

        usort($entries, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        usort($yearly, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return $a['month'] <=> $b['month'];
            }
            return $a['year'] <=> $b['year'];
        });

        return [
            'entries' => $entries,
            'yearly' => array_values($yearly),
        ];
    }


    public function fetchTravelHistoryDb($request)
    {
        $deviceId = $request->device_id;
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $trips = $this->fetchTripsDb($deviceId, $from, $to);

        return $trips;
    }

    public function fetchTripsDb($deviceId, $from, $to)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $devAttrs = $this->deviceTelemetryAttrsById([intval($deviceId)])[intval($deviceId)] ?? [];
        $configuredOdometer = TelemetryFormat::hasConfiguredOdometer($devAttrs);

        // 1. Fetch Ignition Events with Position Data
        $events = DB::connection('pgsql')->select("
            SELECT
                e.id, e.type, e.eventtime,
                p.latitude, p.longitude, p.address,
                p.attributes as position_attrs
            FROM tc_events e
            LEFT JOIN tc_positions p ON e.positionid = p.id
            WHERE e.deviceid = ?
              AND e.eventtime BETWEEN ? AND ?
              AND e.type IN ('ignitionOn', 'ignitionOff')
            ORDER BY e.eventtime ASC
        ", [$deviceId, $from, $to]);

        $trips = [];
        $currentStart = null;

        foreach ($events as $event) {
            $posAttrs = $this->parseJsonAttrs($event->position_attrs ?? '{}');
            $reading = [
                'event' => $event,
                'resolved' => TelemetryFormat::resolveOdometerFromPosition($posAttrs, $devAttrs),
                'totalM' => TelemetryFormat::totalDistanceMetersFromPositionAttrs($posAttrs),
            ];

            if ($event->type === 'ignitionOn') {
                $currentStart = $reading;
            } elseif ($event->type === 'ignitionOff') {
                if ($currentStart !== null) {
                    $startEvent = $currentStart['event'];
                    $dist = TelemetryFormat::tripDistanceMeters(
                        $currentStart['resolved'],
                        $reading['resolved'],
                        $currentStart['totalM'],
                        $reading['totalM'],
                        max(0, strtotime($event->eventtime) - strtotime($startEvent->eventtime)),
                        $configuredOdometer
                    );

                    // Duration
                    $startTime = strtotime($startEvent->eventtime);
                    $endTime = strtotime($event->eventtime);
                    $duration = $endTime - $startTime;

                    // Filter noise (e.g. < 100m or < 2 min)
                    if ($duration > 120 || $dist > 100) {
                        // Fetch Max Speed - Optimized to use aggregation
                        $maxSpeed = 0;
                        try {
                            // Only query max speed if trip is valid
                            $ms = DB::connection('pgsql')->selectOne('
                                SELECT MAX(speed) as max_speed
                                FROM tc_positions
                                WHERE deviceid = ?
                                  AND fixtime BETWEEN ? AND ?
                            ', [$deviceId, $startEvent->eventtime, $event->eventtime]);
                            $maxSpeed = $ms ? $ms->max_speed : 0;
                        } catch (\Throwable $t) {
                        }

                        $avgSpeed = ($duration > 0) ? ($dist / $duration) * 1.94384 : 0; // m/s to knots

                        $trips[] = [
                            'deviceId' => $deviceId,
                            'deviceName' => '', // Fill if needed
                            'distance' => $dist, // meters
                            'averageSpeed' => $avgSpeed, // knots
                            'maxSpeed' => $maxSpeed, // knots
                            'spentFuel' => 0,
                            'startOdometer' => $currentStart['resolved']['meters'] ?? null,
                            'endOdometer' => $reading['resolved']['meters'] ?? null,
                            'startTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($startEvent->eventtime)),
                            'endTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($event->eventtime)),
                            'startPositionId' => 0,
                            'endPositionId' => 0,
                            'startLat' => $startEvent->latitude,
                            'startLon' => $startEvent->longitude,
                            'endLat' => $event->latitude,
                            'endLon' => $event->longitude,
                            'startAddress' => $startEvent->address,
                            'endAddress' => $event->address,
                            'duration' => $duration * 1000, // ms
                            'driverUniqueId' => '',
                            'driverName' => '',
                        ];
                    }
                    $currentStart = null;
                }
            }
        }

        return $trips;
    }

    public function travelHistoryOld($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $id = $request->device_id;
        $data = 'id='.$id;
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date ?? date('Y-01-01 00:00:00')));
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date ?? date('Y-01-01 00:00:00')));

        $data = 'deviceId='.$id.'&from='.$from.'&to='.$to;
        $tripsRaw = static::curl('/api/reports/trips?'.$data, 'GET', $sessionId, '', ['Content-Type: application/json', 'Accept: application/json'], 120);
        // dd($tripsRaw);
        $trips = [];
        if ($tripsRaw->responseCode == 200 && isset($tripsRaw->responseCode)) {
            // $tripsRaw = json_decode($tripsRaw->response);
            $trips = json_decode($tripsRaw->response);
            // $trips = is_array($tmp) ? $tmp : [];
        }

        // dd($trips);
        return $trips;
    }

    public function vehicleRanking($request)
    {
        // Redirect to DB implementation
        return $this->fetchVehicleRankingDb($request);
    }

    public function fetchVehicleRankingDb($request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $vehicleIds = $request->vehicle_ids ?? [];

        // 1. Resolve Device IDs
        if (empty($vehicleIds)) {
            $accessible = Devices::accessibleByUser($request->user())->pluck('device_id')->all();
            $vehicleIds = $accessible;
        } else {
            $vehicleIds = is_string($vehicleIds) ? explode(',', $vehicleIds) : $vehicleIds;
        }

        if (empty($vehicleIds)) {
            return [];
        }

        $idsStr = implode(',', array_map('intval', $vehicleIds));

        $distanceByDevice = $this->distanceMetersByDeviceFromIgnitionTrips($vehicleIds, $from, $to);

        // io253 harsh-event logic (Teltonika / custom devices):
        // Some trackers encode harsh events in tc_positions.attributes.io253.
        //
        // Mapping used:
        // - io253 = 1 => harshAcceleration
        // - io253 = 2 => harshBraking
        // - io253 = 3 (and sometimes 4 on certain firmwares) => harshCornering
        //
        // Rule:
        // - io253 counts are pre-aggregated from tc_positions in this stats query.
        // - We only use io253 counts for HA/HB/HC when tc_events has no harsh data for that device.
        // - When io253 has no data, the existing tc_events-based counts remain the source of truth.
        $stats = [];
        try {
            $statsData = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    MIN(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'hours', ''), '0') AS FLOAT)) as min_hours,
                    MAX(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'hours', ''), '0') AS FLOAT)) as max_hours,
                    SUM(CASE WHEN NULLIF(CAST(attributes AS json)->>'io253', '') = '1' THEN 1 ELSE 0 END) as io253_count_ha,
                    SUM(CASE WHEN NULLIF(CAST(attributes AS json)->>'io253', '') = '2' THEN 1 ELSE 0 END) as io253_count_hb,
                    SUM(CASE WHEN NULLIF(CAST(attributes AS json)->>'io253', '') IN ('3','4') THEN 1 ELSE 0 END) as io253_count_hc,
                    SUM(CASE WHEN NULLIF(CAST(attributes AS json)->>'io253', '') IS NOT NULL AND NULLIF(CAST(attributes AS json)->>'io253', '') <> '0' THEN 1 ELSE 0 END) as io253_count_total
                FROM tc_positions
                WHERE deviceid IN ($idsStr)
                  AND fixtime BETWEEN ? AND ?
                GROUP BY deviceid
            ", [$from, $to]);

            foreach ($statsData as $s) {
                $stats[$s->deviceid] = $s;
            }
        } catch (\Throwable $e) {
            Log::error('fetchVehicleRankingDb stats query failed: '.$e->getMessage());
        }

        // 3. Fetch Events (Penalties) from tc_events
        // We need counts of: harshAcceleration, harshBraking, harshCornering, deviceOverspeed
        // In DB, type='deviceOverspeed'.
        // Harsh events might be type='alarm' AND attributes->>'alarm' IN (...)
        // OR type IN ('harshAcceleration', etc) depending on Traccar version.
        // We will check both for safety.

        $eventsData = [];
        try {
            $eventsQuery = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    SUM(CASE WHEN type = 'deviceOverspeed' THEN 1 ELSE 0 END) as count_overspeed,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardAcceleration' OR type = 'harshAcceleration' THEN 1
                        ELSE 0
                    END) as count_ha,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardBraking' OR type = 'harshBraking' THEN 1
                        ELSE 0
                    END) as count_hb,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardCornering' OR type = 'harshCornering' THEN 1
                        ELSE 0
                    END) as count_hc
                FROM tc_events
                WHERE deviceid IN ($idsStr)
                  AND eventtime BETWEEN ? AND ?
                  AND (
                    type = 'deviceOverspeed'
                    OR type IN ('harshAcceleration', 'harshBraking', 'harshCornering')
                    OR (type = 'alarm' AND (CAST(attributes AS json)->>'alarm') IN ('hardAcceleration', 'hardBraking', 'hardCornering'))
                  )
                GROUP BY deviceid
            ", [$from, $to]);

            foreach ($eventsQuery as $e) {
                $eventsData[$e->deviceid] = $e;
            }
        } catch (\Throwable $e) {
            Log::error('fetchVehicleRankingDb events query failed: '.$e->getMessage());
        }

        // 4. Fetch Device Details
        $tcDevices = \App\Models\TcDevice::whereIn('id', $vehicleIds)->get()->keyBy('id');

        // 5. Build Result
        $rows = collect($vehicleIds)->map(function ($deviceId) use ($stats, $eventsData, $tcDevices, $distanceByDevice) {
            $tcDev = $tcDevices->get($deviceId);
            $stat = $stats[$deviceId] ?? null;
            $evt = $eventsData[$deviceId] ?? null;

            $distanceM = floatval($distanceByDevice[intval($deviceId)] ?? 0);
            // Engine hours in DB are usually in milliseconds
            $minH = $stat ? $stat->min_hours : 0;
            $maxH = $stat ? $stat->max_hours : 0;
            $engineHoursMs = max(0, $maxH - $minH);

            // Counts
            // Use io253 only when tc_events has no harsh data for this device.
            $evtHa = $evt ? (int) ($evt->count_ha ?? 0) : 0;
            $evtHb = $evt ? (int) ($evt->count_hb ?? 0) : 0;
            $evtHc = $evt ? (int) ($evt->count_hc ?? 0) : 0;
            $hasHarshFromEvents = ($evtHa + $evtHb + $evtHc) > 0;

            $ioTotal = $stat ? (int) ($stat->io253_count_total ?? 0) : 0;
            $useIo253 = ! $hasHarshFromEvents && $ioTotal > 0;
            $ha = $useIo253 ? (int) ($stat->io253_count_ha ?? 0) : $evtHa;
            $hb = $useIo253 ? (int) ($stat->io253_count_hb ?? 0) : $evtHb;
            $hc = $useIo253 ? (int) ($stat->io253_count_hc ?? 0) : $evtHc;
            $sv = $evt ? $evt->count_overspeed : 0;

            $points = 100 - ($ha * 5) - ($hb * 5) - ($hc * 5) - ($sv * 10);
            $behaviourScore = (int) ($ha + $hb + $hc + $sv);
            $attrs = $tcDev && $tcDev->attributes
                ? (is_string($tcDev->attributes)
                    ? (json_decode($tcDev->attributes, true) ?: [])
                    : (is_array($tcDev->attributes) ? $tcDev->attributes : []))
                : [];
            $type = trim((string) data_get($attrs, 'type', ''));
            $trackerModel = trim((string) (
                data_get($attrs, 'trackerModel')
                ?? data_get($tcDev, 'model')
                ?? ''
            ));
            $typeModel = $type !== '' ? trim($type.' - '.$trackerModel) : $trackerModel;
            if ($typeModel === '') {
                $fallback = $tcDev ? ($tcDev->category ?? 'N/A') : 'N/A';
                $typeModel = is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : 'N/A';
            }

            return [
                'vehicleId' => $tcDev->name ?? 'Unknown',
                'typeModel' => $typeModel,
                'distance' => round($distanceM / 1000, 2).' KM',
                'duration' => $this->formatDurationHms($engineHoursMs),
                'totalHA' => (int) $ha,
                'totalHB' => (int) $hb,
                'totalHC' => (int) $hc,
                'totalSV' => (int) $sv,
                'points' => $points,
                'percentage' => max(0, min(100, $points)),
                'behaviourScore' => $behaviourScore,
            ];
        });

        // 6. Sorting Logic (copied from original)
        $sortBy = 'points';
        $sortDesc = true;

        if ($request->has('type')) {
            switch ($request->type) {
                case 'percentage':
                    $sortBy = 'percentage';
                    $sortDesc = true;
                    break;
                case 'points':
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
                case 'behaviour':
                    $sortBy = 'behaviourScore';
                    $sortDesc = true;
                    break;
            }
        }

        $sortedRows = $sortDesc ? $rows->sortByDesc($sortBy) : $rows->sortBy($sortBy);

        // Tie-breaker
        if (($sortBy === 'points' && $sortDesc === true) && empty($request->vehicle_ids)) {
            $sortedRows = $sortedRows->sort(function ($a, $b) {
                $cmp = ($b['points'] <=> $a['points']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $a['vehicleId'], (string) $b['vehicleId']);
            });
        }

        $singleSelected = false;
        if ($request->has('vehicle_ids')) {
            $ids = $request->vehicle_ids;
            if (is_array($ids)) {
                $singleSelected = count($ids) === 1;
            } elseif (is_string($ids)) {
                $singleSelected = count(array_filter(explode(',', $ids))) === 1;
            }
        }

        if ($singleSelected) {
            return $sortedRows->values()->map(function ($row) {
                $row['rank'] = 1;

                return $row;
            });
        } else {
            $allSame = $sortedRows->pluck('points')->unique()->count() === 1;
            if ($allSame) {
                return $sortedRows->values()->map(function ($row) {
                    $row['rank'] = 1;

                    return $row;
                });
            } else {
                return $sortedRows->values()->map(function ($row, $index) {
                    $row['rank'] = $index + 1;

                    return $row;
                });
            }
        }
    }

    public function vehicleRankingOld($request)
    {
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date.' 00:00:00'));
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date.' 23:59:59'));

        $vehicleIds = $request->vehicle_ids ?? [];

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        // Query string
        $query = 'from='.$from.'&to='.$to;

        if (empty($vehicleIds)) {
            $accessible = Devices::accessibleByUser($request->user())->pluck('device_id')->all();
            if (! empty($accessible)) {
                $query .= collect($accessible)->map(fn ($vid) => '&deviceId='.$vid)->implode('');
            }
        } else {
            $vehicleIds = is_string($vehicleIds) ? explode(',', $vehicleIds) : $vehicleIds;
            if (is_array($vehicleIds)) {
                $query .= collect($vehicleIds)->map(fn ($vid) => '&deviceId='.$vid)->implode('');
            }
        }

        $eventsQuery = $query;

        Log::info('VehicleRanking Service Request', ['query' => $query]);

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';

        // Parallel Requests
        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $sessionId, $query, $eventsQuery) {
            $reqHeaders = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Cookie' => $sessionId,
            ];
            // Filter events to reduce payload size
            $eventsQueryFiltered = $eventsQuery.'&type=alarm&type=deviceOverspeed';

            return [
                $pool->as('summary')->withHeaders($reqHeaders)->get($baseUrl.'/api/reports/summary?'.$query),
                $pool->as('events')->withHeaders($reqHeaders)->get($baseUrl.'/api/reports/events?'.$eventsQueryFiltered),
            ];
        });

        // 1. Summary
        $summaries = [];
        if ($responses['summary']->ok()) {
            $summaries = $responses['summary']->json();
        } else {
            Log::error('VehicleRanking Service Summary Failed', ['status' => $responses['summary']->status(), 'body' => $responses['summary']->body()]);
        }

        // 2. Events
        $events = [];
        if ($responses['events']->ok()) {
            $events = $responses['events']->json();
        } else {
            Log::error('VehicleRanking Service Events Failed', ['status' => $responses['events']->status(), 'body' => $responses['events']->body()]);
        }
        // dd('  summaries ', $summaries,$eventsQuery,$events);
        // Fetch local device details for Model/Type
        $deviceIds = collect($summaries)->pluck('deviceId');
        $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');

        $eventsByDevice = collect($events)->groupBy('deviceId');

        // Process Data using Map
        $rows = collect($summaries)->map(function ($sum) use ($eventsByDevice, $tcDevices) {
            $deviceId = $sum['deviceId'];
            $evs = $eventsByDevice->get($deviceId, collect([]));
            $tcDev = $tcDevices->get($deviceId);

            $distanceM = $sum['distance'] ?? 0;
            $engineHoursMs = $sum['engineHours'] ?? 0;

            // Counts
            $ha = $evs->filter(fn ($e) => ($e['attributes']['alarm'] ?? null) === 'hardAcceleration')->count();
            $hb = $evs->filter(fn ($e) => ($e['attributes']['alarm'] ?? null) === 'hardBraking')->count();
            $hc = $evs->filter(fn ($e) => ($e['attributes']['alarm'] ?? null) === 'hardCornering')->count();
            $sv = $evs->where('type', 'deviceOverspeed')->count();

            $points = 100 - ($ha * 5) - ($hb * 5) - ($hc * 5) - ($sv * 10);
            $attrs = $tcDev && $tcDev->attributes
                ? (is_string($tcDev->attributes)
                    ? (json_decode($tcDev->attributes, true) ?: [])
                    : (is_array($tcDev->attributes) ? $tcDev->attributes : []))
                : [];
            $type = trim((string) data_get($attrs, 'type', ''));
            $trackerModel = trim((string) (
                data_get($attrs, 'trackerModel')
                ?? data_get($attrs, 'deviceModel')
                ?? data_get($attrs, 'gpsModel')
                ?? data_get($attrs, 'teltonikaModel')
                ?? data_get($tcDev, 'model')
                ?? ''
            ));
            $typeModel = $type !== '' ? trim($type.' - '.$trackerModel) : $trackerModel;
            if ($typeModel === '') {
                $fallback = $tcDev ? ($tcDev->category ?? 'N/A') : 'N/A';
                $typeModel = is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : 'N/A';
            }

            return [
                'vehicleId' => $sum['deviceName'] ?? 'Unknown',
                'typeModel' => $typeModel,
                'distance' => round($distanceM / 1000, 2).' KM',
                'duration' => $this->formatDurationHms($engineHoursMs),
                'totalHA' => $ha ?: 0,
                'totalHB' => $hb ?: 0,
                'totalHC' => $hc ?: 0,
                'totalSV' => $sv ?: 0,
                'points' => $points,
                'percentage' => max(0, min(100, $points)),
            ];
        });

        // Sorting Logic based on request 'type'
        $sortBy = 'points';
        $sortDesc = true;

        if ($request->has('type')) {
            switch ($request->type) {
                case 'percentage':
                    $sortBy = 'percentage';
                    $sortDesc = true;
                    break;
                case 'points':
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
                case 'behaviour':
                    // For behaviour, we might want to see who has the most penalties?
                    // Let's stick to points DESC (Best -> Worst) as default,
                    // or if user wants "Problematic" first, we'd use ASC.
                    // Assuming standard ranking (Best First):
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
            }
        }

        $sortedRows = $sortDesc ? $rows->sortByDesc($sortBy) : $rows->sortBy($sortBy);

        // If listing all devices or sorting by points, enforce points-based rank with stable tie-breaker
        if (empty($vehicleIds) || ($sortBy === 'points' && $sortDesc === true)) {
            $sortedRows = $rows->sort(function ($a, $b) {
                $cmp = ($b['points'] <=> $a['points']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) $a['vehicleId'], (string) $b['vehicleId']);
            });
        }

        $singleSelected = false;
        if ($request->has('vehicle_ids')) {
            $ids = $request->vehicle_ids;
            if (is_array($ids)) {
                $singleSelected = count($ids) === 1;
            } elseif (is_string($ids)) {
                $singleSelected = count(array_filter(explode(',', $ids))) === 1;
            }
        }

        if ($singleSelected) {
            return $sortedRows->values()->map(function ($row) {
                $row['rank'] = 1;

                return $row;
            });
        } else {
            $allSame = $sortedRows->pluck('points')->unique()->count() === 1;
            if ($allSame) {
                return $sortedRows->values()->map(function ($row) {
                    $row['rank'] = 1;

                    return $row;
                });
            } else {
                return $sortedRows->values()->map(function ($row, $index) {
                    $row['rank'] = $index + 1;

                    return $row;
                });
            }
        }
    }

    private function formatDurationHms($ms)
    {
        $seconds = floor($ms / 1000);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        return sprintf('%dh %dm %ds', $h, $m, $s);
    }

    public function yearlyReportDashboard($request)
    {
        return $this->yearlyReportDashboardDb($request);
    }

    public function yearlyReportDashboardDb($request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $deviceId = $request->device_id;
        $from = $request->from_date ? \Carbon\Carbon::parse($request->from_date)->startOfDay() : \Carbon\Carbon::now()->startOfYear();
        $to = $request->to_date ? \Carbon\Carbon::parse($request->to_date)->endOfDay() : \Carbon\Carbon::now()->endOfYear();

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        // Use existing fetchTripsDb
        $trips = $this->fetchTripsDb($deviceId, $fromStr, $toStr);

        // Month buckets
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyDistance = array_fill_keys($months, 0);
        $monthlyFuel = array_fill_keys($months, 0);
        $monthlySpeedData = array_fill_keys($months, ['total' => 0, 'count' => 0]);

        foreach ($trips as $trip) {
            $monthKey = $months[date('n', strtotime($trip['startTime'])) - 1];

            // Distance (m -> km)
            $distKm = round($trip['distance'] / 1000, 1);
            $monthlyDistance[$monthKey] += $distKm;

            // Fuel
            $monthlyFuel[$monthKey] += $trip['spentFuel'] ?? 0;

            // Speed (knots -> km/h)
            $avgSpeedKnots = $trip['averageSpeed'];
            if ($avgSpeedKnots <= 162) {
                $monthlySpeedData[$monthKey]['total'] += $avgSpeedKnots * 1.852;
                $monthlySpeedData[$monthKey]['count'] += 1;
            }
        }

        // Compute averages
        $monthlyAvgSpeed = collect($months)->mapWithKeys(function ($m) use ($monthlySpeedData) {
            $total = $monthlySpeedData[$m]['total'];
            $cnt = $monthlySpeedData[$m]['count'] ?: 1;

            return [$m => round($total / $cnt, 1)];
        })->all();

        // Build Chart
        $chart = [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Distance (km)',
                    'data' => array_values($monthlyDistance),
                    'yAxisID' => 'y1',
                    'backgroundColor' => '#007bff',
                    'borderColor' => '#007bff',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Fuel (L)',
                    'data' => array_values($monthlyFuel),
                    'yAxisID' => 'y2',
                    'backgroundColor' => '#28a745',
                    'borderColor' => '#28a745',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Avg Speed (km/h)',
                    'data' => array_values($monthlyAvgSpeed),
                    'yAxisID' => 'y3',
                    'backgroundColor' => '#ffc107',
                    'borderColor' => '#ffc107',
                    'type' => 'line',
                    'fill' => false,
                ],
            ],
        ];

        $totals = [
            'totalDistanceKm' => array_sum($monthlyDistance),
            'totalFuelL' => array_sum($monthlyFuel),
            'avgSpeedKph' => round(array_sum($monthlyAvgSpeed) / count($monthlyAvgSpeed), 1),
        ];

        return [
            'chart' => $chart,
            'raw' => [
                'distance_km' => array_values($monthlyDistance),
                'fuel_litres' => array_values($monthlyFuel),
                'avg_speed_kph' => array_values($monthlyAvgSpeed),
            ],
            'totals' => $totals,
            'from' => $from->format('Y-m-d\TH:i:s\Z'),
            'to' => $to->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function yearlyReportDashboardOld($request)
    {
        // ------------------------------------------------------------------ 1) Session, device, dates
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $deviceId = $request->device_id;

        $fromIso = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date ?? date('Y-01-01 00:00:00')));
        $toIso = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date ?? date('Y-12-31 23:59:59')));

        $query = "deviceId={$deviceId}&from={$fromIso}&to={$toIso}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // ------------------------------------------------------------------ 2) Call tracking server APIs
        $tripsRaw = static::curl("/api/reports/trips?$query", 'GET', $sessionId, '', $headers);
        $summaryRaw = static::curl("/api/reports/summary?$query", 'GET', $sessionId, '', $headers);

        // Gracefully decode
        $trips = [];
        if ($tripsRaw && isset($tripsRaw->response)) {
            $tmp = json_decode($tripsRaw->response, true);
            $trips = is_array($tmp) ? $tmp : [];
        }

        $summary = [];
        if ($summaryRaw && isset($summaryRaw->response)) {
            $tmp = json_decode($summaryRaw->response, true);
            $summary = is_array($tmp) ? $tmp : [];
        }

        // ------------------------------------------------------------------ 3) Month buckets
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyDistance = array_fill_keys($months, 0);
        $monthlyFuel = array_fill_keys($months, 0);
        $monthlySpeedData = array_fill_keys($months, ['total' => 0, 'count' => 0]);

        // ------------------------------------------------------------------ 4) Aggregate TRIPS (distance, fuel, avg speed)
        $aggregated = collect($trips)->reduce(function ($acc, $trip) use ($months) {
            if (empty($trip['startTime'])) {
                return $acc;
            }

            $monthKey = $months[date('n', strtotime($trip['startTime'])) - 1];

            // Distance (m → km)
            if (isset($trip['distance'])) {
                $acc['distance'][$monthKey] += round($trip['distance'] / 1000, 1);
            }

            // Fuel spent
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
            if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                $acc['fuel'][$monthKey] += round($startFuel - $endFuel, 1);
            }

            // Average speed (knots → km/h)
            // Filter out unrealistic speeds (> 162 knots approx 300 km/h)
            if (isset($trip['averageSpeed']) && $trip['averageSpeed'] <= 162) {
                $acc['speed'][$monthKey]['total'] += $trip['averageSpeed'] * 1.852;
                $acc['speed'][$monthKey]['count'] += 1;
            }

            return $acc;
        }, [
            'distance' => $monthlyDistance,
            'fuel' => $monthlyFuel,
            'speed' => $monthlySpeedData,
        ]);

        $monthlyDistance = $aggregated['distance'];
        $monthlyFuel = $aggregated['fuel'];
        $monthlySpeedData = $aggregated['speed'];

        // Compute monthly avg speed
        $monthlyAvgSpeed = collect($months)->mapWithKeys(function ($m) use ($monthlySpeedData) {
            $total = $monthlySpeedData[$m]['total'];
            $cnt = $monthlySpeedData[$m]['count'] ?: 1;

            return [$m => round($total / $cnt, 1)];
        })->all();

        // ------------------------------------------------------------------ 5) Build Chart.js‑like payload
        $chart = [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Distance (km)',
                    'data' => array_values($monthlyDistance),
                    'yAxisID' => 'y1',
                    'backgroundColor' => '#007bff',
                    'borderColor' => '#007bff',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Fuel (L)',
                    'data' => array_values($monthlyFuel),
                    'yAxisID' => 'y2',
                    'backgroundColor' => '#28a745',
                    'borderColor' => '#28a745',
                    'type' => 'bar',
                ],
                [
                    'label' => 'Avg Speed (km/h)',
                    'data' => array_values($monthlyAvgSpeed),
                    'yAxisID' => 'y3',
                    'backgroundColor' => '#ffc107',
                    'borderColor' => '#ffc107',
                    'type' => 'line',
                    'fill' => false,
                ],
            ],
        ];

        // ------------------------------------------------------------------ 6) Totals
        $totals = [
            'totalDistanceKm' => array_sum($monthlyDistance),
            'totalFuelL' => array_sum($monthlyFuel),
            'avgSpeedKph' => round(array_sum($monthlyAvgSpeed) / count($monthlyAvgSpeed), 1),
        ];

        // ------------------------------------------------------------------ 7) Return JSON (or plain array)
        return [
            'chart' => $chart,
            'raw' => [
                'distance_km' => array_values($monthlyDistance),
                'fuel_litres' => array_values($monthlyFuel),
                'avg_speed_kph' => array_values($monthlyAvgSpeed),
            ],
            'totals' => $totals,
            'from' => $fromIso,
            'to' => $toIso,
        ];
    }

    public function report_summary($request)
    {
        return $this->report_summaryDb($request);
    }

    public function report_summaryDb($request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $deviceId = $request->device_id;
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = \Carbon\Carbon::parse($toStr)->format('Y-m-d H:i:s');

        // 1. Fetch Trips
        $trips = $this->fetchTripsDb($deviceId, $from, $to);

        // 2. Calculate Stops
        $stops = [];
        usort($trips, fn ($a, $b) => strcmp($a['startTime'], $b['startTime']));
        for ($i = 0; $i < count($trips) - 1; $i++) {
            $currentTrip = $trips[$i];
            $nextTrip = $trips[$i + 1];
            $stopStart = $currentTrip['endTime'];
            $stopEnd = $nextTrip['startTime'];
            $duration = strtotime($stopEnd) - strtotime($stopStart);
            if ($duration > 180) {
                $stops[] = ['duration' => $duration * 1000];
            }
        }

        // 3. Fetch Events
        $eventTypesRaw = $request->event_types ?? 'harshBraking,harshAcceleration,overspeed';
        $eventTypes = explode(',', $eventTypesRaw);

        $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $events = DB::connection('pgsql')->select("
            SELECT type, count(*) as count
            FROM tc_events
            WHERE deviceid = ?
              AND eventtime BETWEEN ? AND ?
              AND type IN ($placeholders)
            GROUP BY type
        ", array_merge([$deviceId, $from, $to], $eventTypes));

        $eventCounts = [];
        foreach ($events as $e) {
            $eventCounts[$e->type] = $e->count;
        }

        // 4. Calculate Summary Metrics
        $totalDistance = collect($trips)->sum('distance'); // meters
        $maxSpeed = collect($trips)->max('maxSpeed') ?? 0;
        if ($maxSpeed > 162) {
            $maxSpeed = 0;
        }
        $avgSpeed = collect($trips)->avg('averageSpeed') ?? 0;
        if ($avgSpeed > 162) {
            $avgSpeed = 0;
        }

        $engineHoursMs = collect($trips)->sum('duration'); // ms

        $deviceName = DB::connection('pgsql')->table('tc_devices')->where('id', $deviceId)->value('name');

        return [
            [
                'deviceName' => $deviceName ?? '',
                'distance_km' => round($totalDistance / 1000, 2),
                'spentFuel_litres' => 0, // Placeholder
                'avgFuel_l_per_100km' => 0, // Placeholder
                'engineHours' => round($engineHoursMs / 3600000, 2),
                'maxSpeed_kph' => round($maxSpeed * 1.852, 1),
                'avgSpeed_kph' => round($avgSpeed * 1.852, 1),
                'tripCount' => count($trips),
                'stopCount' => count($stops),
                'idleTime_minutes' => round(collect($stops)->sum('duration') / 60000, 1),
                'harshBraking' => $eventCounts['harshBraking'] ?? 0,
                'harshAcceleration' => $eventCounts['harshAcceleration'] ?? 0,
                'overspeedEvents' => ($eventCounts['overspeed'] ?? 0) + ($eventCounts['deviceOverspeed'] ?? 0),
            ],
        ];
    }

    public function report_summaryOld($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $deviceId = $request->device_id;

        // Format the timestamps
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        // If it looks like a simple date (YYYY-MM-DD), append end of day time
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        // Allow filtering events to reduce payload; default to harsh + overspeed
        $eventTypes = trim((string) ($request->event_types ?? 'harshBraking,harshAcceleration,overspeed'));
        if ($eventTypes === '') {
            $eventTypes = 'harshBraking,harshAcceleration,overspeed';
        }
        $queryString = "deviceId={$deviceId}&from={$from}&to={$to}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // Cache key per device and window; short TTL as data is near-real-time
        $cacheKey = sprintf('report_summary:%s:%s:%s:%s', $deviceId, $from, $to, $eventTypes);

        return Cache::remember($cacheKey, now()->addSeconds(120), function () use ($sessionId, $queryString, $headers, $eventTypes) {
            // Trips Report
            $tripsResponse = static::curl("/api/reports/trips?$queryString", 'GET', $sessionId, '', $headers);
            $trips = json_decode($tripsResponse->response ?? '[]');

            // Summary Report
            $summaryResponse = static::curl("/api/reports/summary?$queryString", 'GET', $sessionId, '', $headers);
            $summary = json_decode($summaryResponse->response ?? '[]');

            // Events (filtered types to reduce processing)
            $eventsResponse = static::curl("/api/reports/events?$queryString&type=".urlencode($eventTypes), 'GET', $sessionId, '', $headers);
            $events = json_decode($eventsResponse->response ?? '[]');

            // Stops Report
            $stopsResponse = static::curl("/api/reports/stops?$queryString", 'GET', $sessionId, '', $headers);
            $stops = json_decode($stopsResponse->response ?? '[]');

            // Result formatting
            $reportData = collect($summary)->map(function ($item) use ($trips, $events, $stops) {
                return [
                    'deviceName' => $item->deviceName ?? '',
                    'distance_km' => round(($item->distance ?? 0) / 1000, 2),
                    'spentFuel_litres' => round(optional($item->spentFuel)->value ?? 0, 1),
                    'avgFuel_l_per_100km' => $item->averageFuel ?? 0,
                    'engineHours' => round($item->engineHours ?? 0, 2),
                    'maxSpeed_kph' => collect($trips)->pluck('maxSpeed')->max(),
                    'avgSpeed_kph' => collect($trips)->avg('averageSpeed'),
                    'tripCount' => count($trips),
                    'stopCount' => count($stops),
                    'idleTime_minutes' => round(array_sum(array_map(fn ($s) => $s->duration ?? 0, $stops)) / 60000, 1),
                    'harshBraking' => count(array_filter($events, fn ($e) => $e->type === 'harshBraking')),
                    'harshAcceleration' => count(array_filter($events, fn ($e) => $e->type === 'harshAcceleration')),
                    'overspeedEvents' => count(array_filter($events, fn ($e) => $e->type === 'overspeed')),
                ];
            });

            return $reportData;
        });
    }

    public function fetchFleetSummary($request, $deviceIds)
    {
        return $this->fetchFleetSummaryDb($request, $deviceIds);
    }

    public function fetchFleetSummaryOld($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');

        // Format the timestamps
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        // If it looks like a simple date (YYYY-MM-DD), append end of day time
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        // Calculate days for averaging
        $diff = strtotime($request->to_date) - strtotime($request->from_date);
        $days = max(1, round($diff / (60 * 60 * 24)));

        // Build query string with multiple deviceId params
        // We will chunk requests to avoid URL length limits and improve parallelism
        $commonQuery = "from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            Log::error('ReportService: Tracking server host URL is not configured.');

            return [];
        }

        $eventTypes = 'harshBraking,harshAcceleration,overspeed,fuelIncrease';

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Chunk size of 20 devices per request group
        $chunks = array_chunk($deviceIds, 20);

        try {
            // Execute requests in parallel using HTTP Pool for all chunks
            $responses = Http::pool(function (Pool $pool) use ($chunks, $baseUrl, $headers, $commonQuery, $eventTypes) {
                $poolRequests = [];
                foreach ($chunks as $index => $chunkIds) {
                    $deviceQuery = collect($chunkIds)->map(function ($id) {
                        return "deviceId={$id}";
                    })->implode('&');
                    $fullQuery = "{$deviceQuery}&{$commonQuery}";

                    $poolRequests[] = $pool->as("summary_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$fullQuery}");
                    $poolRequests[] = $pool->as("stops_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}");
                    $poolRequests[] = $pool->as("events_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}&type=".urlencode($eventTypes));
                }

                return $poolRequests;
            });
        } catch (\Exception $e) {
            Log::error('ReportService: Failed to fetch fleet summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }

        // Aggregate results
        $allSummary = [];
        $allStops = [];
        $allEvents = [];

        foreach ($chunks as $index => $chunkIds) {
            $summaryRes = $responses["summary_{$index}"] ?? null;
            $stopsRes = $responses["stops_{$index}"] ?? null;
            $eventsRes = $responses["events_{$index}"] ?? null;

            if ($summaryRes instanceof \Illuminate\Http\Client\Response) {
                if ($summaryRes->ok()) {
                    $allSummary = array_merge($allSummary, $summaryRes->json());
                } else {
                    Log::error("ReportService API Error [summary_{$index}]", ['status' => $summaryRes->status(), 'body' => $summaryRes->body()]);
                }
            }

            if ($stopsRes instanceof \Illuminate\Http\Client\Response && $stopsRes->ok()) {
                $allStops = array_merge($allStops, $stopsRes->json());
            }

            if ($eventsRes instanceof \Illuminate\Http\Client\Response && $eventsRes->ok()) {
                $allEvents = array_merge($allEvents, $eventsRes->json());
            }
        }

        // Group data by deviceId
        $stopsByDevice = collect($allStops)->groupBy('deviceId');
        $eventsByDevice = collect($allEvents)->groupBy('deviceId');

        // Process summary
        $reportData = collect($allSummary)->map(function ($item) use ($stopsByDevice, $eventsByDevice, $days) {
            $deviceId = $item['deviceId'];
            $deviceStops = $stopsByDevice->get($deviceId, collect([]));
            $deviceEvents = $eventsByDevice->get($deviceId, collect([]));

            // Calculations
            $distTotalKm = round(($item['distance'] ?? 0) / 1000, 2);
            $distAvg = round($distTotalKm / $days, 2);

            $engineHoursMs = $item['engineHours'] ?? 0;
            $durTotalHours = floor($engineHoursMs / 3600000);
            $durTotalMinutes = floor(($engineHoursMs % 3600000) / 60000);
            $durTotalStr = "{$durTotalHours}h {$durTotalMinutes}m";

            $durAvgHours = $days > 0 ? floor(($engineHoursMs / $days) / 3600000) : 0;
            $durAvgMinutes = $days > 0 ? floor((($engineHoursMs / $days) % 3600000) / 60000) : 0;
            $durAvgStr = "{$durAvgHours}h {$durAvgMinutes}m";

            $idleMs = $deviceStops->sum('duration');
            $idleTotalHours = floor($idleMs / 3600000);
            $idleTotalMinutes = floor(($idleMs % 3600000) / 60000);
            $idleTotalStr = "{$idleTotalHours}h {$idleTotalMinutes}m";

            $idleAvgHours = $days > 0 ? floor(($idleMs / $days) / 3600000) : 0;
            $idleAvgMinutes = $days > 0 ? floor((($idleMs / $days) % 3600000) / 60000) : 0;
            $idleAvgStr = "{$idleAvgHours}h {$idleAvgMinutes}m";

            // Utilisation: (Engine Hours / (24 * days * 3600000)) * 100 ?? Or just active time
            $totalPossibleMs = $days * 24 * 60 * 60 * 1000;
            $utilPct = $totalPossibleMs > 0 ? round(($engineHoursMs / $totalPossibleMs) * 100, 1) : 0;

            // Fuel
            $spentFuel = round(optional($item['spentFuel'] ?? null)['value'] ?? 0, 2);
            $avgLitresPerDay = round($spentFuel / $days, 2);

            $avgKmL = $item['averageFuel'] ?? 0; // Traccar sends L/100km usually. If so, KM/L = 100 / L_per_100km
            // But if 'averageFuel' represents consumption, we need to check usage.
            // Let's assume standard Traccar which is often configurable.
            // If it's 0, calculate manually: Distance / Fuel
            if ($avgKmL == 0 && $spentFuel > 0) {
                $avgKmL = round($distTotalKm / $spentFuel, 2);
            }

            // Refills
            $refills = $deviceEvents->where('type', 'fuelIncrease');
            $refillTotal = $refills->sum(function ($refill) {
                return $refill['attributes']['amount'] ?? 0;
            });
            $refillCount = $refills->count();

            $rawAvgSpeed = $item['averageSpeed'] ?? 0;
            // Sanity check: ignore unrealistic speeds (e.g. > 300 km/h approx 162 knots)
            if ($rawAvgSpeed > 162) {
                $rawAvgSpeed = 0;
            }
            $avgSpeed = round($rawAvgSpeed * 1.852, 1);

            return [
                'key' => $deviceId,
                'vehicleId' => $deviceId,
                'vehicleName' => $item['deviceName'],
                'distTotal' => $distTotalKm,
                'distAvg' => $distAvg,
                'durTotal' => $durTotalStr,
                'durAvg' => $durAvgStr,
                'idleTotal' => $idleTotalStr,
                'idleAvg' => $idleAvgStr,
                'util' => $utilPct.'%',
                'avgLitres' => $avgLitresPerDay,
                'avgKmL' => $avgKmL,
                'fuelRefill' => round($refillTotal, 1).' L',
                'fuelRefillFreq' => $refillCount,
                'fuelConsumption' => round($spentFuel, 1).' L',
                'speed' => $avgSpeed.' km/h',
            ];
        });

        return $reportData->values();
    }

    public function fetchDailyTrips($request, $deviceIds)
    {
        return $this->fetchDailyTripsDb($request, $deviceIds);
    }

    public function fetchDailyTripsDb($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $allTrips = [];
        $allStops = [];

        foreach ($deviceIds as $deviceId) {
            $this->abortIfClientDisconnected();
            $trips = $this->fetchTripsDb($deviceId, $from, $to);
            $allTrips = array_merge($allTrips, $trips);

            // Calculate Stops from Trips
            // Sort trips by startTime
            usort($trips, fn ($a, $b) => strcmp($a['startTime'], $b['startTime']));

            for ($i = 0; $i < count($trips) - 1; $i++) {
                $currentTrip = $trips[$i];
                $nextTrip = $trips[$i + 1];

                $stopStart = $currentTrip['endTime'];
                $stopEnd = $nextTrip['startTime'];

                $startTs = strtotime($stopStart);
                $endTs = strtotime($stopEnd);
                $duration = $endTs - $startTs;

                if ($duration > 180) { // Filter short stops < 3 mins
                    $allStops[] = [
                        'deviceId' => $deviceId,
                        'startTime' => $stopStart,
                        'endTime' => $stopEnd,
                        'duration' => $duration * 1000,
                        'address' => $currentTrip['endAddress'] ?? 'N/A', // Stop location is end of previous trip
                        'lat' => $currentTrip['endLat'],
                        'lon' => $currentTrip['endLon'],
                    ];
                }
            }
        }

        // Format Rows (Trips)
        $rows = collect($allTrips)->map(function ($trip, $index) {
            return [
                'key' => $index + 1,
                'date' => date('d/m/Y', strtotime($trip['startTime'])),
                'startTime' => date('h:i A', strtotime($trip['startTime'])),
                'startTimeIso' => $trip['startTime'],
                'startLocation' => $trip['startAddress'] ?? 'N/A',
                'endTime' => date('h:i A', strtotime($trip['endTime'])),
                'endTimeIso' => $trip['endTime'],
                'endLocation' => $trip['endAddress'] ?? 'N/A',
                'distance' => round(($trip['distance'] ?? 0) / 1000, 2).' KM',
                'distance_m' => $trip['distance'] ?? 0,
                'duration_ms' => $trip['duration'] ?? 0,
            ];
        });

        // Format Stops
        $stopsFormatted = collect($allStops)->map(function ($stop) {
            return [
                'startTime' => date('h:i A', strtotime($stop['startTime'])),
                'startTimeIso' => $stop['startTime'],
                'endTime' => date('h:i A', strtotime($stop['endTime'])),
                'endTimeIso' => $stop['endTime'],
                'duration_ms' => $stop['duration'] ?? 0,
                'address' => $stop['address'] ?? 'N/A',
            ];
        });

        // Summary
        $totalDistance = collect($allTrips)->sum('distance');
        $totalDuration = collect($allTrips)->sum('duration');
        $idleSecondsByDevice = $this->idleSecondsByDevice($deviceIds, $from, $to);
        $totalIdle = $idleSecondsByDevice->sum(function ($r) {
            return isset($r->idle_seconds) ? floatval($r->idle_seconds) * 1000 : 0;
        });
        $maxSpeed = collect($allTrips)->max('maxSpeed') ?? 0;
        if ($maxSpeed > 162) {
            $maxSpeed = 0;
        } // Sanity check

        $fuelAgg = $this->bulkFuelUsageLitresTelemetry($deviceIds, $from, $to, 'none');
        $totalFuelLitres = floatval($fuelAgg['total'] ?? 0);
        $avgKmL = ($totalFuelLitres > 0) ? round(($totalDistance / 1000) / $totalFuelLitres, 2) : 0;

        return [
            'rows' => $rows,
            'stops' => $stopsFormatted,
            'summary' => [
                'totalDistance' => $totalDistance,
                'totalDuration' => $totalDuration,
                'totalIdle' => $totalIdle,
                'totalFuel' => $totalFuelLitres,
                'avgKmL' => $avgKmL,
                'maxSpeed' => $maxSpeed * 1.852,
            ],
        ];
    }

    public function fetchDailyTripsOld($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $deviceQuery = collect($deviceIds)->map(function ($id) {
            return "deviceId={$id}";
        })->implode('&');
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            Log::error('ReportService: Tracking server host URL is not configured.');

            return [
                'rows' => [],
                'summary' => [],
            ];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
            ]);
        } catch (\Exception $e) {
            Log::error('fetchDailyTrips exception', ['error' => $e->getMessage()]);

            return [
                'rows' => [],
                'summary' => [],
            ];
        }

        $allTrips = ($responses['trips']->ok()) ? $responses['trips']->json() : [];
        $allStops = ($responses['stops']->ok()) ? $responses['stops']->json() : [];

        $rows = collect($allTrips)->map(function ($trip, $index) {
            return [
                'key' => $index + 1,
                'date' => date('d/m/Y', strtotime($trip['startTime'])),
                'startTime' => date('h:i A', strtotime($trip['startTime'])),
                'startTimeIso' => $trip['startTime'],
                'startLocation' => $trip['startAddress'] ?? 'N/A',
                'endTime' => date('h:i A', strtotime($trip['endTime'])),
                'endTimeIso' => $trip['endTime'],
                'endLocation' => $trip['endAddress'] ?? 'N/A',
                'distance' => round(($trip['distance'] ?? 0) / 1000, 2).' KM',
                // Raw values for calculation if needed
                'distance_m' => $trip['distance'] ?? 0,
                'duration_ms' => $trip['duration'] ?? 0,
            ];
        });

        $stopsFormatted = collect($allStops)->map(function ($stop) {
            return [
                'startTime' => date('h:i A', strtotime($stop['startTime'])),
                'startTimeIso' => $stop['startTime'],
                'endTime' => date('h:i A', strtotime($stop['endTime'])),
                'endTimeIso' => $stop['endTime'],
                'duration_ms' => $stop['duration'] ?? 0,
                'address' => $stop['address'] ?? 'N/A',
            ];
        });

        // Calculate Summary
        $totalDistance = collect($allTrips)->sum('distance');
        $totalDuration = collect($allTrips)->sum('duration');
        $totalIdle = collect($allStops)->sum('duration');
        $maxSpeed = collect($allTrips)->max('maxSpeed') ?? 0;
        $rawMaxSpeed = $maxSpeed;
        // Sanity check for max speed (> 162 knots is unrealistic)
        if ($rawMaxSpeed > 162) {
            $rawMaxSpeed = 0;
        }

        // Fuel (if available in trip attributes)
        $totalFuel = collect($allTrips)->sum(function ($trip) {
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
            if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                return $startFuel - $endFuel;
            }

            return data_get($trip, 'spentFuel', 0);
        });

        return [
            'rows' => $rows,
            'stops' => $stopsFormatted,
            'summary' => [
                'totalDistance' => $totalDistance, // meters
                'totalDuration' => $totalDuration, // ms
                'totalIdle' => $totalIdle, // ms
                'maxSpeed' => $rawMaxSpeed * 1.852, // knots to km/h
                'totalFuel' => $totalFuel,
            ],
        ];
    }

    public function fetchDailySummary($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

            if (empty($deviceIds)) {
                return [
                    'rows' => [],
                    'summary' => [
                        'totalDistance' => 0,
                        'totalDuration' => 0,
                        'totalIdle' => 0,
                        'totalFuel' => 0,
                        'avgKmL' => 0,
                    ],
                    'chart' => [],
                ];
            }

            $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');
            $dailyStats = [];

            foreach ($deviceIds as $deviceId) {
                $this->abortIfClientDisconnected();
                $trips = $this->fetchTripsDb($deviceId, $fromIso, $toIso);

                foreach ($trips as $trip) {
                    $day = date('Y-m-d', strtotime($trip['startTime']));
                    $key = $deviceId.'|'.$day;

                    if (! isset($dailyStats[$key])) {
                        $dailyStats[$key] = [
                            'deviceid' => $deviceId,
                            'day' => $day,
                            'distance_m' => 0,
                            'trip_ms' => 0,
                            'idle_ms' => 0,
                            'trips' => [],
                        ];
                    }

                    $dailyStats[$key]['distance_m'] += $trip['distance'];
                    $dailyStats[$key]['trip_ms'] += $trip['duration'];
                    $dailyStats[$key]['trips'][] = $trip;
                }
            }

            $idleByDay = [];
            $idleByDay = $this->idleSecondsByDeviceDay($deviceIds, $fromIso, $toIso)->map(function ($m) {
                return $m->map(function ($secs) {
                    return $secs * 1000;
                });
            });
            foreach ($dailyStats as $key => &$stat) {
                $did = $stat['deviceid'];
                $day = $stat['day'];
                $stat['idle_ms'] = $idleByDay->get($did)?->get($day) ?? 0;
                unset($stat['trips']);
            }

            $groupBy = (string) ($request->group_by ?? '');
            $rows = collect($dailyStats)->values();

            if ($groupBy === 'vehicle') {
                $rows = $rows->groupBy('deviceid')->map(function ($group) use ($tcDevices) {
                    $first = $group->first();
                    $deviceId = $first['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';

                    return [
                        'key' => $deviceId,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                });
            } elseif ($groupBy === 'date') {
                $rows = $rows->groupBy('day')->map(function ($group) {
                    $first = $group->first();
                    $day = $first['day'];

                    return [
                        'key' => $day,
                        'date' => date('d/m/Y', strtotime($day)),
                        'dateRaw' => $day,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                });
            } else {
                $rows = $rows->map(function ($r) use ($tcDevices) {
                    $deviceId = $r['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';
                    $day = $r['day'];

                    return [
                        'key' => $deviceId.'_'.$day,
                        'date' => date('d/m/Y', strtotime($day)),
                        'dateRaw' => $day,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $r['distance_m'],
                        'trip_ms' => $r['trip_ms'],
                        'idle_ms' => $r['idle_ms'],
                    ];
                });
            }

            // Formatting
            $rows = $rows->map(function ($r) {
                $distKm = round($r['distance_m'] / 1000, 2);

                $tripMs = $r['trip_ms'];
                $durH = floor($tripMs / 3600000);
                $durM = floor(($tripMs % 3600000) / 60000);
                $durS = floor(($tripMs % 60000) / 1000);

                $idleMs = $r['idle_ms'];
                $idleH = floor($idleMs / 3600000);
                $idleM = floor(($idleMs % 3600000) / 60000);
                $idleS = floor(($idleMs % 60000) / 1000);

                $totalTime = $tripMs + $idleMs;
                $pct = ($totalTime > 0) ? round(($idleMs / $totalTime) * 100, 1) : 0;

                $r['distance'] = $distKm.' KM';
                $r['trip'] = sprintf('%dh %dm %ds', $durH, $durM, $durS);
                $r['idle'] = sprintf('%dh %dm %ds', $idleH, $idleM, $idleS);
                $r['idlePct'] = $pct.'%';

                return $r;
            })->values();

            $fuelAgg = $this->bulkFuelUsageLitresTelemetry($deviceIds, $fromIso, $toIso, 'day');
            $totalFuelLitres = floatval($fuelAgg['total'] ?? 0);
            $fuelByDevice = $fuelAgg['byDevice'] ?? [];
            $fuelByDay = $fuelAgg['byBucket'] ?? [];
            $fuelByDeviceDay = $fuelAgg['byDeviceBucket'] ?? [];

            $rows = $rows->map(function ($r) use ($groupBy, $fuelByDeviceDay, $fuelByDevice, $fuelByDay) {
                $fuel = 0.0;
                if ($groupBy === 'vehicle') {
                    $did = intval($r['vehicleId'] ?? 0);
                    $fuel = floatval($fuelByDevice[$did] ?? 0);
                } elseif ($groupBy === 'date') {
                    $day = (string) ($r['dateRaw'] ?? '');
                    $fuel = $day !== '' ? floatval($fuelByDay[$day] ?? 0) : 0.0;
                } else {
                    $did = intval($r['vehicleId'] ?? 0);
                    $day = (string) ($r['dateRaw'] ?? '');
                    $fuel = ($did && $day !== '') ? floatval($fuelByDeviceDay[$did][$day] ?? 0) : 0.0;
                }
                $r['fuel_litres'] = round($fuel, 2);

                return $r;
            })->values();

            $totalDistance = $rows->sum('distance_m');
            $totalDuration = $rows->sum('trip_ms');
            $totalIdle = $rows->sum('idle_ms');

            $chartData = $rows->groupBy('dateRaw')->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'distance' => $group->sum('distance_m'),
                    'tripDuration' => $group->sum('trip_ms'),
                    'idleDuration' => $group->sum('idle_ms'),
                ];
            })->values()->sortBy('date');

            return [
                'rows' => $rows,
                'summary' => [
                    'totalDistance' => $totalDistance,
                    'totalDuration' => $totalDuration,
                    'totalIdle' => $totalIdle,
                    'totalFuel' => round($totalFuelLitres, 2),
                    'avgKmL' => ($totalFuelLitres > 0) ? round(($totalDistance / 1000) / $totalFuelLitres, 2) : 0,
                ],
                'chart' => $chartData->values(),
            ];
        } catch (\Throwable $e) {
            Log::error('fetchDailySummaryDb failed', ['error' => $e->getMessage()]);

            return [
                'rows' => [],
                'summary' => [
                    'totalDistance' => 0,
                    'totalDuration' => 0,
                    'totalIdle' => 0,
                    'totalFuel' => 0,
                    'avgKmL' => 0,
                ],
                'chart' => [],
            ];
        }
    }

    public function fetchDailySummaryDb($request, $deviceIds)
    {
        return $this->fetchDailySummary($request, $deviceIds);
    }

    public function fetchMonthlySummary($request, $deviceIds)
    {
        $this->abortIfClientDisconnected();
        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

            if (empty($deviceIds)) {
                return [
                    'rows' => [],
                    'summary' => [
                        'totalDistance' => 0,
                        'totalDuration' => 0,
                        'totalIdle' => 0,
                        'totalFuel' => 0,
                        'avgKmL' => 0,
                    ],
                    'chart' => [],
                ];
            }

            $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');
            $monthlyStats = [];

            foreach ($deviceIds as $deviceId) {
                $this->abortIfClientDisconnected();
                $trips = $this->fetchTripsDb($deviceId, $fromIso, $toIso);

                foreach ($trips as $trip) {
                    $month = date('Y-m', strtotime($trip['startTime']));
                    $key = $deviceId.'|'.$month;

                    if (! isset($monthlyStats[$key])) {
                        $monthlyStats[$key] = [
                            'deviceid' => $deviceId,
                            'month' => $month,
                            'distance_m' => 0,
                            'trip_ms' => 0,
                            'idle_ms' => 0,
                            'trips' => [],
                        ];
                    }

                    $monthlyStats[$key]['distance_m'] += $trip['distance'];
                    $monthlyStats[$key]['trip_ms'] += $trip['duration'];
                    $monthlyStats[$key]['trips'][] = $trip;
                }
            }

            $idleByMonth = [];
            $idleByMonth = $this->idleSecondsByDeviceMonth($deviceIds, $fromIso, $toIso)->map(function ($m) {
                return $m->map(function ($secs) {
                    return $secs * 1000;
                });
            });
            foreach ($monthlyStats as $key => &$stat) {
                $did = $stat['deviceid'];
                $mon = $stat['month'];
                $stat['idle_ms'] = $idleByMonth->get($did)?->get($mon) ?? 0;
                unset($stat['trips']);
            }

            $groupBy = (string) ($request->group_by ?? '');
            $rows = collect($monthlyStats)->values();

            if ($groupBy === 'vehicle') {
                $rows = $rows->groupBy('deviceid')->map(function ($group) use ($tcDevices) {
                    $first = $group->first();
                    $deviceId = $first['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';

                    return [
                        'key' => $deviceId,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                });
            } else {
                if ($groupBy === 'date') {
                    $rows = $rows->groupBy('month')->map(function ($group) {
                        $first = $group->first();
                        $month = $first['month'];

                        return [
                            'key' => $month,
                            'date' => date('m/Y', strtotime($month.'-01')),
                            'dateRaw' => $month,
                            'distance_m' => $group->sum('distance_m'),
                            'trip_ms' => $group->sum('trip_ms'),
                            'idle_ms' => $group->sum('idle_ms'),
                        ];
                    });
                } else {
                    $rows = $rows->map(function ($r) use ($tcDevices) {
                        $deviceId = $r['deviceid'];
                        $dev = $tcDevices->get($deviceId);
                        $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';
                        $month = $r['month'];

                        return [
                            'key' => $deviceId.'_'.$month,
                            'date' => date('m/Y', strtotime($month.'-01')),
                            'dateRaw' => $month,
                            'vehicleId' => $deviceId,
                            'vehicle' => $vehicleName,
                            'distance_m' => $r['distance_m'],
                            'trip_ms' => $r['trip_ms'],
                            'idle_ms' => $r['idle_ms'],
                        ];
                    });
                }
            }

            // Formatting
            $rows = $rows->map(function ($r) {
                $distKm = round($r['distance_m'] / 1000, 2);

                $tripMs = $r['trip_ms'];
                $durH = floor($tripMs / 3600000);
                $durM = floor(($tripMs % 3600000) / 60000);
                $durS = floor(($tripMs % 60000) / 1000);

                $idleMs = $r['idle_ms'];
                $idleH = floor($idleMs / 3600000);
                $idleM = floor(($idleMs % 3600000) / 60000);
                $idleS = floor(($idleMs % 60000) / 1000);

                $totalTime = $tripMs + $idleMs;
                $pct = ($totalTime > 0) ? round(($idleMs / $totalTime) * 100, 1) : 0;

                $r['distance'] = $distKm.' KM';
                $r['trip'] = sprintf('%dh %dm %ds', $durH, $durM, $durS);
                $r['idle'] = sprintf('%dh %dm %ds', $idleH, $idleM, $idleS);
                $r['idlePct'] = $pct.'%';

                return $r;
            })->values();

            $fuelAgg = $this->bulkFuelUsageLitresTelemetry($deviceIds, $fromIso, $toIso, 'month');
            $totalFuelLitres = floatval($fuelAgg['total'] ?? 0);
            $fuelByDevice = $fuelAgg['byDevice'] ?? [];
            $fuelByMonth = $fuelAgg['byBucket'] ?? [];
            $fuelByDeviceMonth = $fuelAgg['byDeviceBucket'] ?? [];

            $rows = $rows->map(function ($r) use ($groupBy, $fuelByDeviceMonth, $fuelByDevice, $fuelByMonth) {
                $fuel = 0.0;
                if ($groupBy === 'vehicle') {
                    $did = intval($r['vehicleId'] ?? 0);
                    $fuel = floatval($fuelByDevice[$did] ?? 0);
                } elseif ($groupBy === 'date') {
                    $month = (string) ($r['dateRaw'] ?? '');
                    $fuel = $month !== '' ? floatval($fuelByMonth[$month] ?? 0) : 0.0;
                } else {
                    $did = intval($r['vehicleId'] ?? 0);
                    $month = (string) ($r['dateRaw'] ?? '');
                    $fuel = ($did && $month !== '') ? floatval($fuelByDeviceMonth[$did][$month] ?? 0) : 0.0;
                }
                $r['fuel_litres'] = round($fuel, 2);

                return $r;
            })->values();

            $totalDistance = $rows->sum('distance_m');
            $totalDuration = $rows->sum('trip_ms');
            $totalIdle = $rows->sum('idle_ms');

            $chartData = $rows->groupBy('dateRaw')->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'distance' => $group->sum('distance_m'),
                    'tripDuration' => $group->sum('trip_ms'),
                    'idleDuration' => $group->sum('idle_ms'),
                ];
            })->values()->sortBy('date');

            return [
                'rows' => $rows,
                'summary' => [
                    'totalDistance' => $totalDistance,
                    'totalDuration' => $totalDuration,
                    'totalIdle' => $totalIdle,
                    'totalFuel' => round($totalFuelLitres, 2),
                    'avgKmL' => ($totalFuelLitres > 0) ? round(($totalDistance / 1000) / $totalFuelLitres, 2) : 0,
                ],
                'chart' => $chartData->values(),
            ];
        } catch (\Throwable $e) {
            Log::error('fetchMonthlySummary failed', ['error' => $e->getMessage()]);

            return [
                'rows' => [],
                'summary' => [
                    'totalDistance' => 0,
                    'totalDuration' => 0,
                    'totalIdle' => 0,
                    'totalFuel' => 0,
                    'avgKmL' => 0,
                ],
                'chart' => [],
            ];
        }
    }

    public function fetchDailyBreakdownMap($request, $deviceIds)
    {
        return $this->fetchDailyBreakdownMapDb($request, $deviceIds);
    }

    public function fetchDailyBreakdownMapDb($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $allTrips = [];
        $allEvents = [];
        $allRoutes = [];
        $allStops = [];
        $idleByDayMs = $this->idleSecondsByDeviceDay($deviceIds, $from, $to)->map(function ($m) {
            return $m->map(function ($secs) {
                return $secs * 1000;
            });
        });

        foreach ($deviceIds as $deviceId) {
            $this->abortIfClientDisconnected();
            // 1. Trips
            $trips = $this->fetchTripsDb($deviceId, $from, $to);
            foreach ($trips as &$t) {
                $t['deviceId'] = $deviceId;
            }
            // Fetch device name for the first trip
            $deviceRow = DB::connection('pgsql')->table('tc_devices')->select('name', 'attributes')->where('id', $deviceId)->first();
            $deviceName = $deviceRow?->name;
            $deviceAttributes = $deviceRow?->attributes;
            foreach ($trips as &$t) {
                $t['deviceName'] = $deviceName;
                $t['deviceAttributes'] = $deviceAttributes;
            }

            $allTrips = array_merge($allTrips, $trips);

            // 2. Events (with location)
            $eventsData = DB::connection('pgsql')->select('
                SELECT
                    e.id, e.type, e.eventtime, e.deviceid, e.attributes, e.positionid,
                    p.latitude, p.longitude, p.address
                FROM tc_events e
                LEFT JOIN tc_positions p ON e.positionid = p.id
                WHERE e.deviceid = ?
                  AND e.eventtime BETWEEN ? AND ?
            ', [$deviceId, $from, $to]);

            foreach ($eventsData as $e) {
                $allEvents[] = [
                    'id' => $e->id,
                    'type' => $e->type,
                    'eventTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($e->eventtime)),
                    'deviceId' => $e->deviceid,
                    'attributes' => json_decode($e->attributes, true),
                    'positionId' => $e->positionid,
                    'latitude' => $e->latitude,
                    'longitude' => $e->longitude,
                    'address' => $e->address,
                ];
            }

            // 3. Routes (Positions)
            // Optimize: Select only needed columns and skip unused attributes decoding
            // Downsample: Get roughly every 10th point if duration > 7 days, or every 5th if > 1 day
            // Actually, for 2 months, 500k rows is too much. Let's filter in PHP to be flexible.
            // But fetching 500k rows is fast enough (30-50MB). The bottleneck is filtering logic.
            // We REMOVE 'address' to save memory/bandwidth.
            $positions = DB::connection('pgsql')->select('
                SELECT id, deviceid, fixtime, latitude, longitude, speed
                FROM tc_positions
                WHERE deviceid = ?
                  AND fixtime BETWEEN ? AND ?
                ORDER BY fixtime ASC
            ', [$deviceId, $from, $to]);

            foreach ($positions as $p) {
                $allRoutes[] = [
                    'id' => $p->id,
                    'deviceId' => $p->deviceid,
                    'fixTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($p->fixtime)),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'speed' => $p->speed,
                    // 'address' => $p->address, // Removed to save memory
                ];
            }

            // 4. Stops (Calculated)
            usort($trips, fn ($a, $b) => strcmp($a['startTime'], $b['startTime']));
            for ($i = 0; $i < count($trips) - 1; $i++) {
                $currentTrip = $trips[$i];
                $nextTrip = $trips[$i + 1];
                $stopStart = $currentTrip['endTime'];
                $stopEnd = $nextTrip['startTime'];
                $duration = strtotime($stopEnd) - strtotime($stopStart);
                if ($duration > 180) {
                    $allStops[] = [
                        'deviceId' => $deviceId,
                        'startTime' => $stopStart,
                        'endTime' => $stopEnd,
                        'duration' => $duration * 1000,
                        'address' => $currentTrip['endAddress'] ?? 'N/A',
                        'latitude' => $currentTrip['endLat'],
                        'longitude' => $currentTrip['endLon'],
                    ];
                }
            }
        }

        $trips = collect($allTrips);
        $events = collect($allEvents);
        $stops = collect($allStops);
        // Optimization: Group routes by day first to avoid O(N*M) loop
        $routesGroupedByDay = collect($allRoutes)->groupBy(function ($r) {
            return date('Y-m-d', strtotime($r['fixTime']));
        });

        $grouped = $trips->groupBy(function ($t) {
            return date('Y-m-d', strtotime($t['startTime'])).'_'.$t['deviceId'];
        });

        $result = $grouped->map(function ($dayTrips, $key) use ($events, $stops, $routesGroupedByDay, $idleByDayMs) {
            [$date, $deviceId] = explode('_', $key);
            $deviceName = $dayTrips->first()['deviceName'] ?? 'Unknown';

            $dayEvents = $events->filter(function ($e) use ($date, $deviceId) {
                return $e['deviceId'] == $deviceId && date('Y-m-d', strtotime($e['eventTime'])) == $date;
            });
            $dayStops = $stops->filter(function ($s) use ($date, $deviceId) {
                return $s['deviceId'] == $deviceId && date('Y-m-d', strtotime($s['startTime'])) == $date;
            });

            // Further filter: only points within trips (to exclude idle drift/noise)
            // Pre-calculate trip ranges for faster checking
            $tripRanges = $dayTrips->map(function ($t) {
                return [strtotime($t['startTime']), strtotime($t['endTime'])];
            })->all();

            // Optimized route filtering:
            // Include route points from all days touched by trips (fixes trips spanning midnight)
            $routeDayKeys = [];
            foreach ($tripRanges as $range) {
                $rangeStartDay = date('Y-m-d', $range[0]);
                $rangeEndDay = date('Y-m-d', $range[1]);
                $d = $rangeStartDay;
                while (strtotime($d) <= strtotime($rangeEndDay)) {
                    $routeDayKeys[] = $d;
                    $d = date('Y-m-d', strtotime($d.' +1 day'));
                }
            }
            $routeDayKeys = array_values(array_unique(array_merge([$date], $routeDayKeys)));
            $dayRoutesRaw = collect([]);
            foreach ($routeDayKeys as $dk) {
                $dayRoutesRaw = $dayRoutesRaw->merge($routesGroupedByDay->get($dk, collect([])));
            }

            $dayRoutes = $dayRoutesRaw->filter(function ($r) use ($tripRanges, $deviceId) {
                if ($r['deviceId'] != $deviceId) {
                    return false;
                }
                $t = strtotime($r['fixTime']);
                foreach ($tripRanges as $range) {
                    if ($t >= $range[0] && $t <= $range[1]) {
                        return true;
                    }
                }

                return false;
            });

            // Downsample if too many points (e.g., > 1000 per day)
            if ($dayRoutes->count() > 1000) {
                $nth = ceil($dayRoutes->count() / 1000);
                $dayRoutes = $dayRoutes->values()->filter(function ($v, $k) use ($nth) {
                    return $k % $nth === 0;
                });
            }

            // Build timeline
            $tripTimeline = $dayTrips->flatMap(function ($trip) use ($dayEvents) {
                $durMs = $trip['duration'] ?? 0;
                $durSec = floor($durMs / 1000);
                $h = floor($durSec / 3600);
                $m = floor(($durSec % 3600) / 60);
                $s = $durSec % 60;
                $durStr = ($h > 0) ? sprintf('%dh %dm %ds', $h, $m, $s) : sprintf('%dm %ds', $m, $s);

                $tripStart = strtotime($trip['startTime']);
                $tripEnd = strtotime($trip['endTime']);

                $tripEvents = $dayEvents->filter(function ($e) use ($tripStart, $tripEnd) {
                    $t = strtotime($e['eventTime']);

                    return $t >= $tripStart && $t <= $tripEnd;
                });

                $svCount = $tripEvents->where('type', 'overspeed')->count() + $tripEvents->where('type', 'deviceOverspeed')->count();
                $haCount = $tripEvents->where('type', 'harshAcceleration')->count();
                $hbCount = $tripEvents->where('type', 'harshBraking')->count();

                $badges = [];
                if ($svCount > 0) {
                    $badges[] = "$svCount SV";
                }
                if ($haCount > 0) {
                    $badges[] = "$haCount HA";
                }
                if ($hbCount > 0) {
                    $badges[] = "$hbCount HB";
                }
                $alertBadge = implode(', ', $badges);

                return [
                    [
                        'time_sort' => $tripStart,
                        'time' => date('h:i A', $tripStart),
                        'location' => $trip['startAddress'] ?? '',
                        'dist' => round(($trip['distance'] ?? 0) / 1000, 2).'KM',
                        'dur' => $durStr,
                        'alert' => $alertBadge,
                        'type' => 'start',
                        'lat' => $trip['startLat'] ?? 0,
                        'lon' => $trip['startLon'] ?? 0,
                    ],
                    [
                        'time_sort' => $tripEnd,
                        'time' => date('h:i A', $tripEnd),
                        'location' => $trip['endAddress'] ?? '',
                        'type' => 'end',
                        'lat' => $trip['endLat'] ?? 0,
                        'lon' => $trip['endLon'] ?? 0,
                    ],
                ];
            });

            // Events
            $eventTimeline = $dayEvents->filter(function ($event) {
                return ! in_array($event['type'], ['deviceOnline', 'deviceOffline']);
            })->map(function ($event) use ($dayRoutes) {
                $eventTs = strtotime($event['eventTime']);
                // Use joined location if available, otherwise fallback to route match (rare)
                $lat = $event['latitude'] ?? 0;
                $lon = $event['longitude'] ?? 0;
                $addr = $event['address'] ?? '';

                if ($lat == 0 && $lon == 0) {
                    $closest = $dayRoutes->sortBy(function ($r) use ($eventTs) {
                        return abs(strtotime($r['fixTime']) - $eventTs);
                    })->first();
                    $lat = $closest['latitude'] ?? 0;
                    $lon = $closest['longitude'] ?? 0;
                    $addr = $closest['address'] ?? '';
                }

                $friendlyName = $event['type'];
                if ($event['type'] == 'overspeed' || $event['type'] == 'deviceOverspeed') {
                    $friendlyName = 'Overspeed';
                }
                if ($event['type'] == 'harshAcceleration') {
                    $friendlyName = 'Harsh Acceleration';
                }
                if ($event['type'] == 'harshBraking') {
                    $friendlyName = 'Harsh Braking';
                }

                return [
                    'time_sort' => $eventTs,
                    'time' => date('h:i A', $eventTs),
                    'location' => $addr,
                    'alert' => $friendlyName,
                    'type' => 'alert',
                    'lat' => $lat,
                    'lon' => $lon,
                    'hidden' => true,
                ];
            });

            // Stops
            $stopTimeline = $dayStops->map(function ($stop) {
                $stopTs = strtotime($stop['startTime']);

                return [
                    'time_sort' => $stopTs,
                    'time' => date('h:i A', $stopTs),
                    'location' => $stop['address'] ?? '',
                    'type' => 'stop',
                    'lat' => $stop['latitude'] ?? 0,
                    'lon' => $stop['longitude'] ?? 0,
                ];
            });

            // Merge and Sort
            $timeline = $tripTimeline->merge($eventTimeline)
                ->merge($stopTimeline)
                ->sortBy('time_sort')
                ->values();

            $timeline = $timeline->filter(function ($item, $idx) use ($timeline) {
                if (($item['type'] ?? '') !== 'stop') {
                    return true;
                }
                if ($idx <= 0) {
                    return true;
                }
                $prev = $timeline[$idx - 1] ?? null;
                if (! is_array($prev)) {
                    return true;
                }
                if (($prev['type'] ?? '') !== 'end') {
                    return true;
                }
                if (($prev['time_sort'] ?? null) !== ($item['time_sort'] ?? null)) {
                    return true;
                }
                $prevLoc = trim((string) ($prev['location'] ?? ''));
                $curLoc = trim((string) ($item['location'] ?? ''));

                return $prevLoc === '' || $curLoc === '' || strcasecmp($prevLoc, $curLoc) !== 0;
            })->values()->all();

            // Route points
            $routePoints = $dayRoutes->map(function ($r) {
                return [$r['latitude'], $r['longitude'], strtotime($r['fixTime']) * 1000, $r['speed'] ?? null];
            })->values()->all();

            // Summary
            $totalDist = $dayTrips->sum('distance');
            $totalDur = $dayTrips->sum('duration');
            $totalIdle = $idleByDayMs->get((int) $deviceId)?->get($date) ?? 0;

            $durSec = floor($totalDur / 1000);
            $h = floor($durSec / 3600);
            $m = floor(($durSec % 3600) / 60);
            $s = $durSec % 60;
            $formattedDur = sprintf('%dh %dm %ds', $h, $m, $s);

            $idleSec = floor($totalIdle / 1000);
            $ih = floor($idleSec / 3600);
            $im = floor(($idleSec % 3600) / 60);
            $is = $idleSec % 60;
            $formattedIdle = sprintf('%dh %dm %ds', $ih, $im, $is);

            return [
                'date' => $date,
                'deviceId' => $deviceId,
                'deviceName' => $deviceName,
                'deviceAttributes' => $dayTrips->first()['deviceAttributes'] ?? null,
                'totalDistance' => round($totalDist / 1000, 2).' KM',
                'totalDuration' => $formattedDur,
                'totalIdle' => $formattedIdle,
                'timeline' => $timeline,
                'route' => $routePoints,
            ];
        });

        return $result->values();
    }

    public function fetchDailyBreakdownMapOld($request, $deviceIds)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        Log::info('fetchDailyBreakdownMap called', ['deviceIds' => $deviceIds]);

        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $deviceQuery = collect($deviceIds)->map(function ($id) {
            return "deviceId={$id}";
        })->implode('&');
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            Log::error('ReportService: Tracking server host URL is not configured.');

            return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $trips = collect([]);
        $events = collect([]);
        $stops = collect([]);
        $routes = collect([]);

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->timeout(120)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
            ]);

            $trips = collect(($responses['trips']->ok()) ? $responses['trips']->json() : []);
            $events = collect(($responses['events']->ok()) ? $responses['events']->json() : []);
            $stops = collect(($responses['stops']->ok()) ? $responses['stops']->json() : []);

        } catch (\Throwable $e) {
            Log::error('fetchDailyBreakdownMap base data exception', ['error' => $e->getMessage()]);

            return [];
        }

        // Fetch Route separately to avoid failing everything on timeout
        try {
            $routeResponse = Http::withHeaders($headers)->timeout(60)->get("{$baseUrl}/api/reports/route?{$fullQuery}");
            if ($routeResponse->successful()) {
                $routes = collect($routeResponse->json() ?? []);
            } else {
                Log::error('DailyBreakdownMap Route API Error', [
                    'status' => $routeResponse->status(),
                    'body' => substr($routeResponse->body(), 0, 500),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('fetchDailyBreakdownMap route exception', ['error' => $e->getMessage()]);
            // Continue without routes
        }

        $grouped = $trips->groupBy(function ($t) {
            return date('Y-m-d', strtotime($t['startTime'])).'_'.$t['deviceId'];
        });

        $result = $grouped->map(function ($dayTrips, $key) use ($events, $stops, $routes) {
            [$date, $deviceId] = explode('_', $key);
            $deviceName = $dayTrips->first()['deviceName'] ?? 'Unknown';

            $dayEvents = $events->filter(function ($e) use ($date, $deviceId) {
                return $e['deviceId'] == $deviceId && date('Y-m-d', strtotime($e['eventTime'])) == $date;
            });
            $dayStops = $stops->filter(function ($s) use ($date, $deviceId) {
                return $s['deviceId'] == $deviceId && date('Y-m-d', strtotime($s['startTime'])) == $date;
            });
            $dayRoutes = $routes->filter(function ($r) use ($dayTrips, $deviceId) {
                if ($r['deviceId'] != $deviceId) {
                    return false;
                }
                $rTime = strtotime($r['fixTime']);

                // Check if point belongs to any trip in this day
                return $dayTrips->contains(function ($trip) use ($rTime) {
                    $start = strtotime($trip['startTime']);
                    $end = strtotime($trip['endTime']);

                    return $rTime >= $start && $rTime <= $end;
                });
            });

            // Build timeline
            $tripTimeline = $dayTrips->flatMap(function ($trip) use ($dayEvents) {
                $durMs = $trip['duration'] ?? 0;
                $durSec = floor($durMs / 1000);
                $h = floor($durSec / 3600);
                $m = floor(($durSec % 3600) / 60);
                $s = $durSec % 60;
                $durStr = ($h > 0) ? sprintf('%dh %dm %ds', $h, $m, $s) : sprintf('%dm %ds', $m, $s);

                // Aggregate events for this trip
                $tripStart = strtotime($trip['startTime']);
                $tripEnd = strtotime($trip['endTime']);

                $tripEvents = $dayEvents->filter(function ($e) use ($tripStart, $tripEnd) {
                    $t = strtotime($e['eventTime']);

                    return $t >= $tripStart && $t <= $tripEnd;
                });

                $svCount = $tripEvents->where('type', 'overspeed')->count();
                $haCount = $tripEvents->where('type', 'harshAcceleration')->count();
                $hbCount = $tripEvents->where('type', 'harshBraking')->count();

                $badges = [];
                if ($svCount > 0) {
                    $badges[] = "$svCount SV";
                }
                if ($haCount > 0) {
                    $badges[] = "$haCount HA";
                }
                if ($hbCount > 0) {
                    $badges[] = "$hbCount HB";
                }
                $alertBadge = implode(', ', $badges);

                return [
                    [
                        'time_sort' => $tripStart,
                        'time' => date('h:i A', $tripStart),
                        'location' => $trip['startAddress'] ?? '',
                        'dist' => round(($trip['distance'] ?? 0) / 1000, 2).'KM',
                        'dur' => $durStr,
                        'alert' => $alertBadge,
                        'type' => 'start',
                        'lat' => $trip['startLat'] ?? 0,
                        'lon' => $trip['startLon'] ?? 0,
                    ],
                    [
                        'time_sort' => $tripEnd,
                        'time' => date('h:i A', $tripEnd),
                        'location' => $trip['endAddress'] ?? '',
                        'type' => 'end',
                        'lat' => $trip['endLat'] ?? 0,
                        'lon' => $trip['endLon'] ?? 0,
                    ],
                ];
            });

            // Events (Hidden in list, visible on map)
            $eventTimeline = $dayEvents->filter(function ($event) {
                return ! in_array($event['type'], ['deviceOnline', 'deviceOffline']);
            })->map(function ($event) use ($dayRoutes) {
                $eventTs = strtotime($event['eventTime']);
                $closest = $dayRoutes->sortBy(function ($r) use ($eventTs) {
                    return abs(strtotime($r['fixTime']) - $eventTs);
                })->first();

                $lat = $closest['latitude'] ?? 0;
                $lon = $closest['longitude'] ?? 0;
                $addr = $closest['address'] ?? '';

                $friendlyName = $event['type'];
                if ($event['type'] == 'overspeed') {
                    $friendlyName = 'Overspeed';
                }
                if ($event['type'] == 'harshAcceleration') {
                    $friendlyName = 'Harsh Acceleration';
                }
                if ($event['type'] == 'harshBraking') {
                    $friendlyName = 'Harsh Braking';
                }

                return [
                    'time_sort' => $eventTs,
                    'time' => date('h:i A', $eventTs),
                    'location' => $addr,
                    'alert' => $friendlyName,
                    'type' => 'alert',
                    'lat' => $lat,
                    'lon' => $lon,
                    'hidden' => true,
                ];
            });

            // Stops
            $stopTimeline = $dayStops->map(function ($stop) {
                $stopTs = strtotime($stop['startTime']);

                return [
                    'time_sort' => $stopTs,
                    'time' => date('h:i A', $stopTs),
                    'location' => $stop['address'] ?? '',
                    'type' => 'stop',
                    'lat' => $stop['latitude'] ?? 0,
                    'lon' => $stop['longitude'] ?? 0,
                ];
            });

            // Merge and Sort
            $timeline = $tripTimeline->merge($eventTimeline)
                ->merge($stopTimeline)
                ->sortBy('time_sort')
                ->values()
                ->all();

            // Route points
            $routePoints = $dayRoutes->map(function ($r) {
                return [$r['latitude'], $r['longitude'], strtotime($r['fixTime']) * 1000, $r['speed'] ?? null];
            })->values()->all();

            // Summary
            $totalDist = $dayTrips->sum('distance');
            $totalDur = $dayTrips->sum('duration');
            $totalIdle = $dayStops->sum('duration');

            // Format Duration
            $durSec = floor($totalDur / 1000);
            $h = floor($durSec / 3600);
            $m = floor(($durSec % 3600) / 60);
            $s = $durSec % 60;
            $formattedDur = sprintf('%dh %dm %ds', $h, $m, $s);

            // Format Idle
            $idleSec = floor($totalIdle / 1000);
            $ih = floor($idleSec / 3600);
            $im = floor(($idleSec % 3600) / 60);
            $is = $idleSec % 60;
            $formattedIdle = sprintf('%dh %dm %ds', $ih, $im, $is);

            // Behav Badges
            $totalSV = $dayEvents->where('type', 'overspeed')->count();
            $totalHA = $dayEvents->where('type', 'harshAcceleration')->count();
            $totalHB = $dayEvents->where('type', 'harshBraking')->count();

            $badges = [];
            if ($totalSV > 0) {
                $badges[] = "$totalSV SV";
            }
            if ($totalHA > 0) {
                $badges[] = "$totalHA HA";
            }
            if ($totalHB > 0) {
                $badges[] = "$totalHB HB";
            }
            $behavStr = empty($badges) ? '0' : implode(', ', $badges);

            return [
                'key' => $key,
                'date' => date('d/m/Y - l', strtotime($date)),
                'distance' => round($totalDist / 1000, 2).' KM',
                'isOpen' => true,
                'summary' => [
                    'date' => date('d/m/Y - l', strtotime($date)),
                    'dist' => round($totalDist / 1000, 2).' km',
                    'dur' => $formattedDur,
                    'idle' => $formattedIdle,
                    'behav' => $behavStr,
                ],
                'timeline' => $timeline,
                'route' => $routePoints,
            ];
        });

        return $result->values();
    }

    public function getDeviceEvents($request)
    {
        $deviceId = $request->device_id;

        // Check permission and get device details
        $deviceName = Devices::accessibleByUser($request->user())
            ->where('device_id', $deviceId)
            ->first();

        if (! $deviceName) {
            // Return empty or throw exception?
            // Since this returns an array, maybe return empty array or let the controller handle it.
            // But existing code didn't check for null, so it would crash.
            // Let's assume valid request for now but at least scope the query.
            // If we want to be safe:
            return [];
        }

        $fromStr = $request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $fromStr .= ' 00:00:00';
        }

        $toStr = $request->to_date;
        if (! $toStr) {
            $toStr = date('Y-m-d H:i:s');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
        }

        $rows = DB::connection('pgsql')->table('tc_events as e')
            ->leftJoin('tc_positions as p', 'e.positionid', '=', 'p.id')
            ->select('e.eventtime', 'e.type', 'e.attributes')
            ->where('e.deviceid', (int) $deviceId)
            ->whereBetween('e.eventtime', [$fromStr, $toStr])
            ->orderBy('e.eventtime', 'asc')
            ->get();

        $formattedEvents = collect($rows)->map(function ($row) use ($deviceName) {
            $evt = [
                'eventTime' => $row->eventtime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $row->type ?? 'unknown',
                'attributes' => $row->attributes ?? null,
            ];

            return [
                'eventTime' => $evt['eventTime'],
                'deviceName' => $evt['deviceName'],
                'type' => $evt['type'],
                'description' => $this->formatEventDescription($evt),
                'attributes' => $evt['attributes'],
            ];
        })->all();

        return $formattedEvents;
    }

    public function getDeviceStops($request)
    {
        $deviceId = $request->device_id;

        // Check permission and get device details
        $deviceName = Devices::accessibleByUser($request->user())
            ->where('device_id', $deviceId)
            ->first();

        if (! $deviceName) {
            return [];
        }

        $fromStr = $request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $fromStr .= ' 00:00:00';
        }

        $toStr = $request->to_date;
        if (! $toStr) {
            $toStr = date('Y-m-d H:i:s');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
        }

        $rows = DB::connection('pgsql')->table('tc_events as e')
            ->leftJoin('tc_positions as p', 'e.positionid', '=', 'p.id')
            ->select('e.eventtime', 'e.type', 'e.attributes')
            ->where('e.deviceid', (int) $deviceId)
            ->whereBetween('e.eventtime', [$fromStr, $toStr])
            ->whereIn('e.type', ['deviceStopped'])
            ->orderBy('e.eventtime', 'asc')
            ->get();

        $formattedEvents = collect($rows)->map(function ($row) use ($deviceName) {
            $evt = [
                'eventTime' => $row->eventtime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $row->type ?? 'unknown',
                'attributes' => $row->attributes ?? null,
            ];

            return [
                'eventTime' => $evt['eventTime'],
                'deviceName' => $evt['deviceName'],
                'type' => $evt['type'],
                'description' => $this->formatEventDescription($evt),
                'attributes' => $evt['attributes'],
            ];
        })->all();

        return $formattedEvents;
    }

    public function fetchAssetActivityDb($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        if (empty($deviceIds)) {
            return [
                'header' => null,
                'rows' => [],
            ];
        }

        $deviceIds = array_values(array_map('intval', $deviceIds));

        $limitParam = (int) $request->input('limit', 100);
        if ($limitParam <= 0) {
            $limitParam = 100;
        }

        $from = \Carbon\Carbon::parse($request->from_date)->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->format('Y-m-d H:i:s');

        $toIso = (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $request->to_date) ? $request->to_date.' 23:59:59' : $request->to_date);
        $fromIso = (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $request->from_date) ? $request->from_date.' 12:01:59' : $request->from_date);

        $from = date('Y-m-d\\TH:i:00\\Z', strtotime($fromIso));
        $to = date('Y-m-d\\TH:i:00\\Z', strtotime($toIso));

        $tcDevices = DB::connection('pgsql')
            ->table('tc_devices')
            ->select('id', 'name', 'uniqueid', 'attributes')
            ->whereIn('id', $deviceIds)
            ->get()
            ->keyBy('id');

        $positions = DB::connection('pgsql')
            ->table('tc_positions as p')
            ->select(
                'p.id',
                'p.deviceid',
                'p.fixtime',
                'p.latitude',
                'p.longitude',
                'p.speed',
                'p.course',
                'p.address',
                'p.attributes'
            )
            ->whereIn('p.deviceid', $deviceIds)
            ->whereBetween('p.fixtime', [$from, $to])
            ->orderBy('p.fixtime', 'desc')
            ->limit($limitParam)
            ->get();
        $this->abortIfClientDisconnected();

        $positionIds = $positions->pluck('id')->toArray();
        $eventsGrouped = [];

        if (! empty($positionIds)) {
            $events = DB::connection('pgsql')
                ->table('tc_events')
                ->select('positionid', 'type', 'attributes', 'eventtime')
                ->whereIn('positionid', $positionIds)
                ->orderBy('eventtime', 'asc') // process in order
                ->get();

            foreach ($events as $e) {
                $pid = $e->positionid;
                // We want the latest event for the position, similar to original logic
                // Original logic: if ($newTime > $existingTime) update.
                // Since we iterate, we can just overwrite or check.
                // Let's store the best event for each position.
                if (! isset($eventsGrouped[$pid])) {
                    $eventsGrouped[$pid] = $e;
                } else {
                    $curr = $eventsGrouped[$pid];
                    if (strtotime($e->eventtime) > strtotime($curr->eventtime)) {
                        $eventsGrouped[$pid] = $e;
                    }
                }
            }
        }

        // Merge events into positions
        $positionsForRows = $positions->map(function ($p) use ($eventsGrouped) {
            $e = $eventsGrouped[$p->id] ?? null;
            $p->event_type = $e ? $e->type : null;
            $p->event_attributes = $e ? $e->attributes : null;
            $p->event_time = $e ? $e->eventtime : null;

            return $p;
        });

        $positionRows = $positionsForRows->map(function ($p) use ($tcDevices) {
            $deviceId = (int) $p->deviceid;
            $device = $tcDevices->get($deviceId);
            $deviceName = $device ? ($device->name ?? 'Unknown') : 'Unknown';

            $epoch = strtotime($p->fixtime);
            if ($epoch === false) {
                return null;
            }

            $attrsRaw = $p->attributes;
            if (is_string($attrsRaw)) {
                $attrs = json_decode($attrsRaw, true) ?? [];
            } elseif (is_array($attrsRaw)) {
                $attrs = $attrsRaw;
            } else {
                $attrs = [];
            }

            $lat = $p->latitude !== null ? round($p->latitude, 5) : 0;
            $lon = $p->longitude !== null ? round($p->longitude, 5) : 0;

            $power = $attrs['power'] ?? ($attrs['battery'] ?? null);
            if ($power !== null && is_numeric($power)) {
                $power = round((float) $power, 1).'V';
            } else {
                $power = null;
            }

            $fuel = $this->formatFuel($attrs);

            $rssi = $attrs['rssi'] ?? null;
            $gsm = 'No Signal';
            if ($rssi !== null && is_numeric($rssi)) {
                $rssi = (float) $rssi;
                if ($rssi >= -70) {
                    $gsm = 'Excellent';
                } elseif ($rssi >= -85) {
                    $gsm = 'Good';
                } elseif ($rssi >= -100) {
                    $gsm = 'Fair';
                } elseif ($rssi >= -110) {
                    $gsm = 'Poor';
                } else {
                    $gsm = 'Very Poor';
                }
            }

            $sat = $attrs['sat'] ?? 0;
            $gps = 'No Signal';
            if (is_numeric($sat) && $sat > 0) {
                $sat = (int) $sat;
                if ($sat >= 10) {
                    $gps = 'Excellent';
                } elseif ($sat >= 7) {
                    $gps = 'Good';
                } elseif ($sat >= 4) {
                    $gps = 'Fair';
                } else {
                    $gps = 'Poor';
                }
            }

            $ignRaw = $attrs['ignition'] ?? null;
            $isIgnitionOn = false;
            if ($ignRaw !== null) {
                if (is_bool($ignRaw)) {
                    $isIgnitionOn = $ignRaw;
                } elseif (is_numeric($ignRaw)) {
                    $isIgnitionOn = ((int) $ignRaw) === 1;
                } else {
                    $val = strtolower(trim((string) $ignRaw));
                    $isIgnitionOn = in_array($val, ['true', 'on', '1', 'yes'], true);
                }
            }
            $ignition = $isIgnitionOn ? 'ON' : 'OFF';

            $speedKnots = $p->speed ?? 0;
            $speedVal = round(((float) $speedKnots) * 1.852, 0);
            $speed = $speedVal.' km/h';

            $status = null;

            if ($p->event_type === 'deviceOffline') {
                $status = 'Offline';
            } elseif ($p->event_type) {
                $eventAttrsRaw = $p->event_attributes;
                if (is_string($eventAttrsRaw)) {
                    $eventAttrs = json_decode($eventAttrsRaw, true) ?? [];
                } elseif (is_array($eventAttrsRaw)) {
                    $eventAttrs = $eventAttrsRaw;
                } else {
                    $eventAttrs = [];
                }

                $eventPayload = [
                    'type' => $p->event_type,
                    'deviceName' => $deviceName,
                    'attributes' => $eventAttrs,
                ];

                $status = $this->formatEventDescription($eventPayload);
            } else {
                $status = 'Stopped';
                if ($speedVal > 1) {
                    $status = 'Moving';
                } elseif ($isIgnitionOn) {
                    $status = 'Idle';
                }
            }

            if ($status !== null && stripos($status, 'moving') !== false && ! $isIgnitionOn) {
                $isIgnitionOn = true;
                $ignition = 'ON';
            }

            $location = $p->address ?? '';
            if (is_string($location)) {
                $location = trim($location);
            }
            if ($location === '' && ($lat !== 0 || $lon !== 0)) {
                $coords = $lat.','.$lon;
                $location = 'https://www.google.com/maps?q='.$coords;
            }

            return [
                'key' => 0,
                'vehicle' => $deviceName,
                'groupDate' => date('d-m-Y l', $epoch),
                'date' => date('d-m-Y', $epoch),
                'time' => date('H:i:s', $epoch),
                'status' => $status,
                'lat' => $lat,
                'lon' => $lon,
                'location' => $location,
                'direction' => $p->course ?? 0,
                'speed' => $speed,
                'gsm' => $gsm,
                'gps' => $gps,
                'power' => $power,
                'ignition' => $ignition,
                'fuel' => $fuel,
                'isEvent' => false,
                'rawType' => 'position',
                'epoch' => $epoch,
            ];
        })->filter();

        $rows = $positionRows->values()->all();

        usort($rows, function ($a, $b) {
            return $a['epoch'] <=> $b['epoch'];
        });

        if ($limitParam > 0 && count($rows) > $limitParam) {
            $rows = array_slice($rows, 0, $limitParam);
        }

        $lastEpoch = null;
        $lastLocation = '';
        $lastLat = null;
        $lastLon = null;

        $rows = array_map(function ($index, $row) use (&$lastEpoch, &$lastLocation, &$lastLat, &$lastLon) {
            $row['key'] = $index;

            if ($lastEpoch === null || $row['epoch'] > $lastEpoch) {
                $lastEpoch = $row['epoch'];
                if (! $row['isEvent']) {
                    $lastLocation = $row['location'] ?: ($row['lat'].', '.$row['lon']);
                    $lastLat = $row['lat'];
                    $lastLon = $row['lon'];
                } else {
                    if ($row['location']) {
                        $lastLocation = $row['location'];
                    }
                }
            }

            return $row;
        }, array_keys($rows), $rows);

        $singleDeviceName = 'Unknown';
        if (count($deviceIds) === 1) {
            $device = $tcDevices->get($deviceIds[0]);
            if ($device && $device->name) {
                $singleDeviceName = $device->name;
            }
        }

        $vehicleLabel = count($deviceIds) > 1 ? 'Multiple Vehicles ('.count($deviceIds).')' : $singleDeviceName;
        $deviceIdLabel = count($deviceIds) > 1 ? 'Multiple' : ($deviceIds[0] ?? 'N/A');
        $deviceUniqueIdLabel = 'Multiple';
        $vehicleNoLabel = count($deviceIds) > 1 ? 'Multiple' : 'N/A';
        if (count($deviceIds) === 1) {
            $device = $tcDevices->get($deviceIds[0]);
            if ($device) {
                $deviceUniqueIdLabel = $device->uniqueid ? (string) $device->uniqueid : 'N/A';
                $attrs = $device->attributes ?? [];
                if (is_string($attrs)) {
                    $decoded = json_decode($attrs, true);
                    $attrs = is_array($decoded) ? $decoded : [];
                } elseif (! is_array($attrs)) {
                    $attrs = [];
                }
                $vehicleNo = $attrs['vehicleNo'] ?? null;
                if ($vehicleNo) {
                    $vehicleNoLabel = (string) $vehicleNo;
                    $vehicleLabel = $vehicleNoLabel.' - '.$singleDeviceName;
                }
            }
        }

        $lastTime = $lastEpoch ? date('Y-m-d H:i:s', $lastEpoch) : 'N/A';

        $header = [
            'vehicleId' => $vehicleLabel,
            'deviceId' => $deviceIdLabel,
            'deviceUniqueId' => $deviceUniqueIdLabel,
            'vehicleNo' => $vehicleNoLabel,
            'duration' => $from.' - '.$to,
            'lastReport' => $lastTime,
            'lastLocation' => $lastLocation,
            'lastLocationLat' => $lastLat,
            'lastLocationLon' => $lastLon,
        ];

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    public function fetchVehicleActivityDb($request, $deviceIds)
    {
        return $this->fetchAssetActivityDb($request, $deviceIds);
    }

    public function fetchVehicleActivity($request, $deviceIds)
    {
        // Increase memory limit for this heavy report
        // Set to 1024M to handle large JSON responses from Traccar
        ini_set('memory_limit', '1024M');
        set_time_limit(300); // 5 minutes timeout

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        if (empty($deviceIds)) {
            return [];
        }

        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        // Get limit from request, default to 100
        $limitParam = (int) $request->input('limit', 100);

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
        ];

        // Sequential processing with smaller chunks to save memory
        // Process 1 device at a time to minimize peak memory usage
        $chunks = collect(array_chunk($deviceIds, 1));

        $singleDeviceName = 'Unknown';
        $limitReached = false;
        $currentRowCount = 0;

        $allRows = $chunks->map(function ($chunkIds) use ($headers, $baseUrl, $from, $to, &$singleDeviceName, &$limitReached, &$currentRowCount, $limitParam) {

            if ($limitReached) {
                return [];
            }

            // If we have collected enough rows across chunks (approx check)
            if ($limitParam > 0 && $currentRowCount >= $limitParam * 2) {
                $limitReached = true;

                return [];
            }

            $deviceQuery = collect($chunkIds)->map(function ($id) {
                return "deviceId={$id}";
            })->implode('&');
            $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

            try {
                $responses = Http::pool(fn (Pool $pool) => [
                    $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$fullQuery}"),
                    $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
                    $pool->as('summary')->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$fullQuery}"),
                ]);
            } catch (\Exception $e) {
                Log::error('fetchVehicleActivity chunk exception', ['error' => $e->getMessage()]);

                return [];
            }

            $routeData = ($responses['route']->ok()) ? $responses['route']->json() : [];
            $eventsData = ($responses['events']->ok()) ? $responses['events']->json() : [];
            $summaryData = ($responses['summary']->ok()) ? $responses['summary']->json() : [];

            $chunkRows = [];
            $chunkDeviceMap = [];

            // Process Summary to get device names
            if (is_array($summaryData)) {
                foreach ($summaryData as $s) {
                    if (isset($s['deviceId'])) {
                        $name = $s['deviceName'] ?? 'Device '.$s['deviceId'];
                        $chunkDeviceMap[$s['deviceId']] = $name;
                        if ($singleDeviceName === 'Unknown') {
                            $singleDeviceName = $name;
                        }
                    }
                }
            }

            // Process Route
            if (is_array($routeData)) {
                foreach ($routeData as $pos) {
                    $dId = $pos['deviceId'] ?? 0;
                    $time = $pos['fixTime'] ?? $pos['deviceTime'];
                    $dt = strtotime($time);
                    $attrs = $pos['attributes'] ?? [];

                    $lat = isset($pos['latitude']) ? round($pos['latitude'], 5) : 0;
                    $lon = isset($pos['longitude']) ? round($pos['longitude'], 5) : 0;
                    $power = $attrs['power'] ?? $attrs['battery'] ?? null;
                    if ($power) {
                        $power = round($power, 1).'V';
                    }

                    $fuel = $this->formatFuel($attrs);

                    // GSM Signal Formatting
                    $rssi = $attrs['rssi'] ?? null;
                    $gsm = 'No Signal';
                    if ($rssi !== null) {
                        if ($rssi >= -70) {
                            $gsm = 'Excellent';
                        } elseif ($rssi >= -85) {
                            $gsm = 'Good';
                        } elseif ($rssi >= -100) {
                            $gsm = 'Fair';
                        } elseif ($rssi >= -110) {
                            $gsm = 'Poor';
                        } else {
                            $gsm = 'Very Poor';
                        }
                    }

                    // GPS Signal Formatting
                    $sat = $attrs['sat'] ?? 0;
                    $gps = 'No Signal';
                    if ($sat > 0) {
                        if ($sat >= 10) {
                            $gps = 'Excellent';
                        } elseif ($sat >= 7) {
                            $gps = 'Good';
                        } elseif ($sat >= 4) {
                            $gps = 'Fair';
                        } else {
                            $gps = 'Poor';
                        }
                    }

                    // Ignition Formatting
                    $ign = $attrs['ignition'] ?? false;
                    $ignition = $ign ? 'ON' : 'OFF';

                    // Determine Status (Moving, Idling, Stopped)
                    $speedKnots = $pos['speed'] ?? 0;
                    $speedKph = round($speedKnots * 1.852, 0);

                    $status = 'Stopped';
                    if ($speedKph > 0) {
                        $status = 'Moving';
                    } elseif ($ign) {
                        $status = 'Idling';
                    }

                    $chunkRows[] = [
                        'key' => 0, // Will reindex later
                        'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                        'groupDate' => date('d-m-Y l', $dt),
                        'date' => date('d-m-Y', $dt),
                        'time' => date('H:i:s', $dt),
                        'status' => $status,
                        'lat' => $lat,
                        'lon' => $lon,
                        'location' => ($pos['address'] ?? null) ?: (($lat && $lon) ? ($lat.', '.$lon) : ''),
                        'direction' => $pos['course'] ?? 0,
                        'speed' => $speedKph.' km/h',
                        'gsm' => $gsm,
                        'gps' => $gps,
                        'power' => $power,
                        'ignition' => $ignition,
                        'fuel' => $fuel,
                        'isEvent' => false,
                        'rawType' => 'position',
                        'epoch' => $dt,
                    ];
                }
            }

            // Process Events
            if (is_array($eventsData)) {
                foreach ($eventsData as $evt) {
                    $dId = $evt['deviceId'] ?? 0;
                    $time = $evt['eventTime'] ?? $evt['serverTime'];
                    $dt = strtotime($time);
                    $attrs = $evt['attributes'] ?? [];

                    $chunkRows[] = [
                        'key' => 0,
                        'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                        'groupDate' => date('d-m-Y l', $dt),
                        'date' => date('d-m-Y', $dt),
                        'time' => date('H:i:s', $dt),
                        'status' => $this->formatEventDescription($evt),
                        'lat' => 0,
                        'lon' => 0,
                        'location' => '',
                        'direction' => 0,
                        'speed' => '0 km/h',
                        'gsm' => null,
                        'gps' => null,
                        'power' => null,
                        'ignition' => null,
                        'fuel' => null,
                        'isEvent' => true,
                        'rawType' => $evt['type'] ?? '',
                        'epoch' => $dt,
                    ];
                }
            }

            unset($routeData, $eventsData, $summaryData, $responses);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $currentRowCount += count($chunkRows);

            return $chunkRows;

        })->collapse()->values()->all();

        // Sort by Time
        usort($allRows, function ($a, $b) {
            return $a['epoch'] <=> $b['epoch'];
        });

        // Limit to last N records
        if ($limitParam > 0 && count($allRows) > $limitParam) {
            $allRows = array_slice($allRows, -$limitParam);
        }

        // Re-index keys
        foreach ($allRows as $index => &$row) {
            $row['key'] = $index;
        }
        unset($row);

        // Header Info
        $lastRow = end($allRows);
        $lastTime = $lastRow ? date('Y-m-d H:i:s', $lastRow['epoch']) : 'N/A';

        // Find last position
        $lastAddress = '';
        for ($i = count($allRows) - 1; $i >= 0; $i--) {
            if ($allRows[$i]['rawType'] === 'position') {
                $lastAddress = $allRows[$i]['location'];
                if (! $lastAddress) {
                    $lastAddress = $allRows[$i]['lat'].', '.$allRows[$i]['lon'];
                }
                break;
            }
        }

        $vehicleLabel = count($deviceIds) > 1 ? 'Multiple Vehicles ('.count($deviceIds).')' : $singleDeviceName;
        $deviceIdLabel = count($deviceIds) > 1 ? 'Multiple' : ($deviceIds[0] ?? 'N/A');

        $header = [
            'vehicleId' => $vehicleLabel,
            'deviceId' => $deviceIdLabel,
            'duration' => date('Y/m/d H:i', strtotime($from)).' - '.date('Y/m/d H:i', strtotime($to)),
            'lastReport' => $lastTime,
            'lastLocation' => $lastAddress,
        ];

        return [
            'header' => $header,
            'rows' => $allRows,
        ];
    }

    private function getMemoryLimitBytes()
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return -1;
        }
        $val = trim($limit);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    public function fetchIdlingReport($request, $deviceIds)
    {
        return $this->fetchIdlingReportDb($request, $deviceIds);
    }

    public function fetchIdlingReportDb($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        if (empty($deviceIds)) {
            return [];
        }

        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->pluck('name', 'id');
        $allIdlingEvents = [];

        foreach ($deviceIds as $deviceId) {
            $this->abortIfClientDisconnected();
            $deviceName = $tcDevices[$deviceId] ?? 'Unknown';

            $ignEvents = DB::connection('pgsql')->select("
                SELECT type, EXTRACT(EPOCH FROM eventtime) AS event_epoch
                FROM tc_events
                WHERE deviceid = ?
                  AND eventtime BETWEEN ? AND ?
                  AND type IN ('ignitionOn', 'ignitionOff')
                ORDER BY eventtime ASC
                LIMIT 400000
            ", [(int) $deviceId, $from, $to]);

            $tripIntervals = [];
            $tripStart = null;
            foreach ($ignEvents as $ev) {
                $evEpoch = isset($ev->event_epoch) ? (int) $ev->event_epoch : null;
                if ($ev->type === 'ignitionOn') {
                    if ($tripStart === null) {
                        $tripStart = $evEpoch;
                    }

                    continue;
                }

                if ($ev->type === 'ignitionOff' && $tripStart !== null) {
                    $tripEnd = $evEpoch;
                    if ($tripEnd > $tripStart) {
                        $tripIntervals[] = [
                            'startEpoch' => $tripStart,
                            'endEpoch' => $tripEnd,
                        ];
                    }
                    $tripStart = null;
                }
            }

            if ($tripStart !== null) {
                $toEpoch = \Carbon\Carbon::parse($to, 'UTC')->timestamp;
                if ($toEpoch > $tripStart) {
                    $tripIntervals[] = [
                        'startEpoch' => $tripStart,
                        'endEpoch' => $toEpoch,
                    ];
                }
            }

            $positions = DB::connection('pgsql')->select("
                SELECT EXTRACT(EPOCH FROM fixtime) AS fix_epoch, latitude, longitude, address, speed
                FROM tc_positions
                WHERE deviceid = ?
                  AND fixtime BETWEEN ? AND ?
                  AND (attributes::json->>'ignition')::boolean = true
                ORDER BY fixtime ASC
            ", [(int) $deviceId, $from, $to]);

            if (empty($positions)) {
                continue;
            }

            $currentStart = null;
            $lastIdlePos = null;
            $intervalIdx = 0;
            $tripIntervalCount = count($tripIntervals);

            foreach ($positions as $pos) {
                $ts = isset($pos->fix_epoch) ? (int) $pos->fix_epoch : null;
                if ($ts === null) {
                    continue;
                }

                while ($intervalIdx < $tripIntervalCount && $ts > $tripIntervals[$intervalIdx]['endEpoch']) {
                    if ($currentStart && $lastIdlePos) {
                        $startTs = (int) ($currentStart->fix_epoch ?? 0);
                        $endTs = (int) ($lastIdlePos->fix_epoch ?? 0);
                        $int = $tripIntervals[$intervalIdx];
                        $startTs = max($startTs, $int['startEpoch']);
                        $endTs = min($endTs, $int['endEpoch']);
                        if ($endTs > $startTs) {
                            $duration = $endTs - $startTs;
                            $tripStartEpoch = $int['startEpoch'];
                            $tripEndEpoch = $int['endEpoch'];
                            $tripDurationSeconds = $tripEndEpoch - $tripStartEpoch;
                            $allIdlingEvents[] = [
                                'vehicle' => $deviceName,
                                'deviceId' => (int) $deviceId,
                                'date' => gmdate('d-m-Y', $startTs),
                                'startTime' => gmdate('H:i:s', $startTs),
                                'endTime' => gmdate('H:i:s', $endTs),
                                'durationSeconds' => $duration,
                                'durationFormatted' => $this->formatDuration($duration),
                                'tripStartTime' => gmdate('H:i:s', $tripStartEpoch),
                                'tripEndTime' => gmdate('H:i:s', $tripEndEpoch),
                                'tripDurationSeconds' => $tripDurationSeconds,
                                'tripDurationFormatted' => $this->formatDuration($tripDurationSeconds),
                                'location' => $currentStart->address ?? (($currentStart->latitude ?? 0).', '.($currentStart->longitude ?? 0)),
                                'lat' => $currentStart->latitude ?? null,
                                'lon' => $currentStart->longitude ?? null,
                                'startEpoch' => $startTs,
                                'endEpoch' => $endTs,
                                'tripStartEpoch' => $tripStartEpoch,
                                'tripEndEpoch' => $tripEndEpoch,
                            ];
                        }
                    }
                    $currentStart = null;
                    $lastIdlePos = null;
                    $intervalIdx++;
                }

                if ($intervalIdx >= $tripIntervalCount) {
                    break;
                }
                $activeInterval = $tripIntervals[$intervalIdx];
                if ($ts < $activeInterval['startEpoch']) {
                    continue;
                }

                $speed = isset($pos->speed) ? (float) $pos->speed : 0.0;
                $isIdle = $speed < 1;

                if ($isIdle) {
                    if ($currentStart === null) {
                        $currentStart = $pos;
                    }
                    $lastIdlePos = $pos;

                    continue;
                }

                if ($currentStart && $lastIdlePos) {
                    $startTs = (int) ($currentStart->fix_epoch ?? 0);
                    $endTs = (int) ($lastIdlePos->fix_epoch ?? 0);
                    if ($endTs > $startTs) {
                        $duration = $endTs - $startTs;
                        if ($duration > 0) {
                            $int = $activeInterval;
                            $startTs = max($startTs, $int['startEpoch']);
                            $endTs = min($endTs, $int['endEpoch']);
                            if ($endTs <= $startTs) {
                                $currentStart = null;
                                $lastIdlePos = null;

                                continue;
                            }
                            $duration = $endTs - $startTs;
                            $tripStartEpoch = $int['startEpoch'];
                            $tripEndEpoch = $int['endEpoch'];
                            $tripDurationSeconds = $tripEndEpoch - $tripStartEpoch;
                            $tripDurationFormatted = $this->formatDuration($tripDurationSeconds);

                            $allIdlingEvents[] = [
                                'vehicle' => $deviceName,
                                'deviceId' => (int) $deviceId,
                                'date' => gmdate('d-m-Y', $startTs),
                                'startTime' => gmdate('H:i:s', $startTs),
                                'endTime' => gmdate('H:i:s', $endTs),
                                'durationSeconds' => $duration,
                                'durationFormatted' => $this->formatDuration($duration),
                                'tripStartTime' => gmdate('H:i:s', $tripStartEpoch),
                                'tripEndTime' => gmdate('H:i:s', $tripEndEpoch),
                                'tripDurationSeconds' => $tripDurationSeconds,
                                'tripDurationFormatted' => $tripDurationFormatted,
                                'location' => $currentStart->address ?? (($currentStart->latitude ?? 0).', '.($currentStart->longitude ?? 0)),
                                'lat' => $currentStart->latitude ?? null,
                                'lon' => $currentStart->longitude ?? null,
                                'startEpoch' => $startTs,
                                'endEpoch' => $endTs,
                                'tripStartEpoch' => $tripStartEpoch,
                                'tripEndEpoch' => $tripEndEpoch,
                            ];
                        }
                    }
                }
                $currentStart = null;
                $lastIdlePos = null;
            }

            if ($currentStart && $lastIdlePos) {
                $startTs = (int) ($currentStart->fix_epoch ?? 0);
                $endTs = (int) ($lastIdlePos->fix_epoch ?? 0);
                if ($endTs > $startTs) {
                    $duration = $endTs - $startTs;
                    if ($duration > 0) {
                        if ($intervalIdx >= $tripIntervalCount) {
                            continue;
                        }
                        $int = $tripIntervals[$intervalIdx];
                        $startTs = max($startTs, $int['startEpoch']);
                        $endTs = min($endTs, $int['endEpoch']);
                        if ($endTs <= $startTs) {
                            continue;
                        }
                        $duration = $endTs - $startTs;
                        $tripStartEpoch = $int['startEpoch'];
                        $tripEndEpoch = $int['endEpoch'];
                        $tripDurationSeconds = $tripEndEpoch - $tripStartEpoch;
                        $tripDurationFormatted = $this->formatDuration($tripDurationSeconds);

                        $allIdlingEvents[] = [
                            'vehicle' => $deviceName,
                            'deviceId' => (int) $deviceId,
                            'date' => gmdate('d-m-Y', $startTs),
                            'startTime' => gmdate('H:i:s', $startTs),
                            'endTime' => gmdate('H:i:s', $endTs),
                            'durationSeconds' => $duration,
                            'durationFormatted' => $this->formatDuration($duration),
                            'tripStartTime' => gmdate('H:i:s', $tripStartEpoch),
                            'tripEndTime' => gmdate('H:i:s', $tripEndEpoch),
                            'tripDurationSeconds' => $tripDurationSeconds,
                            'tripDurationFormatted' => $tripDurationFormatted,
                            'location' => $currentStart->address ?? (($currentStart->latitude ?? 0).', '.($currentStart->longitude ?? 0)),
                            'lat' => $currentStart->latitude ?? null,
                            'lon' => $currentStart->longitude ?? null,
                            'startEpoch' => $startTs,
                            'endEpoch' => $endTs,
                            'tripStartEpoch' => $tripStartEpoch,
                            'tripEndEpoch' => $tripEndEpoch,
                        ];
                    }
                }
            }
        }

        // Sort by start time
        usort($allIdlingEvents, function ($a, $b) {
            return $b['startEpoch'] <=> $a['startEpoch'];
        });

        return $allIdlingEvents;
    }

    public function fetchIdlingReportOld($request, $deviceIds)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $sessionId = $request->user()->traccarSession ?? session('cookie');
        if (empty($deviceIds)) {
            return [];
        }

        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
        ];

        // Process sequentially to save memory
        $chunks = collect(array_chunk($deviceIds, 5)); // Process 5 devices at a time
        $allIdlingEvents = [];

        foreach ($chunks as $chunkIds) {
            $deviceQuery = collect($chunkIds)->map(function ($id) {
                return "deviceId={$id}";
            })->implode('&');
            $queryString = "{$deviceQuery}&from={$from}&to={$to}";

            try {
                $responses = Http::pool(fn (Pool $pool) => [
                    $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$queryString}"),
                    $pool->as('devices')->withHeaders($headers)->get("{$baseUrl}/api/devices?{$deviceQuery}"), // To get names
                ]);
            } catch (\Exception $e) {
                Log::error('fetchIdlingReport chunk exception', ['error' => $e->getMessage()]);

                continue;
            }

            $routeData = ($responses['route']->ok()) ? $responses['route']->json() : [];
            $devicesData = ($responses['devices']->ok()) ? $responses['devices']->json() : [];

            $deviceMap = [];
            foreach ($devicesData as $d) {
                $deviceMap[$d['id']] = $d['name'];
            }

            // Group route data by device
            $groupedRoute = [];
            foreach ($routeData as $pos) {
                $dId = $pos['deviceId'];
                if (! isset($groupedRoute[$dId])) {
                    $groupedRoute[$dId] = [];
                }
                $groupedRoute[$dId][] = $pos;
            }

            // Analyze for idling
            foreach ($groupedRoute as $dId => $positions) {
                // Sort by time just in case
                usort($positions, function ($a, $b) {
                    return strtotime($a['fixTime']) - strtotime($b['fixTime']);
                });

                $currentStart = null;
                $deviceName = $deviceMap[$dId] ?? 'Unknown';

                foreach ($positions as $index => $pos) {
                    $speedKnots = $pos['speed'] ?? 0;
                    $speedKph = $speedKnots * 1.852;
                    $attrs = $pos['attributes'] ?? [];
                    $ignition = $attrs['ignition'] ?? false;

                    // Idling condition: Ignition ON AND Speed approx 0 (e.g., < 2 km/h)
                    $isIdling = $ignition && ($speedKph < 2);

                    if ($isIdling) {
                        if ($currentStart === null) {
                            $currentStart = $pos;
                        }
                    } else {
                        if ($currentStart !== null) {
                            // End of idling session
                            $startDt = strtotime($currentStart['fixTime']);
                            $endDt = strtotime($positions[$index - 1]['fixTime']); // Use previous point as end
                            $duration = $endDt - $startDt;

                            if ($duration > 0) {
                                $allIdlingEvents[] = [
                                    'vehicle' => $deviceName,
                                    'deviceId' => $dId,
                                    'date' => date('d-m-Y', $startDt),
                                    'startTime' => date('H:i:s', $startDt),
                                    'endTime' => date('H:i:s', $endDt),
                                    'durationSeconds' => $duration,
                                    'durationFormatted' => $this->formatDuration($duration),
                                    'location' => $currentStart['address'] ?? ($currentStart['latitude'].', '.$currentStart['longitude']),
                                    'lat' => $currentStart['latitude'],
                                    'lon' => $currentStart['longitude'],
                                    'startEpoch' => $startDt,
                                ];
                            }
                            $currentStart = null;
                        }
                    }
                }

                // Check if still idling at the end of the list
                if ($currentStart !== null) {
                    $lastPos = end($positions);
                    $startDt = strtotime($currentStart['fixTime']);
                    $endDt = strtotime($lastPos['fixTime']);
                    $duration = $endDt - $startDt;

                    if ($duration > 0) {
                        $allIdlingEvents[] = [
                            'vehicle' => $deviceName,
                            'deviceId' => $dId,
                            'date' => date('d-m-Y', $startDt),
                            'startTime' => date('H:i:s', $startDt),
                            'endTime' => date('H:i:s', $endDt),
                            'durationSeconds' => $duration,
                            'durationFormatted' => $this->formatDuration($duration),
                            'location' => $currentStart['address'] ?? ($currentStart['latitude'].', '.$currentStart['longitude']),
                            'lat' => $currentStart['latitude'],
                            'lon' => $currentStart['longitude'],
                            'startEpoch' => $startDt,
                        ];
                    }
                }
            }

            // Cleanup
            unset($routeData, $groupedRoute, $responses);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Sort by start time
        usort($allIdlingEvents, function ($a, $b) {
            return $b['startEpoch'] <=> $a['startEpoch'];
        });

        return $allIdlingEvents;
    }

    private function formatDuration($seconds)
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        $str = '';
        if ($h > 0) {
            $str .= $h.'h ';
        }
        if ($m > 0) {
            $str .= $m.'m ';
        }
        $str .= $s.'s';

        return trim($str);
    }

    private function formatFuel($attrs)
    {
        if (empty($attrs)) {
            return null;
        }

        $lower = array_change_key_case($attrs, CASE_LOWER);

        $getVal = function ($keys) use ($attrs, $lower) {
            foreach ((array) $keys as $k) {
                if (isset($attrs[$k])) {
                    return $attrs[$k];
                }
                $lk = strtolower($k);
                if (isset($lower[$lk])) {
                    return $lower[$lk];
                }
            }

            return null;
        };

        $num = function ($v) {
            return (is_numeric($v)) ? (float) $v : null;
        };

        // 1. Resolve Percent
        $percent = null;
        $percentCandidates = ['CAN_FuelPercentage_89', 'fuelPercent', 'fuelLevel', 'fuel_percent', 'io89', 'io48'];
        foreach ($percentCandidates as $key) {
            $val = $num($getVal($key));
            if ($val !== null && $val > -1) {
                $percent = max(0, min(100, round($val)));
                break;
            }
        }

        // 2. Resolve Liters
        $liters = null;
        $litersCandidates = ['CAN_FuelLeter_84', 'OBD_FuelLeter_48', 'fuelLiter', 'fuelLiters', 'fuel', 'io84'];
        foreach ($litersCandidates as $key) {
            $val = $num($getVal($key));
            if ($val !== null && $val > -1) {
                $liters = round($val * 10) / 10;
                break;
            }
        }

        // 3. Raw Analog Fallback
        $raw = null;
        if ($percent === null && $liters === null) {
            $rawCandidates = [
                'io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243',
                'fuelRaw', 'analog1', 'analog2', 'analog3', 'adc1', 'adc2', 'adc3',
            ];
            foreach ($rawCandidates as $key) {
                $val = $num($getVal($key));
                if ($val !== null && $val > -1) {
                    $raw = $val;
                    break;
                }
            }

            // Generic 'fuel' scan
            if ($raw === null) {
                foreach ($lower as $k => $v) {
                    if (strpos($k, 'fuel') !== false) {
                        $n = $num($v);
                        if ($n !== null && $n > -1) {
                            $raw = $n;
                            break;
                        }
                    }
                }
            }
        }

        // Return formatted string
        if ($liters !== null) {
            return $liters.' L';
        }
        if ($percent !== null) {
            return $percent.'%';
        }
        if ($raw !== null) {
            if ($raw >= 0 && $raw <= 100) {
                return round($raw).'%';
            }

            return (string) $raw;
        }

        return null;
    }

    private function formatEventDescription($event)
    {
        $isObj = is_object($event);
        $type = $isObj ? ($event->type ?? 'unknown') : ($event['type'] ?? 'unknown');
        $deviceName = $isObj ? ($event->deviceName ?? 'Device') : ($event['deviceName'] ?? 'Device');
        $attributes = $isObj ? ($event->attributes ?? []) : ($event['attributes'] ?? []);
        // Handle JSON string attributes if necessary
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true) ?? [];
        }

        switch ($type) {
            case 'overspeed':
                $speed = $isObj ? ($event->speed ?? 0) : ($event['speed'] ?? 0);
                $speedKph = round($speed * 1.852, 1);

                return "Exceeded speed limit ({$speedKph} km/h)";

            case 'deviceOverspeed':
                $speed = $isObj ? ($event->speed ?? 0) : ($event['speed'] ?? 0);
                $speedKph = round($speed * 1.852, 1);

                return "Device overspeed ({$speedKph} km/h)";

            case 'harshBraking':
                return 'Harsh braking detected';

            case 'harshAcceleration':
                return 'Harsh acceleration detected';

            case 'ignitionOn':
                return 'Ignition turned ON';

            case 'ignitionOff':
                return 'Ignition turned OFF';

            case 'geofenceEnter':
                return 'Entered geofence';

            case 'geofenceExit':
                return 'Exited geofence';

            case 'deviceStopped':
                return 'Vehicle stopped';

            case 'deviceOnline':
                return 'Device online';

            case 'deviceOffline':
                return 'Device offline';

            case 'deviceUnknown':
                return 'Device status unknown';

            case 'deviceInactive':
                return 'Vehicle inactive';

            case 'deviceMoving':
                return 'Vehicle moving';

            case 'alarm':
                $alarmKey = $attributes['alarm'] ?? 'general';

                return 'Alarm: '.ucfirst($alarmKey);

            default:
                $label = ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $type));

                return preg_replace('/\bDevice\b/i', 'Vehicle', $label);
        }
    }

    public function fetchUtilisationReport($request, $deviceId)
    {
        Log::info("fetchUtilisationReport started for device {$deviceId}");
        $type = $request->type ?? 'Movement';
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {

            Log::error('ReportService: Tracking server host URL is not configured.');
            $vehicleRec = Devices::accessibleByUser($request->user())->with('tcDevice')->where('device_id', $deviceId)->first();

            $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;
            $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;
            $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
            if (is_string($attributes)) {
                $attributes = json_decode($attributes, true);
            }

            $vehicleName = $tcDevice->name ?? 'Unknown';
            $vehicleNo = $attributes['vehicleNo'] ?? null;

            if ($vehicleNo) {
                $vehicleIdDisplay = "{$vehicleNo} - {$vehicleName}";
            } else {
                $vehicleIdDisplay = $vehicleName;
            }

            $totalDays = max(1, round((strtotime($toStr) - strtotime($request->from_date)) / (60 * 60 * 24)) + 1);

            return [
                'summary' => [
                    'vehicleIdDisplay' => $vehicleIdDisplay,
                    'deviceId' => $uniqueId,
                    'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                    'totalDays' => $totalDays,
                ],
                'rows' => [],
            ];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $fromIso = $request->from_date.'T00:00:00Z';
        $toIso = (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $request->to_date) ? $request->to_date.' 23:59:59' : $request->to_date);
        $toIso = date('Y-m-d\\TH:i:00\\Z', strtotime($toIso));

        $tripResp = null;
        $stopResp = null;
        $eventResp = null;

        $allTrips = [];
        $allStops = [];
        $allEvents = [];

        if ($type !== 'Engine Hours') {
            $tripResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/trips", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso,
            ]);
            $stopResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/stops", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso,
            ]);
            $allTrips = is_array($tripResp?->json()) ? $tripResp->json() : [];
            $allStops = is_array($stopResp?->json()) ? $stopResp->json() : [];
        } else {
            $eventResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/events", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso,
                'type' => ['ignitionOn', 'ignitionOff'],
            ]);
            $allEvents = is_array($eventResp?->json()) ? $eventResp->json() : [];
        }

        Log::info('Fetched Total Data', [
            'trips' => is_array($allTrips) ? count($allTrips) : 0,
            'stops' => is_array($allStops) ? count($allStops) : 0,
            'events' => is_array($allEvents) ? count($allEvents) : 0,
        ]);

        $foundVehicleName = null;
        if (! $foundVehicleName && count($allTrips) > 0) {
            $first = $allTrips[0];
            if (isset($first['deviceName'])) {
                $foundVehicleName = $first['deviceName'];
            }
        }
        unset($responses);

        $vehicleRec = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
        $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;

        $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;

        $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }

        $vehicleName = $tcDevice->name ?? 'Unknown';
        if ($vehicleName === 'Unknown' && $foundVehicleName) {
            $vehicleName = $foundVehicleName;
        }

        // Try to find vehicle no in attributes
        $vehicleNo = $attributes['vehicleNo'] ?? null;

        if ($vehicleNo) {
            $vehicleIdDisplay = "{$vehicleNo} - {$vehicleName}";
        } else {
            $vehicleIdDisplay = $vehicleName;
        }

        $totalDays = max(1, round((strtotime($toStr) - strtotime($request->from_date)) / (60 * 60 * 24)) + 1);

        return [
            'summary' => [
                'vehicleIdDisplay' => $vehicleIdDisplay,
                'deviceId' => $uniqueId,
                'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                'totalDays' => $totalDays,
            ],
            'raw' => [
                'trips' => $allTrips,
                'stops' => $allStops,
                'events' => $allEvents,
            ],
            'type' => $type,
        ];
    }

    public function fetchUtilisationReportDb($request, $deviceId)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        $type = $request->type ?? 'Movement';
        $fromStr = $request->from_date;
        $toStr = $request->to_date;

        // Ensure proper timestamps
        $fromTs = strtotime($fromStr.' 00:00:00');
        $toTs = strtotime($toStr.' 23:59:59');
        $fromIso = date('Y-m-d H:i:s', $fromTs);
        $toIso = date('Y-m-d H:i:s', $toTs);

        // Fetch Device Info
        $vehicleRec = Devices::with('tcDevice')->whereHas('tcDevice', function ($q) use ($deviceId) {
            $q->where('id', (int) $deviceId);
        })->first();
        if (! $vehicleRec) {
            $vehicleRec = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
        }
        $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;
        $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;
        $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }
        $vehicleName = $tcDevice->name ?? 'Unknown';
        $vehicleNo = $attributes['vehicleNo'] ?? null;
        $vehicleIdDisplay = $vehicleNo ? "{$vehicleNo} - {$vehicleName}" : $vehicleName;
        $totalDays = max(1, round(($toTs - $fromTs) / (60 * 60 * 24)));

        $rows = [];
        $startDate = new \DateTime($fromStr);
        $endDate = new \DateTime($toStr);
        $endDate->modify('+1 day');
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);

        if ($type !== 'Engine Hours') {
            // Movement Report — distance from telemetry odometer trips (same as other reports)
            $allTrips = $this->fetchTripsDb((int) $deviceId, $fromIso, $toIso);
            $distanceByDay = $this->tripDistanceMetersByDay($allTrips);

            $positions = DB::connection('pgsql')
                ->table('tc_positions')
                ->select('fixtime', 'latitude', 'longitude', 'speed')
                ->where('deviceid', (int) $deviceId)
                ->whereBetween('fixtime', [$fromIso, $toIso])
                ->orderBy('fixtime')
                ->limit(500000) // Increased limit for larger ranges
                ->get()
                ->groupBy(function ($item) {
                    return substr($item->fixtime, 0, 10);
                });

            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');
                $dayStart = strtotime($dateStr.' 00:00:00') * 1000;
                $dayEnd = strtotime($dateStr.' 23:59:59') * 1000;

                $dayPositions = isset($positions[$dateStr]) ? $positions[$dateStr] : [];

                $tripMs = 0;
                $distance = floatval($distanceByDay[$dateStr] ?? 0);
                $totalMs = 0;
                $hourlyMoveMs = array_fill(0, 24, 0);

                $count = count($dayPositions);
                if ($count > 1) {
                    $firstT = strtotime($dayPositions[0]->fixtime) * 1000;
                    $lastT = strtotime($dayPositions[$count - 1]->fixtime) * 1000;
                    $totalMs = max(0, $lastT - $firstT);

                    for ($i = 1; $i < $count; $i++) {
                        $prev = $dayPositions[$i - 1];
                        $cur = $dayPositions[$i];

                        $pt = strtotime($prev->fixtime) * 1000;
                        $ct = strtotime($cur->fixtime) * 1000;
                        $dtMs = max(0, $ct - $pt);

                        $speed = $prev->speed ?? 0;
                        $kmh = $speed * 1.852; // knots to kmh

                        // Movement logic
                        if ($kmh > 5) {
                            $tripMs += $dtMs;
                            $midTime = $pt + ($dtMs / 2);
                            $h = (int) date('H', $midTime / 1000);
                            if (isset($hourlyMoveMs[$h])) {
                                $hourlyMoveMs[$h] += $dtMs;
                            }
                        }
                    }
                }

                $idleMs = max(0, $totalMs - $tripMs);
                $usagePct = $totalMs > 0 ? round(($tripMs / $totalMs) * 100) : 0;

                // Blue boxes logic
                $hours = array_fill(0, 24, false);
                $totalMoveHours = ceil($tripMs / 3600000);
                if ($totalMoveHours > 0) {
                    arsort($hourlyMoveMs);
                    $topHours = array_keys(array_slice($hourlyMoveMs, 0, $totalMoveHours, true));
                    foreach ($topHours as $h) {
                        $hours[$h] = true;
                    }
                }

                $rows[] = [
                    'day' => $dt->format('l d/m/Y'),
                    'usage' => $usagePct.'%',
                    'move' => $this->formatDurationMs($tripMs),
                    'idle' => $this->formatDurationMs($idleMs),
                    'dist' => round($distance / 1000, 2).' KM',
                    'hours' => $hours,
                ];
            }

        } else {
            // Engine Hours Report (Events based)
            $events = DB::connection('pgsql')
                ->table('tc_events')
                ->select('type', 'eventtime')
                ->where('deviceid', (int) $deviceId)
                ->whereBetween('eventtime', [$fromIso, $toIso])
                ->whereIn('type', ['ignitionOn', 'ignitionOff'])
                ->orderBy('eventtime')
                ->limit(200000)
                ->get();

            // Build intervals
            $intervals = [];
            $start = null;
            foreach ($events as $ev) {
                if ($ev->type === 'ignitionOn') {
                    $start = $ev->eventtime;
                } elseif ($ev->type === 'ignitionOff' && $start) {
                    $intervals[] = ['start' => strtotime($start) * 1000, 'end' => strtotime($ev->eventtime) * 1000];
                    $start = null;
                }
            }

            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');
                $dayStart = strtotime($dateStr.' 00:00:00') * 1000;
                $dayEnd = strtotime($dateStr.' 23:59:59') * 1000;

                $engineMs = 0;
                $hourlyEngineMs = array_fill(0, 24, 0);

                // Track min start and max end for "span" calculation (Usage/Idle)
                $minStart = null;
                $maxEnd = null;

                foreach ($intervals as $int) {
                    $s = $int['start'];
                    $e = $int['end'];

                    if ($e <= $dayStart || $s >= $dayEnd) {
                        continue;
                    }

                    $os = max($s, $dayStart);
                    $oe = min($e, $dayEnd);

                    if ($oe > $os) {
                        $diff = $oe - $os;
                        $engineMs += $diff;

                        // Update span
                        if ($minStart === null || $os < $minStart) {
                            $minStart = $os;
                        }
                        if ($maxEnd === null || $oe > $maxEnd) {
                            $maxEnd = $oe;
                        }

                        // Hourly distribution
                        $sh = (int) date('H', $os / 1000);
                        $eh = (int) date('H', $oe / 1000);

                        // Simple distribution: if it spans multiple hours, we might just add to start hour or split it.
                        // For accuracy, let's split it.
                        for ($h = $sh; $h <= $eh; $h++) {
                            $hStart = strtotime($dateStr." $h:00:00") * 1000;
                            $hEnd = strtotime($dateStr." $h:59:59") * 1000 + 1000; // end of hour

                            $hos = max($os, $hStart);
                            $hoe = min($oe, $hEnd);

                            if ($hoe > $hos) {
                                $hourlyEngineMs[$h] += ($hoe - $hos);
                            }
                        }
                    }
                }

                // Usage & Idle Logic (Consistent with Movement Report)
                $totalMs = 0;
                if ($minStart !== null && $maxEnd !== null) {
                    $totalMs = max(0, $maxEnd - $minStart);
                }

                $idleMs = max(0, $totalMs - $engineMs);
                $usagePct = $totalMs > 0 ? round(($engineMs / $totalMs) * 100) : 0;

                // Blue boxes logic (Quantity based)
                $hours = array_fill(0, 24, false);
                $totalEngineHours = ceil($engineMs / 3600000);

                if ($totalEngineHours > 0) {
                    arsort($hourlyEngineMs);
                    $topHours = array_keys(array_slice($hourlyEngineMs, 0, $totalEngineHours, true));
                    foreach ($topHours as $h) {
                        $hours[$h] = true;
                    }
                }

                $rows[] = [
                    'day' => $dt->format('l d/m/Y'),
                    'usage' => $usagePct.'%',
                    'move' => $this->formatDurationMs($engineMs),
                    'idle' => $this->formatDurationMs($idleMs),
                    'dist' => '0 KM',
                    'hours' => $hours,
                ];
            }
        }

        return [
            'summary' => [
                'vehicleIdDisplay' => $vehicleIdDisplay,
                'deviceId' => $uniqueId,
                'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                'totalDays' => $totalDays,
            ],
            'rows' => $rows,
            'type' => $type,
        ];
    }

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function formatDurationMs($ms)
    {
        $hours = floor($ms / 3600000);
        $minutes = floor(($ms % 3600000) / 60000);

        return "{$hours} hours {$minutes} minutes";
    }

    private function idleSecondsByDevice(array $deviceIds, string $fromIso, string $toIso, int $capSeconds = 900, float $speedThreshold = 1.0)
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return collect();
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH p AS (
                    SELECT
                        deviceid,
                        fixtime,
                        LEAD(fixtime) OVER (PARTITION BY deviceid ORDER BY fixtime) AS next_time,
                        speed,
                        (CAST(attributes AS json)->>'ignition') = 'true' AS ignition
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                )
                SELECT
                    deviceid,
                    SUM(
                        CASE
                            WHEN ignition AND speed < ? AND next_time IS NOT NULL
                                THEN LEAST(EXTRACT(EPOCH FROM (next_time - fixtime)), ?)
                            ELSE 0
                        END
                    ) AS idle_seconds
                FROM p
                GROUP BY deviceid
            ", [$fromIso, $toIso, $speedThreshold, $capSeconds]);

            return collect($rows)->keyBy('deviceid');
        } catch (\Throwable $e) {
            Log::error('idleSecondsByDevice query failed: '.$e->getMessage());

            return collect();
        }
    }

    private function idleSecondsByDeviceDay(array $deviceIds, string $fromIso, string $toIso, int $capSeconds = 900, float $speedThreshold = 1.0)
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return collect();
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH p AS (
                    SELECT
                        deviceid,
                        date(fixtime) AS day,
                        fixtime,
                        LEAD(fixtime) OVER (PARTITION BY deviceid, date(fixtime) ORDER BY fixtime) AS next_time,
                        speed,
                        (CAST(attributes AS json)->>'ignition') = 'true' AS ignition
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                )
                SELECT
                    deviceid,
                    day,
                    SUM(
                        CASE
                            WHEN ignition AND speed < ? AND next_time IS NOT NULL
                                THEN LEAST(EXTRACT(EPOCH FROM (next_time - fixtime)), ?)
                            ELSE 0
                        END
                    ) AS idle_seconds
                FROM p
                GROUP BY deviceid, day
            ", [$fromIso, $toIso, $speedThreshold, $capSeconds]);

            return collect($rows)->groupBy('deviceid')->map(function ($group) {
                return collect($group)->mapWithKeys(function ($r) {
                    $day = is_object($r) ? ($r->day ?? null) : ($r['day'] ?? null);
                    $secs = is_object($r) ? ($r->idle_seconds ?? 0) : ($r['idle_seconds'] ?? 0);

                    return [$day => floatval($secs)];
                });
            });
        } catch (\Throwable $e) {
            Log::error('idleSecondsByDeviceDay query failed: '.$e->getMessage());

            return collect();
        }
    }

    private function idleSecondsByDeviceMonth(array $deviceIds, string $fromIso, string $toIso, int $capSeconds = 900, float $speedThreshold = 1.0)
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return collect();
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH p AS (
                    SELECT
                        deviceid,
                        to_char(date_trunc('month', fixtime), 'YYYY-MM') AS month,
                        fixtime,
                        LEAD(fixtime) OVER (PARTITION BY deviceid, date_trunc('month', fixtime) ORDER BY fixtime) AS next_time,
                        speed,
                        (CAST(attributes AS json)->>'ignition') = 'true' AS ignition
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                )
                SELECT
                    deviceid,
                    month,
                    SUM(
                        CASE
                            WHEN ignition AND speed < ? AND next_time IS NOT NULL
                                THEN LEAST(EXTRACT(EPOCH FROM (next_time - fixtime)), ?)
                            ELSE 0
                        END
                    ) AS idle_seconds
                FROM p
                GROUP BY deviceid, month
            ", [$fromIso, $toIso, $speedThreshold, $capSeconds]);

            return collect($rows)->groupBy('deviceid')->map(function ($group) {
                return collect($group)->mapWithKeys(function ($r) {
                    $month = is_object($r) ? ($r->month ?? null) : ($r['month'] ?? null);
                    $secs = is_object($r) ? ($r->idle_seconds ?? 0) : ($r['idle_seconds'] ?? 0);

                    return [$month => floatval($secs)];
                });
            });
        } catch (\Throwable $e) {
            Log::error('idleSecondsByDeviceMonth query failed: '.$e->getMessage());

            return collect();
        }
    }

    private function tankCapacityLitresByDeviceIds(array $deviceIds): array
    {
        $caps = [];
        if (empty($deviceIds)) {
            return $caps;
        }

        $devices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get(['id', 'attributes']);
        foreach ($devices as $dev) {
            $attrs = is_string($dev->attributes) ? json_decode($dev->attributes, true) : (array) $dev->attributes;
            $cap = floatval($attrs['fuelTankCapacity'] ?? 0);
            if ($cap <= 0) {
                $cap = 50;
            }
            $caps[intval($dev->id)] = $cap;
        }

        return $caps;
    }

    private function fuelDropPctByDevice(array $deviceIds, string $fromIso, string $toIso): array
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return [];
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH pos_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        COALESCE(
                            NULLIF(CAST(attributes AS json)->>'io89', ''),
                            NULLIF(CAST(attributes AS json)->>'CAN_FuelPercentage_89', ''),
                            NULLIF(CAST(attributes AS json)->>'io48', ''),
                            NULLIF(CAST(attributes AS json)->>'io16', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelLevel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel_level', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercent', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercentage', '')
                        ) AS raw_fuel
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                ),
                clean_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        CAST(raw_fuel AS FLOAT) as fuel_level
                    FROM pos_data
                    WHERE raw_fuel ~ '^[0-9]+(\\.[0-9]+)?$'
                      AND CAST(raw_fuel AS FLOAT) BETWEEN 0 AND 100
                ),
                pos_with_prev AS (
                    SELECT
                        deviceid,
                        fixtime,
                        fuel_level,
                        LAG(fuel_level) OVER (PARTITION BY deviceid ORDER BY fixtime) as prev_fuel_level
                    FROM clean_data
                )
                SELECT
                    deviceid,
                    SUM(CASE WHEN prev_fuel_level > fuel_level THEN prev_fuel_level - fuel_level ELSE 0 END) as total_drop_pct
                FROM pos_with_prev
                GROUP BY deviceid
            ", [$fromIso, $toIso]);

            $out = [];
            foreach ($rows as $r) {
                $out[intval($r->deviceid)] = floatval($r->total_drop_pct ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('fuelDropPctByDevice query failed: '.$e->getMessage());

            return [];
        }
    }

    private function fuelDropPctByDeviceDay(array $deviceIds, string $fromIso, string $toIso): array
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return [];
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH pos_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        date(fixtime) AS day,
                        COALESCE(
                            NULLIF(CAST(attributes AS json)->>'io89', ''),
                            NULLIF(CAST(attributes AS json)->>'CAN_FuelPercentage_89', ''),
                            NULLIF(CAST(attributes AS json)->>'io48', ''),
                            NULLIF(CAST(attributes AS json)->>'io16', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelLevel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel_level', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercent', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercentage', '')
                        ) AS raw_fuel
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                ),
                clean_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        day,
                        CAST(raw_fuel AS FLOAT) as fuel_level
                    FROM pos_data
                    WHERE raw_fuel ~ '^[0-9]+(\\.[0-9]+)?$'
                      AND CAST(raw_fuel AS FLOAT) BETWEEN 0 AND 100
                ),
                pos_with_prev AS (
                    SELECT
                        deviceid,
                        day,
                        fixtime,
                        fuel_level,
                        LAG(fuel_level) OVER (PARTITION BY deviceid, day ORDER BY fixtime) as prev_fuel_level
                    FROM clean_data
                )
                SELECT
                    deviceid,
                    day,
                    SUM(CASE WHEN prev_fuel_level > fuel_level THEN prev_fuel_level - fuel_level ELSE 0 END) as total_drop_pct
                FROM pos_with_prev
                GROUP BY deviceid, day
            ", [$fromIso, $toIso]);

            $out = [];
            foreach ($rows as $r) {
                $did = intval($r->deviceid);
                $day = (string) $r->day;
                $out[$did] ??= [];
                $out[$did][$day] = floatval($r->total_drop_pct ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('fuelDropPctByDeviceDay query failed: '.$e->getMessage());

            return [];
        }
    }

    private function fuelDropPctByDeviceMonth(array $deviceIds, string $fromIso, string $toIso): array
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return [];
        }

        try {
            $rows = DB::connection('pgsql')->select("
                WITH pos_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        to_char(date_trunc('month', fixtime), 'YYYY-MM') AS month,
                        COALESCE(
                            NULLIF(CAST(attributes AS json)->>'io89', ''),
                            NULLIF(CAST(attributes AS json)->>'CAN_FuelPercentage_89', ''),
                            NULLIF(CAST(attributes AS json)->>'io48', ''),
                            NULLIF(CAST(attributes AS json)->>'io16', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelLevel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel_level', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercent', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelPercentage', '')
                        ) AS raw_fuel
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                ),
                clean_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        month,
                        CAST(raw_fuel AS FLOAT) as fuel_level
                    FROM pos_data
                    WHERE raw_fuel ~ '^[0-9]+(\\.[0-9]+)?$'
                      AND CAST(raw_fuel AS FLOAT) BETWEEN 0 AND 100
                ),
                pos_with_prev AS (
                    SELECT
                        deviceid,
                        month,
                        fixtime,
                        fuel_level,
                        LAG(fuel_level) OVER (PARTITION BY deviceid, month ORDER BY fixtime) as prev_fuel_level
                    FROM clean_data
                )
                SELECT
                    deviceid,
                    month,
                    SUM(CASE WHEN prev_fuel_level > fuel_level THEN prev_fuel_level - fuel_level ELSE 0 END) as total_drop_pct
                FROM pos_with_prev
                GROUP BY deviceid, month
            ", [$fromIso, $toIso]);

            $out = [];
            foreach ($rows as $r) {
                $did = intval($r->deviceid);
                $month = (string) $r->month;
                $out[$did] ??= [];
                $out[$did][$month] = floatval($r->total_drop_pct ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('fuelDropPctByDeviceMonth query failed: '.$e->getMessage());

            return [];
        }
    }

    /** Traccar tc_events type for fuel refill notifications. */
    private const TC_EVENT_DEVICE_FUEL_INCREASE = 'deviceFuelIncrease';

    /** Legacy alias still present in some databases. */
    private const TC_EVENT_FUEL_INCREASE_LEGACY = 'fuelIncrease';

    /**
     * Fuel refill events grouped by device (Traccar event types + alarm payloads).
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, object>>
     */
    private function fleetSummaryFuelRefillEventsByDevice(array $deviceIds, string $fromIso, string $toIso): \Illuminate\Support\Collection
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return collect();
        }

        try {
            $rows = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    type,
                    COUNT(*) AS count,
                    SUM(CAST(COALESCE(
                        NULLIF(CAST(attributes AS json)->>'fuelIncrease', ''),
                        NULLIF(CAST(attributes AS json)->>'amount', ''),
                        NULLIF(CAST(attributes AS json)->>'fuel', ''),
                        '0'
                    ) AS FLOAT)) AS fuel_amount
                FROM tc_events
                WHERE deviceid IN ($idsStr)
                  AND eventtime BETWEEN ? AND ?
                  AND (
                    type IN ('deviceFuelIncrease', 'fuelIncrease')
                    OR (
                        type = 'alarm'
                        AND LOWER(COALESCE(CAST(attributes AS json)->>'alarm', '')) IN (
                            'fuelincrease', 'fuel', 'refuel', 'fuel_fill', 'fuel_refill'
                        )
                    )
                  )
                GROUP BY deviceid, type
            ", [$fromIso, $toIso]);

            return collect($rows)->groupBy('deviceid');
        } catch (\Throwable $e) {
            Log::error('fleetSummaryFuelRefillEventsByDevice failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Detect refills from position telemetry using each device's fuelAttr / fuelAttr_key.
     * Clusters jumps within a time window so sensor noise does not inflate frequency.
     *
     * @return array<int, array{litres: float, count: int}>
     */
    private function fleetSummaryTelemetryRefillsByDevice(array $deviceIds, string $fromIso, string $toIso): array
    {
        $byDevice = [];
        if (empty($deviceIds)) {
            return $byDevice;
        }

        $deviceAttrsById = $this->deviceTelemetryAttrsById($deviceIds);
        $configuredIds = [];
        foreach ($deviceIds as $deviceId) {
            $did = (int) $deviceId;
            if ($this->deviceHasFuelConfig($deviceAttrsById[$did] ?? [])) {
                $configuredIds[] = $did;
            }
            $byDevice[$did] = ['litres' => 0.0, 'count' => 0];
        }

        if (empty($configuredIds)) {
            return $byDevice;
        }

        $sampleIntervalSec = 300;
        $stableDeviceId = null;
        $devAttrs = [];
        $capForCalc = 50.0;
        $refillMinDelta = 10.0;
        $stepJump = 20.0;
        $refillLitres = 0.0;
        $refillCount = 0;
        $prevLiters = null;
        $lastRefillTs = null;
        $lastSampleTs = null;
        $pendingBase = null;
        $pendingPeak = null;
        $pendingHits = 0;
        $minGapBetweenRefillsSec = 3600;
        $confirmReadings = 2;

        $confirmPending = function (?int $eventTs = null) use (
            &$pendingBase,
            &$pendingPeak,
            &$pendingHits,
            &$refillLitres,
            &$refillCount,
            &$lastRefillTs,
            &$refillMinDelta,
            &$capForCalc,
            &$minGapBetweenRefillsSec,
            &$confirmReadings
        ) {
            $posTs = $eventTs;
            if ($pendingBase === null || $pendingHits < $confirmReadings) {
                $pendingBase = null;
                $pendingPeak = null;
                $pendingHits = 0;

                return;
            }
            $delta = min(($pendingPeak ?? $pendingBase) - $pendingBase, $capForCalc * 0.95);
            if ($delta < $refillMinDelta) {
                $pendingBase = null;
                $pendingPeak = null;
                $pendingHits = 0;

                return;
            }
            if ($lastRefillTs !== null && $posTs && ($posTs - $lastRefillTs) < $minGapBetweenRefillsSec) {
                $pendingBase = null;
                $pendingPeak = null;
                $pendingHits = 0;

                return;
            }
            $refillLitres += $delta;
            $refillCount++;
            $lastRefillTs = $posTs ?: $lastRefillTs;
            $pendingBase = null;
            $pendingPeak = null;
            $pendingHits = 0;
        };

        $finalizeDevice = function () use (
            &$byDevice,
            &$stableDeviceId,
            &$refillLitres,
            &$refillCount,
            &$capForCalc,
            $confirmPending
        ) {
            if ($stableDeviceId === null) {
                return;
            }
            $confirmPending(null);
            $litres = $refillLitres;
            $count = $refillCount;
            $this->capFleetSummaryRefillStats($litres, $count, $capForCalc);
            $byDevice[$stableDeviceId] = [
                'litres' => round($litres, 2),
                'count' => $count,
            ];
        };

        $query = DB::connection('pgsql')
            ->table('tc_positions')
            ->select('deviceid', 'fixtime', 'attributes')
            ->whereIn('deviceid', $configuredIds)
            ->whereBetween('fixtime', [$fromIso, $toIso])
            ->orderBy('deviceid')
            ->orderBy('fixtime');

        foreach ($query->cursor() as $row) {
            $did = (int) $row->deviceid;
            if ($stableDeviceId === null || $stableDeviceId !== $did) {
                $finalizeDevice();
                $stableDeviceId = $did;
                $devAttrs = $deviceAttrsById[$did] ?? [];
                $cap = $this->num($devAttrs['fuelTankCapacity'] ?? $devAttrs['FuelTankCapacity'] ?? null);
                $capForCalc = ($cap && $cap > 0) ? $cap : 50.0;
                $fuelAttrName = strtolower(trim((string) ($devAttrs['fuelAttr'] ?? '')));
                $isAnalog = $fuelAttrName !== '' && str_contains($fuelAttrName, 'analog');
                $isPercentCan = str_contains($fuelAttrName, 'percentage') || str_contains($fuelAttrName, 'can');
                $refillMinDelta = $isAnalog
                    ? max(4.0, $capForCalc * 0.08)
                    : ($isPercentCan ? max(8.0, $capForCalc * 0.12) : max(12.0, $capForCalc * 0.18));
                $stepJump = $isPercentCan ? ($capForCalc * 0.18) : ($capForCalc * 0.35);
                $confirmReadings = $isAnalog ? 3 : 2;
                $minGapBetweenRefillsSec = $isAnalog ? 7200 : 3600;
                $refillLitres = 0.0;
                $refillCount = 0;
                $prevLiters = null;
                $lastRefillTs = null;
                $lastSampleTs = null;
                $pendingBase = null;
                $pendingPeak = null;
                $pendingHits = 0;
            }

            $posTs = is_string($row->fixtime) ? strtotime($row->fixtime) : strtotime((string) $row->fixtime);
            if ($posTs && $lastSampleTs !== null && ($posTs - $lastSampleTs) < $sampleIntervalSec) {
                continue;
            }

            $posAttrs = $this->parseJsonAttrs($row->attributes);
            $merged = array_merge($devAttrs, $posAttrs);
            $fuel = $this->computeFuelTelemetry($merged, [
                'capacity' => $capForCalc,
                'fuelAttr_key' => $devAttrs['fuelAttr_key'] ?? null,
                'fuelAttr' => $devAttrs['fuelAttr'] ?? null,
            ]);
            $curLiters = $fuel ? ($fuel['liters'] ?? null) : null;
            if ($curLiters === null && $fuel && (($fuel['percent'] ?? null) !== null)) {
                $curLiters = round((($capForCalc * floatval($fuel['percent']) / 100.0) * 10)) / 10;
            }
            if ($curLiters === null || $curLiters < 0) {
                continue;
            }
            if ($posTs) {
                $lastSampleTs = $posTs;
            }

            if ($prevLiters !== null && abs($curLiters - $prevLiters) > $stepJump) {
                if ($curLiters > $prevLiters && ! $isAnalog) {
                    $this->tryRecordFleetRefill(
                        $prevLiters,
                        $curLiters,
                        $capForCalc,
                        $refillMinDelta,
                        $minGapBetweenRefillsSec,
                        $posTs,
                        $lastRefillTs,
                        $refillLitres,
                        $refillCount
                    );
                }
                $confirmPending($posTs ?: null);
                $prevLiters = $curLiters;

                continue;
            }

            $threshold = ($pendingBase ?? $prevLiters) + $refillMinDelta;
            if ($prevLiters !== null && $curLiters >= $threshold) {
                if ($pendingBase === null) {
                    $pendingBase = $prevLiters;
                    $pendingPeak = $curLiters;
                    $pendingHits = 1;
                } else {
                    $pendingPeak = max($pendingPeak, $curLiters);
                    $pendingHits++;
                }
            } else {
                $confirmPending($posTs ?: null);
            }

            $prevLiters = $curLiters;
        }

        $finalizeDevice();

        return $byDevice;
    }

    /**
     * Engine-on duration (ms) per device from ignitionOn / ignitionOff events.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function fleetSummaryIgnitionDurationMsByDevice(array $deviceIds, string $fromIso, string $toIso): \Illuminate\Support\Collection
    {
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return collect();
        }

        try {
            $ignitionEvents = DB::connection('pgsql')->select("
                SELECT deviceid, type, eventtime
                FROM tc_events
                WHERE deviceid IN ($idsStr)
                  AND eventtime BETWEEN ? AND ?
                  AND type IN ('ignitionOn', 'ignitionOff')
                ORDER BY deviceid, eventtime ASC
            ", [$fromIso, $toIso]);
        } catch (\Throwable $e) {
            Log::error('fleetSummaryIgnitionDurationMsByDevice failed: '.$e->getMessage());

            return collect();
        }

        return collect($ignitionEvents)->groupBy('deviceid')->map(function ($evts) {
            $currentStart = null;
            $total = 0;
            foreach ($evts as $e) {
                $type = is_object($e) ? ($e->type ?? '') : ($e['type'] ?? '');
                $time = is_object($e) ? ($e->eventtime ?? '') : ($e['eventtime'] ?? '');
                if ($type === 'ignitionOn') {
                    $currentStart = $time;
                } elseif ($type === 'ignitionOff' && $currentStart) {
                    $start = strtotime($currentStart);
                    $end = strtotime($time);
                    if ($end > $start) {
                        $total += ($end - $start) * 1000;
                    }
                    $currentStart = null;
                }
            }

            return $total;
        });
    }

    /**
     * SQL fragment: extract numeric speed from tc_positions.attributes JSON key.
     */
    private function sqlPositionAttrSpeed(string $jsonKeyPlaceholder = '?'): string
    {
        return 'CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'.$jsonKeyPlaceholder.', \'\'), \'-1\') AS DOUBLE PRECISION)';
    }

    /**
     * Average and highest speed per device (SQL only; IO uses speedAttr_key like live tracking).
     *
     * @return \Illuminate\Support\Collection<int, object{avg_speed: float, max_speed: float}>
     */
    private function fleetSummaryPositionStatsByDevice(array $deviceIds, string $fromIso, string $toIso): \Illuminate\Support\Collection
    {
        $deviceIds = array_values(array_map('intval', $deviceIds));
        if (empty($deviceIds)) {
            return collect();
        }

        $stats = collect($deviceIds)->mapWithKeys(fn (int $id) => [
            $id => (object) ['avg_speed' => 0.0, 'max_speed' => 0.0],
        ]);

        $deviceAttrsById = $this->deviceTelemetryAttrsById($deviceIds);
        $gpsIds = [];
        $ioByKey = [];

        foreach ($deviceIds as $did) {
            $key = trim((string) ($deviceAttrsById[$did]['speedAttr_key'] ?? ''));
            if ($key !== '' && preg_match('/^[a-z0-9_]+$/i', $key)) {
                $ioByKey[$key][] = $did;
            } else {
                $gpsIds[] = $did;
            }
        }

        try {
            if (! empty($gpsIds)) {
                $gpsIdsStr = implode(',', $gpsIds);
                $rows = DB::connection('pgsql')->select("
                    SELECT deviceid, AVG(speed) AS avg_speed, MAX(speed) AS max_speed
                    FROM tc_positions
                    WHERE deviceid IN ($gpsIdsStr)
                      AND fixtime BETWEEN ? AND ?
                      AND speed > -1
                    GROUP BY deviceid
                ", [$fromIso, $toIso]);

                collect($rows)->each(function ($row) use ($stats) {
                    $did = (int) $row->deviceid;
                    if (! $stats->has($did)) {
                        return;
                    }
                    $stats[$did]->avg_speed = round(floatval($row->avg_speed ?? 0), 1);
                    $stats[$did]->max_speed = round(floatval($row->max_speed ?? 0), 1);
                });
            }

            foreach ($ioByKey as $key => $dids) {
                $ioIdsStr = implode(',', array_map('intval', $dids));
                if ($ioIdsStr === '') {
                    continue;
                }

                $attrSpeed = $this->sqlPositionAttrSpeed('?');
                $ioRows = DB::connection('pgsql')->select("
                    SELECT deviceid,
                           AVG($attrSpeed) AS avg_speed,
                           MAX($attrSpeed) AS max_speed
                    FROM tc_positions
                    WHERE deviceid IN ($ioIdsStr)
                      AND fixtime BETWEEN ? AND ?
                      AND $attrSpeed >= 0
                    GROUP BY deviceid
                ", [$key, $key, $fromIso, $toIso, $key]);

                collect($ioRows)->each(function ($row) use ($stats) {
                    $did = (int) $row->deviceid;
                    if (! $stats->has($did)) {
                        return;
                    }
                    $stats[$did]->avg_speed = round(floatval($row->avg_speed ?? 0), 1);
                    $stats[$did]->max_speed = round(floatval($row->max_speed ?? 0), 1);
                });
            }
        } catch (\Throwable $e) {
            Log::error('fleetSummaryPositionStatsByDevice failed: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Aggregate observer-stored fuelRefillDiff attributes from tc_positions.
     *
     * @return array<int, array{litres: float, count: int}>
     */
    public function fleetSummaryFuelRefillDiffByDevice(array $deviceIds, string $fromIso, string $toIso): array
    {
        $out = [];
        foreach ($deviceIds as $deviceId) {
            $out[(int) $deviceId] = ['litres' => 0.0, 'count' => 0];
        }

        $idsStr = implode(',', array_map('intval', $deviceIds));
        if ($idsStr === '') {
            return $out;
        }

        $attrKey = FuelRefillDiffService::ATTR_KEY;
        $tankCapByDevice = [];
        foreach ($this->deviceTelemetryAttrsById($deviceIds) as $did => $attrs) {
            $cap = $this->num($attrs['fuelTankCapacity'] ?? $attrs['FuelTankCapacity'] ?? null);
            $tankCapByDevice[$did] = ($cap && $cap > 0) ? $cap : 50.0;
        }

        try {
            $rows = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    fixtime,
                    CAST(COALESCE(NULLIF(CAST(attributes AS json)->>?, ''), '0') AS FLOAT) AS refill_diff
                FROM tc_positions
                WHERE deviceid IN ($idsStr)
                  AND fixtime BETWEEN ? AND ?
                  AND CAST(COALESCE(NULLIF(CAST(attributes AS json)->>?, ''), '0') AS FLOAT) > 0
                ORDER BY deviceid, fixtime
            ", [$attrKey, $fromIso, $toIso, $attrKey]);

            $clusterGapSec = 3600;
            $minDiffLitres = 3.0;
            $stableDeviceId = null;
            $lastClusterTs = null;
            $clusterLitres = 0.0;
            $totalLitres = 0.0;
            $refillCount = 0;
            $rejectDevice = false;

            $finalizeDevice = function () use (
                &$out,
                &$stableDeviceId,
                &$totalLitres,
                &$refillCount,
                &$clusterLitres,
                &$lastClusterTs,
                &$rejectDevice
            ) {
                if ($stableDeviceId === null) {
                    return;
                }
                if ($clusterLitres > 0) {
                    $totalLitres += $clusterLitres;
                    $refillCount++;
                }
                if (! $rejectDevice) {
                    $out[$stableDeviceId] = [
                        'litres' => round($totalLitres, 2),
                        'count' => $refillCount,
                    ];
                }
                $stableDeviceId = null;
                $lastClusterTs = null;
                $clusterLitres = 0.0;
                $totalLitres = 0.0;
                $refillCount = 0;
                $rejectDevice = false;
            };

            foreach ($rows as $row) {
                $did = (int) ($row->deviceid ?? 0);
                if ($did <= 0) {
                    continue;
                }

                if ($stableDeviceId === null || $stableDeviceId !== $did) {
                    $finalizeDevice();
                    $stableDeviceId = $did;
                }

                $rawDiff = (float) ($row->refill_diff ?? 0);
                $cap = $tankCapByDevice[$did] ?? 50.0;
                if ($rawDiff > ($cap * 2.0)) {
                    $rejectDevice = true;

                    continue;
                }

                $diff = min($rawDiff, $cap * 0.95);
                if ($diff < $minDiffLitres) {
                    continue;
                }

                $posTs = is_string($row->fixtime) ? strtotime($row->fixtime) : strtotime((string) $row->fixtime);
                if ($lastClusterTs !== null && $posTs && ($posTs - $lastClusterTs) < $clusterGapSec) {
                    $clusterLitres += $diff;
                } else {
                    if ($clusterLitres > 0) {
                        $totalLitres += $clusterLitres;
                        $refillCount++;
                    }
                    $clusterLitres = $diff;
                }
                $lastClusterTs = $posTs ?: $lastClusterTs;
            }

            $finalizeDevice();
        } catch (\Throwable $e) {
            Log::error('fleetSummaryFuelRefillDiffByDevice failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * Resolve fuel refill litres and frequency for one fleet-summary row.
     *
     * @return array{litres: float, count: int}
     */
    private function fleetSummaryResolveFuelRefills(
        int $deviceId,
        \Illuminate\Support\Collection $fuelRefillEventsByDevice,
        array $telemetryRefillByDevice,
        float $tankCapacityLitres = 50.0,
        array $bulkRefillByDevice = [],
        array $refillDiffByDevice = [],
        string $fuelAttrName = ''
    ): array {
        $devFuelEvents = $fuelRefillEventsByDevice->get($deviceId, collect());

        if ($devFuelEvents->isNotEmpty()) {
            $litres = (float) $devFuelEvents->sum(fn ($r) => floatval(is_object($r) ? ($r->fuel_amount ?? 0) : ($r['fuel_amount'] ?? 0)));
            $count = (int) $devFuelEvents->sum(fn ($r) => intval(is_object($r) ? ($r->count ?? 0) : ($r['count'] ?? 0)));
            $cap = $tankCapacityLitres > 0 ? $tankCapacityLitres : 50.0;

            return [
                'litres' => round(min($litres, $cap * 3), 2),
                'count' => $count,
            ];
        }

        $telemetry = $telemetryRefillByDevice[$deviceId] ?? ['litres' => 0.0, 'count' => 0];
        $bulk = $bulkRefillByDevice[$deviceId] ?? ['litres' => 0.0, 'count' => 0];
        $telemetryLitres = (float) ($telemetry['litres'] ?? 0);
        $telemetryCount = (int) ($telemetry['count'] ?? 0);
        $bulkLitres = (float) ($bulk['litres'] ?? 0);
        $bulkCount = (int) ($bulk['count'] ?? 0);
        $litres = 0.0;
        $count = 0;

        if ($telemetryCount > 0 || $telemetryLitres > 0) {
            $litres = $telemetryLitres;
            $count = $telemetryCount;
        } elseif ($bulkCount > 0 || $bulkLitres > 0) {
            $litres = $bulkLitres;
            $count = $bulkCount;
        } else {
            $fuelAttrLower = strtolower($fuelAttrName);
            $allowDiffFallback = $fuelAttrLower === ''
                || str_contains($fuelAttrLower, 'analog');
            if ($allowDiffFallback) {
                $diff = $refillDiffByDevice[$deviceId] ?? ['litres' => 0.0, 'count' => 0];
                $litres = (float) ($diff['litres'] ?? 0);
                $count = (int) ($diff['count'] ?? 0);
            }
        }

        $cap = $tankCapacityLitres > 0 ? $tankCapacityLitres : 50.0;
        $this->capFleetSummaryRefillStats($litres, $count, $cap);

        return [
            'litres' => round($litres, 2),
            'count' => $count,
        ];
    }

    public function fetchFleetSummaryDb($request, $deviceIds)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        $this->abortIfClientDisconnected();

        if (empty($deviceIds)) {
            return [];
        }

        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::error('fetchFleetSummaryDb: Date parse error: '.$e->getMessage());

            return [];
        }

        $days = max(1, \Carbon\Carbon::parse($request->from_date)->diffInDays(\Carbon\Carbon::parse($request->to_date)) + 1);
        $deviceIds = array_values(array_map('intval', $deviceIds));

        $devices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');
        $this->abortIfClientDisconnected();
        $statsByDevice = $this->fleetSummaryPositionStatsByDevice($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();
        $idleSecondsByDevice = $this->idleSecondsByDevice($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();
        $distanceByDevice = $this->distanceMetersByDeviceFromIgnitionTrips($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();
        $durMsByDevice = $this->fleetSummaryIgnitionDurationMsByDevice($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();
        $fuelRefillEventsByDevice = $this->fleetSummaryFuelRefillEventsByDevice($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();

        // Consumption from one position pass; refills from confirmed telemetry scan.
        $fuelAgg = $this->bulkFuelUsageLitresTelemetry($deviceIds, $fromIso, $toIso, 'none', true, false);
        $this->abortIfClientDisconnected();
        $fuelByDevice = $fuelAgg['byDevice'] ?? [];
        $bulkRefillByDevice = $fuelAgg['refillsByDevice'] ?? [];
        $telemetryRefillByDevice = $this->fleetSummaryTelemetryRefillsByDevice($deviceIds, $fromIso, $toIso);
        $this->abortIfClientDisconnected();
        $fuelRefillDiffByDevice = $this->fleetSummaryFuelRefillDiffByDevice($deviceIds, $fromIso, $toIso);

        return collect($deviceIds)->map(function ($deviceId) use (
            $devices,
            $statsByDevice,
            $idleSecondsByDevice,
            $days,
            $durMsByDevice,
            $distanceByDevice,
            $fuelByDevice,
            $fuelRefillEventsByDevice,
            $bulkRefillByDevice,
            $telemetryRefillByDevice,
            $fuelRefillDiffByDevice
        ) {
            $dev = $devices->get($deviceId);
            $stat = $statsByDevice->get($deviceId);
            $deviceName = $dev->name ?? 'Unknown';

            $distTotalKm = round(floatval($distanceByDevice[$deviceId] ?? 0) / 1000, 2);
            $distAvg = round($distTotalKm / $days, 2);

            $engineHoursMs = (int) $durMsByDevice->get($deviceId, 0);
            $durTotalHours = floor($engineHoursMs / 3600000);
            $durTotalMinutes = floor(($engineHoursMs % 3600000) / 60000);
            $durTotalStr = "{$durTotalHours}h {$durTotalMinutes}m";
            $durAvgHours = floor(($engineHoursMs / $days) / 3600000);
            $durAvgMinutes = floor((($engineHoursMs / $days) % 3600000) / 60000);
            $durAvgStr = "{$durAvgHours}h {$durAvgMinutes}m";

            $idleSeconds = 0;
            $idleStat = $idleSecondsByDevice->get($deviceId);
            if ($idleStat && isset($idleStat->idle_seconds)) {
                $idleSeconds = floatval($idleStat->idle_seconds);
            }
            $idleMs = $idleSeconds * 1000;
            $idleTotalHours = floor($idleMs / 3600000);
            $idleTotalMinutes = floor(($idleMs % 3600000) / 60000);
            $idleTotalStr = "{$idleTotalHours}h {$idleTotalMinutes}m";
            $idleAvgHours = floor(($idleMs / $days) / 3600000);
            $idleAvgMinutes = floor((($idleMs / $days) % 3600000) / 60000);
            $idleAvgStr = "{$idleAvgHours}h {$idleAvgMinutes}m";

            $totalPossibleMs = $days * 24 * 60 * 60 * 1000;
            $utilPct = $totalPossibleMs > 0 ? round(($engineHoursMs / $totalPossibleMs) * 100, 1) : 0;

            $attrs = is_string($dev->attributes) ? json_decode($dev->attributes, true) : (array) $dev->attributes;
            $tankCap = floatval($attrs['fuelTankCapacity'] ?? 50);
            if ($tankCap <= 0) {
                $tankCap = 50.0;
            }

            $spentFuel = floatval($fuelByDevice[$deviceId] ?? 0);
            if ($spentFuel > 0 && $distTotalKm > 10) {
                $maxFuelFromDistance = $distTotalKm / 4.0;
                if ($spentFuel > $maxFuelFromDistance) {
                    $spentFuel = $maxFuelFromDistance;
                }
            }
            $maxLitresPerDay = $tankCap * 0.85;
            $avgLitresPerDay = round(min($spentFuel / $days, $maxLitresPerDay), 2);
            $avgKmL = ($avgLitresPerDay > 0 && $distTotalKm > 0) ? round($distTotalKm / ($avgLitresPerDay * $days), 2) : 0;

            $refills = $this->fleetSummaryResolveFuelRefills(
                $deviceId,
                $fuelRefillEventsByDevice,
                $telemetryRefillByDevice,
                $tankCap,
                $bulkRefillByDevice,
                $fuelRefillDiffByDevice,
                (string) ($attrs['fuelAttr'] ?? '')
            );

            $maxRefillLitres = $tankCap + max(0.0, $distTotalKm / 5.0);
            if ($refills['litres'] > $maxRefillLitres) {
                $refills['litres'] = round($maxRefillLitres, 2);
                $refills['count'] = min(
                    $refills['count'],
                    max(1, (int) ceil($maxRefillLitres / max(1.0, $tankCap * 0.45)))
                );
            }
            if ($distTotalKm < 5 && $refills['litres'] > $tankCap * 0.5) {
                $refills = ['litres' => 0.0, 'count' => 0];
            }

            $avgSpeed = round(floatval($stat->avg_speed ?? 0), 1);
            $maxSpeed = round(floatval($stat->max_speed ?? 0), 1);

            return [
                'key' => $deviceId,
                'vehicleId' => $deviceId,
                'vehicleName' => $deviceName,
                'distTotal' => $distTotalKm,
                'distAvg' => $distAvg,
                'durTotal' => $durTotalStr,
                'durAvg' => $durAvgStr,
                'idleTotal' => $idleTotalStr,
                'idleAvg' => $idleAvgStr,
                'util' => $utilPct.'%',
                'avgLitres' => $avgLitresPerDay,
                'avgKmL' => $avgKmL,
                'fuelRefill' => round($refills['litres'], 1).' L',
                'fuelRefillFreq' => $refills['count'],
                'fuelConsumption' => round($spentFuel, 1).' L',
                'speed' => $avgSpeed.' km/h',
                'maxSpeed' => $maxSpeed.' km/h',
            ];
        })->values();
    }

    use \App\Traits\GeocodingTrait;
}
