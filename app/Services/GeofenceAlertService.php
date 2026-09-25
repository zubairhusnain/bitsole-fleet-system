<?php

namespace App\Services;

use App\Models\TcEvent;
use App\Models\TcGeofence;
use App\Support\GeofenceGeometry;
use App\Support\TraccarTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detect geofence enter/exit from tc_positions when Traccar does not emit tc_events.
 */
class GeofenceAlertService
{
    public const CACHE_KEY_INSIDE = 'geofence_alert_inside_by_device';

    /** @var array<int, list<array{lat: float, lon: float}>>|null */
    private ?array $polygonByGeofenceId = null;

    /** @var array<int, list<int>>|null */
    private ?array $geofenceIdsByDevice = null;

    /** @var array<int, string>|null */
    private ?array $geofenceNameById = null;

    /**
     * @param  Collection<int, object>  $rows  Rows with id, deviceid, latitude, longitude, fixtime
     * @param  array<int, list<int>>  $insideByDevice  deviceId => geofence ids currently inside
     * @return array{created: int, insideByDevice: array<int, list<int>>}
     */
    public function processBatch(Collection $rows, array $insideByDevice): array
    {
        if ($rows->isEmpty()) {
            return ['created' => 0, 'insideByDevice' => $insideByDevice];
        }

        $this->warmCaches();

        $created = 0;

        foreach ($rows as $row) {
            $created += $this->processRow($row, $insideByDevice);
        }

        return ['created' => $created, 'insideByDevice' => $insideByDevice];
    }

    public function processPosition(object $position): int
    {
        $deviceId = (int) $position->deviceid;

        return $this->processDeviceBatch($deviceId, collect([$position]));
    }

