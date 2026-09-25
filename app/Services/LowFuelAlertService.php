<?php

namespace App\Services;

use App\Models\TcEvent;
use Carbon\Carbon;

class LowFuelAlertService
{
    public const CRITICAL_PCT = 10;

    public const WARNING_PCT = 25;

    public const COOLDOWN_MINUTES = 30;

    public const ATTR_FUEL_GAUGE_LEVEL = 'fuelGaugeLevel';

    public function __construct(
        private FuelRefillDiffService $fuelService,
    ) {}

    public function fuelLowAlertsDisabled(array $devAttrs): bool
    {
        $v = strtolower(trim((string) ($devAttrs['fuelLowAlerts'] ?? '')));

        return $v === 'disabled' || $v === 'false' || $v === '0';
    }

    public function hasFuelConfig(array $devAttrs): bool
    {
        $fuelAttr = strtolower(trim((string) ($devAttrs['fuelAttr'] ?? '')));

        return $fuelAttr !== '' && $fuelAttr !== 'none';
    }

    /** Live check: alert type for current fuel level (not threshold crossing). */
    public function liveAlertType(int $percent): ?string
    {
        if ($percent < self::CRITICAL_PCT) {
            return 'lowFuelCritical';
        }
        if ($percent < self::WARNING_PCT) {
            return 'lowFuelWarning';
        }

        return null;
    }

    /**
     * Threshold crossings when fuel moves from $previousPercent to $percent.
     *
     * @return list<string>
     */
    public function crossingAlertTypes(?int $previousPercent, int $percent): array
    {
        if ($previousPercent === null) {
            return [];
        }

        $types = [];

        if ($percent < self::CRITICAL_PCT && $previousPercent >= self::CRITICAL_PCT) {
            $types[] = 'lowFuelCritical';
        }
        if ($percent < self::WARNING_PCT && $previousPercent >= self::WARNING_PCT) {
            $types[] = 'lowFuelWarning';
        }

        return $types;
    }

    /**
     * Band-entry alerts for historical backfill — one alert per episode until fuel recovers.
     *
     * @return array{types: list<string>, wasBelowWarning: bool, wasBelowCritical: bool}
     */
    public function bandEntryAlertTypes(bool $wasBelowWarning, bool $wasBelowCritical, int $percent): array
    {
        if ($percent >= self::WARNING_PCT) {
            $wasBelowWarning = false;
        }
        if ($percent >= self::CRITICAL_PCT) {
            $wasBelowCritical = false;
        }

        $types = [];

        if ($percent < self::CRITICAL_PCT && ! $wasBelowCritical) {
            $types[] = 'lowFuelCritical';
            $wasBelowCritical = true;
            $wasBelowWarning = true;
        } elseif ($percent < self::WARNING_PCT && ! $wasBelowWarning) {
            $types[] = 'lowFuelWarning';
            $wasBelowWarning = true;
        }

        return [
            'types' => $types,
            'wasBelowWarning' => $wasBelowWarning,
            'wasBelowCritical' => $wasBelowCritical,
        ];
    }

    public function initialBandState(?int $seedPercent): array
    {
        return [
            'wasBelowWarning' => $seedPercent !== null && $seedPercent < self::WARNING_PCT,
            'wasBelowCritical' => $seedPercent !== null && $seedPercent < self::CRITICAL_PCT,
        ];
    }

    public function messageFor(string $type, int $percent): string
    {
        if ($type === 'lowFuelCritical') {
            return "Critical fuel level: {$percent}% (below ".self::CRITICAL_PCT.'%)';
        }

        return "Low fuel level: {$percent}% (below ".self::WARNING_PCT.'%)';
    }

    /**
     * @return array{message: string, fuelGaugeLevel: int}
     */
    public function eventAttributes(int $percent, string $message): array
    {
        return [
            'message' => $message,
            self::ATTR_FUEL_GAUGE_LEVEL => $percent,
        ];
    }

    public function cooldownKey(int $deviceId, string $type): string
    {
        return "low_fuel_alert:{$deviceId}:{$type}";
    }

    public function eventExists(int $deviceId, string $type, int $positionId): bool
    {
        return TcEvent::query()
            ->where('deviceid', $deviceId)
            ->where('type', $type)
            ->where('positionid', $positionId)
            ->exists();
    }

    public function createEvent(
        int $deviceId,
        int $positionId,
        string $type,
        int $percent,
        Carbon $eventTime,
    ): TcEvent {
        $message = $this->messageFor($type, $percent);

        $event = new TcEvent();
        $event->type = $type;
        $event->eventtime = $eventTime;
        $event->deviceid = $deviceId;
        $event->positionid = $positionId;
        $event->geofenceid = null;
        $event->attributes = $this->eventAttributes($percent, $message);
        $event->maintenanceid = null;
        $event->save();

        return $event;
    }

    public function fuelPercentFromPosition(array $posAttrs, array $devAttrs): ?int
    {
        $telemetry = $this->fuelService->fuelTelemetry($posAttrs, $devAttrs);

        return isset($telemetry['percent']) ? (int) $telemetry['percent'] : null;
    }

    public function decodeAttributes(mixed $raw): array
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
