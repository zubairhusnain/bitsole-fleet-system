<?php

namespace App\Support;

use App\Models\TcDevice;

class TelemetryFormat
{
    public static function parseAttrs($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function num($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = is_string($v) ? (float) $v : (float) $v;

        return is_finite($n) ? $n : null;
    }

    private static function get(array $attrs, string $k)
    {
        if (array_key_exists($k, $attrs)) {
            return $attrs[$k];
        }
        $lk = strtolower($k);
        foreach ($attrs as $x => $v) {
            if (strtolower((string) $x) === $lk) {
                return $v;
            }
        }

        return null;
    }

    private static function isKmKey(string $k): bool
    {
        $s = strtolower($k);
        foreach (['389', 'io389', 'obd_total_mileage_389'] as $x) {
            if (str_contains($s, $x)) {
                return true;
            }
        }

        return str_ends_with($s, 'km') || str_contains($s, '-km');
    }

    private static function isIoKey(string $k): bool
    {
        foreach (['87', '389', '16', '50'] as $x) {
            if (str_contains($k, $x)) {
                return true;
            }
        }

        return false;
    }

    private static function fmtKm(float $k): string
    {
        $n = round($k * 10) / 10;

        return number_format($n, 1).' km';
    }

    /**
     * Odometer context passed to resolveOdometer (mirrors frontend formatTelemetry ctx).
     *
     * @return array{odometerAttr?: string|null, odometerAttr_key?: string|null}
     */
    public static function odometerContextFromAttrs(array $deviceAttrs = [], array $vehicleAttrs = []): array
    {
        $configured = $vehicleAttrs['odometerAttr']
            ?? $vehicleAttrs['odometer_attribute']
            ?? $deviceAttrs['odometerAttr']
            ?? $deviceAttrs['odometer_attribute']
            ?? null;

        return [
            'odometerAttr' => is_string($configured) ? $configured : null,
            'odometerAttr_key' => $deviceAttrs['odometerAttr_key'] ?? null,
        ];
    }

    /**
     * Mirror resources/js/utils/telemetry.js formatOdometer — resolved numeric reading.
     *
     * @return array{key: string, raw: float, km: float, meters: float}|null
     */
    public static function resolveOdometer(array $attrs, array $ctx = []): ?array
    {
        $getV = fn (string $k) => self::num(self::get($attrs, $k));

        $pref = $attrs['odometerAttr_key'] ?? $ctx['odometerAttr_key'] ?? null;
        if ($pref) {
            $val = $getV($pref);
            if ($val !== null && $val > -1 && ! (self::isIoKey($pref) && $val == 0)) {
                $km = self::isKmKey($pref) ? $val : $val / 1000;

                return [
                    'key' => $pref,
                    'raw' => (float) $val,
                    'km' => (float) $km,
                    'meters' => (float) $km * 1000.0,
                ];
            }

            return [
                'key' => $pref,
                'raw' => 0.0,
                'km' => 0.0,
                'meters' => 0.0,
            ];
        }

        $defaults = [
            '87', '389', '16', '50', 'odometer', 'mileage', 'odometerKm', 'odometer_km',
            'totalDistance', 'distance', 'tripDistance',
        ];

        $keys = [];
        $odoAttr = $ctx['odometerAttr'] ?? $attrs['odometerAttr'] ?? null;
        if ($odoAttr && is_string($odoAttr)) {
            $keys[] = $odoAttr;
        }
        foreach ($defaults as $k) {
            $keys[] = $k;
        }

        $expanded = [];
        foreach ($keys as $k) {
            if (! $k || ! is_string($k)) {
                continue;
            }
            if (in_array($k, ['87', '389', '16', '50'], true)) {
                $expanded = array_merge($expanded, [$k, "io{$k}", "io_{$k}", "io-{$k}"]);
            } else {
                $expanded[] = $k;
            }
        }

        foreach ($expanded as $k) {
            $val = $getV($k);
            if ($val === null) {
                continue;
            }
            if ((self::isIoKey($k) && $val == 0) || $val <= -1) {
                continue;
            }
            $km = self::isKmKey($k) ? $val : $val / 1000;

            return [
                'key' => $k,
                'raw' => (float) $val,
                'km' => (float) $km,
                'meters' => (float) $km * 1000.0,
            ];
        }

        if ($odoAttr) {
            return [
                'key' => (string) $odoAttr,
                'raw' => 0.0,
                'km' => 0.0,
                'meters' => 0.0,
            ];
        }

        return null;
    }

    /**
     * Resolve odometer from position attrs using the same merge + ctx as vehicle list/live/detail.
     *
     * @return array{key: string, raw: float, km: float, meters: float}|null
     */
    public static function resolveOdometerFromPosition(array $posAttrs, array $deviceAttrs = [], array $vehicleAttrs = []): ?array
    {
        $merged = array_merge($deviceAttrs, $vehicleAttrs, $posAttrs);

        return self::resolveOdometer($merged, self::odometerContextFromAttrs($deviceAttrs, $vehicleAttrs));
    }

    /**
     * Mirror resources/js/utils/telemetry.js formatOdometer display string.
     */
    public static function formatOdometerDisplay(array $rawAttrs, array $ctx = []): ?string
    {
        $resolved = self::resolveOdometer($rawAttrs, $ctx);
        if ($resolved === null) {
            return null;
        }

        return self::fmtKm($resolved['km']);
    }

    public static function formatOdometerFromSources(array $deviceAttrs, array $vehicleAttrs, array $posAttrs): ?string
    {
        $merged = array_merge($deviceAttrs, $vehicleAttrs, $posAttrs);

        return self::formatOdometerDisplay($merged, self::odometerContextFromAttrs($deviceAttrs, $vehicleAttrs));
    }

    /** Max plausible average speed for a single ignition trip (m/s). */
    private const MAX_TRIP_SPEED_MPS = 200 / 3.6;

    public static function hasConfiguredOdometer(array $deviceAttrs, array $vehicleAttrs = []): bool
    {
        $ctx = self::odometerContextFromAttrs($deviceAttrs, $vehicleAttrs);
        if (is_string($ctx['odometerAttr_key'] ?? null) && $ctx['odometerAttr_key'] !== '') {
            return true;
        }

        $attr = $ctx['odometerAttr'] ?? null;

        return is_string($attr) && trim($attr) !== '';
    }

    /**
     * Raw odometer reading in metres (telemetry resolveOdometer).
     */
    public static function odometerMetersFromPositionAttrs(array $posAttrs, array $deviceAttrs = [], array $vehicleAttrs = []): ?float
    {
        $resolved = self::resolveOdometerFromPosition($posAttrs, $deviceAttrs, $vehicleAttrs);

        return $resolved !== null ? $resolved['meters'] : null;
    }

    public static function totalDistanceMetersFromPositionAttrs(array $posAttrs): ?float
    {
        $val = self::num(self::get($posAttrs, 'totalDistance'));

        return ($val !== null && $val >= 0) ? (float) $val : null;
    }

    /**
     * Trip distance in metres from ignition on/off readings.
     * Uses telemetry odometer resolution (configured IO keys) — same as vehicle list/live/detail.
     */
    public static function tripDistanceMeters(
        ?array $startResolved,
        ?array $endResolved,
        ?float $startTotalM,
        ?float $endTotalM,
        int $durationSec,
        bool $configuredOdometer = false
    ): float {
        if ($startResolved !== null && $endResolved !== null) {
            $startKey = $startResolved['key'] ?? '';
            $endKey = $endResolved['key'] ?? '';
            $sameSource = $startKey !== '' && $startKey === $endKey;
            $delta = $endResolved['meters'] - $startResolved['meters'];

            if ($sameSource && $delta >= 0) {
                $usesTelemetryOdo = $configuredOdometer || ! in_array($startKey, ['totalDistance', 'distance', 'tripDistance'], true);
                if ($usesTelemetryOdo) {
                    return self::capTripDistanceMeters($delta, $durationSec);
                }
            }
        }

        if ($configuredOdometer) {
            return 0.0;
        }

        $totalDist = null;
        if ($startTotalM !== null && $endTotalM !== null) {
            $delta = $endTotalM - $startTotalM;
            if ($delta >= 0) {
                $totalDist = $delta;
            }
        }

        $odoDist = null;
        if ($startResolved !== null && $endResolved !== null
            && ($startResolved['key'] ?? '') === ($endResolved['key'] ?? '')
            && ! in_array($startResolved['key'] ?? '', ['totalDistance', 'distance', 'tripDistance'], true)) {
            $delta = $endResolved['meters'] - $startResolved['meters'];
            if ($delta >= 0) {
                $odoDist = $delta;
            }
        }

        $dist = $odoDist ?? $totalDist ?? 0.0;

        if ($odoDist !== null && $totalDist !== null && $totalDist > max(1000.0, $odoDist * 3.0)) {
            $dist = $odoDist;
        }

        return self::capTripDistanceMeters($dist, $durationSec);
    }

    private static function capTripDistanceMeters(float $dist, int $durationSec): float
    {
        if ($dist <= 0) {
            return 0.0;
        }

        if ($durationSec > 0) {
            $maxDist = $durationSec * self::MAX_TRIP_SPEED_MPS;
            if ($dist > $maxDist) {
                return $maxDist;
            }
        } elseif ($dist > 500_000) {
            return 0.0;
        }

        return $dist;
    }

    /**
     * Device attrs with odometerAttr_key resolved (same as TcDevice accessor / frontend API).
     */
    public static function resolvedDeviceAttrs(?TcDevice $tc): array
    {
        if (! $tc) {
            return [];
        }

        return self::parseAttrs($tc->attributes);
    }

    /**
     * Odometer display for a device + vehicle + position (mirrors frontend formatTelemetry odometer).
     */
    public static function formatOdometerForDevice(?TcDevice $tc, array $vehicleAttrs, array $posAttrs): ?string
    {
        return self::formatOdometerFromSources(
            self::resolvedDeviceAttrs($tc),
            $vehicleAttrs,
            $posAttrs
        );
    }
}