    /**
     * Process an ordered batch for one device under a per-device lock.
     *
     * @param  Collection<int, object>  $rows
     */
    public function processDeviceBatch(int $deviceId, Collection $rows): int
    {
        if ($deviceId <= 0 || $rows->isEmpty()) {
            return 0;
        }

        $lock = Cache::lock('geofence_alert_device:'.$deviceId, 30);

        try {
            if (! $lock->block(15)) {
                return 0;
            }

            $insideByDevice = [];
            $result = $this->processBatch($rows, $insideByDevice);

            if (array_key_exists($deviceId, $result['insideByDevice'])) {
                $this->saveInsideByDevice([
                    $deviceId => $result['insideByDevice'][$deviceId],
                ]);
            }

            return $result['created'];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, list<int>>  $insideByDevice
     */
    private function processRow(object $row, array &$insideByDevice): int
    {
        $deviceId = (int) $row->deviceid;
        $positionId = (int) $row->id;

        if ($deviceId <= 0 || $positionId <= 0) {
            return 0;
        }

        $lat = is_numeric($row->latitude ?? null) ? (float) $row->latitude : null;
        $lon = is_numeric($row->longitude ?? null) ? (float) $row->longitude : null;

        if ($lat === null || $lon === null) {
            return 0;
        }

        $assignedIds = $this->geofenceIdsByDevice[$deviceId] ?? [];
        if ($assignedIds === []) {
            return 0;
        }

        $currentInside = $this->insideGeofenceIds($deviceId, $lat, $lon, $assignedIds);

        if (array_key_exists($deviceId, $insideByDevice)) {
            $previousInside = $insideByDevice[$deviceId];
        } else {
            $previousInside = $this->previousInsideFromDb($deviceId, $row, $assignedIds);
            if ($previousInside === null) {
                $insideByDevice[$deviceId] = $currentInside;

                return 0;
            }
        }

        $previousSet = array_fill_keys($previousInside, true);
        $currentSet = array_fill_keys($currentInside, true);

        $entered = array_values(array_filter(
            $currentInside,
            fn (int $gid) => ! isset($previousSet[$gid])
        ));
        $exited = array_values(array_filter(
            $previousInside,
            fn (int $gid) => ! isset($currentSet[$gid])
        ));

        $insideByDevice[$deviceId] = $currentInside;

        if ($entered === [] && $exited === []) {
            return 0;
        }

        $eventTime = $this->eventTimeFromRow($row);
        $created = 0;

        foreach ($entered as $geofenceId) {
            if ($this->createEvent($deviceId, $positionId, $geofenceId, 'geofenceEnter', $eventTime)) {
                $created++;
            }
        }

        foreach ($exited as $geofenceId) {
            if ($this->createEvent($deviceId, $positionId, $geofenceId, 'geofenceExit', $eventTime)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  list<int>  $assignedIds
     * @return list<int>
     */
    private function insideGeofenceIds(int $deviceId, float $lat, float $lon, array $assignedIds): array
    {
        $inside = [];

        foreach ($assignedIds as $geofenceId) {
            $polygon = $this->polygonByGeofenceId[$geofenceId] ?? null;
            if ($polygon === null) {
                continue;
            }

            if (GeofenceGeometry::pointInPolygon($lat, $lon, $polygon)) {
                $inside[] = $geofenceId;
            }
        }

        sort($inside);

        return $inside;
    }

    private function createEvent(
        int $deviceId,
        int $positionId,
        int $geofenceId,
        string $type,
        Carbon $eventTime,
    ): bool {
        if ($this->eventExists($deviceId, $positionId, $geofenceId, $type)) {
            return false;
        }

        $name = $this->geofenceNameById[$geofenceId] ?? ('Geofence #'.$geofenceId);
        $message = $type === 'geofenceEnter'
            ? "Entered geofence: {$name}"
            : "Exited geofence: {$name}";

        $event = new TcEvent();
        $event->type = $type;
        $event->eventtime = $eventTime;
        $event->deviceid = $deviceId;
        $event->positionid = $positionId;
        $event->geofenceid = $geofenceId;
        $event->attributes = ['message' => $message];
        $event->maintenanceid = null;
        $event->save();

        return true;
    }

    public function eventExists(int $deviceId, int $positionId, int $geofenceId, string $type): bool
    {
        return TcEvent::query()
            ->where('deviceid', $deviceId)
            ->where('positionid', $positionId)
            ->where('geofenceid', $geofenceId)
            ->where('type', $type)
            ->exists();
    }

    private function eventTimeFromRow(object $row): Carbon
    {
        $raw = $row->fixtime ?? null;
        if ($raw instanceof Carbon) {
            return $raw->copy()->utc();
        }
        if (is_string($raw) && $raw !== '') {
            return TraccarTime::parse($raw);
        }

        return now('UTC');
    }

    /**
     * Derive inside-geofence state from the last DB position before the current row.
     *
     * @param  list<int>  $assignedIds
     * @return list<int>|null  Null when there is no prior position to anchor from.
     */
    private function previousInsideFromDb(int $deviceId, object $row, array $assignedIds): ?array
    {
        $fixtime = $row->fixtime ?? null;
        $positionId = (int) $row->id;

        if ($fixtime === null || $positionId <= 0) {
            return null;
        }

        $prev = DB::connection('pgsql')
            ->table('tc_positions')
            ->where('deviceid', $deviceId)
            ->where(function ($query) use ($fixtime, $positionId) {
                $query->where('fixtime', '<', $fixtime)
                    ->orWhere(function ($sameTime) use ($fixtime, $positionId) {
                        $sameTime->where('fixtime', '=', $fixtime)
                            ->where('id', '<', $positionId);
                    });
            })
            ->orderByDesc('fixtime')
            ->orderByDesc('id')
            ->first(['latitude', 'longitude']);

        if ($prev === null) {
            return null;
        }

        $lat = is_numeric($prev->latitude) ? (float) $prev->latitude : null;
        $lon = is_numeric($prev->longitude) ? (float) $prev->longitude : null;

        if ($lat === null || $lon === null) {
            return [];
        }

        return $this->insideGeofenceIds($deviceId, $lat, $lon, $assignedIds);
    }

    private function warmCaches(): void
    {
        if ($this->polygonByGeofenceId !== null) {
            return;
        }

        $this->polygonByGeofenceId = [];
        $this->geofenceNameById = [];

        $geofences = TcGeofence::query()->get(['id', 'name', 'area']);
        foreach ($geofences as $geofence) {
            $polygon = GeofenceGeometry::parsePolygonFromWkt((string) $geofence->area);
            if ($polygon !== null) {
                $this->polygonByGeofenceId[(int) $geofence->id] = $polygon;
                $this->geofenceNameById[(int) $geofence->id] = (string) ($geofence->name ?? '');
            }
        }

        $this->geofenceIdsByDevice = [];
        $links = DB::connection('pgsql')
            ->table('tc_device_geofence')
            ->select('deviceid', 'geofenceid')
            ->get();

        foreach ($links as $link) {
            $deviceId = (int) $link->deviceid;
            $geofenceId = (int) $link->geofenceid;
            if ($deviceId <= 0 || $geofenceId <= 0) {
                continue;
            }
            if (! isset($this->polygonByGeofenceId[$geofenceId])) {
                continue;
            }
            $this->geofenceIdsByDevice[$deviceId][] = $geofenceId;
        }

        foreach ($this->geofenceIdsByDevice as $deviceId => $ids) {
            sort($ids);
            $this->geofenceIdsByDevice[$deviceId] = array_values(array_unique($ids));
        }
    }

    /** @return array<int, list<int>> */
    public function loadInsideByDevice(): array
    {
        $raw = Cache::get(self::CACHE_KEY_INSIDE, []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $deviceId => $ids) {
            if (! is_array($ids)) {
                continue;
            }
            $parsed = array_values(array_unique(array_map('intval', $ids)));
            sort($parsed);
            $out[(int) $deviceId] = $parsed;
        }

        return $out;
    }

    /** @param  array<int, list<int>>  $insideByDevice */
    public function saveInsideByDevice(array $insideByDevice): void
    {
        if ($insideByDevice === []) {
            return;
        }

        $existing = $this->loadInsideByDevice();
        Cache::put(self::CACHE_KEY_INSIDE, array_replace($existing, $insideByDevice), now()->addDays(7));
    }
}
