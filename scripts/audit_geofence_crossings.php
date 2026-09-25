<?php

/**
 * Audit point-based geofence enter/exit vs segment path crossings.
 * Usage: php scripts/audit_geofence_crossings.php [days=30]
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\GeofenceGeometry;
use Illuminate\Support\Facades\DB;

$days = max(1, (int) ($argv[1] ?? 30));
$since = now()->subDays($days)->format('Y-m-d H:i:s');

function segmentsIntersect(array $a, array $b, array $c, array $d): bool
{
    $d1 = ($d['lon'] - $c['lon']) * ($a['lat'] - $c['lat']) - ($d['lat'] - $c['lat']) * ($a['lon'] - $c['lon']);
    $d2 = ($d['lon'] - $c['lon']) * ($b['lat'] - $c['lat']) - ($d['lat'] - $c['lat']) * ($b['lon'] - $c['lon']);
    $d3 = ($b['lon'] - $a['lon']) * ($c['lat'] - $a['lat']) - ($b['lat'] - $a['lat']) * ($c['lon'] - $a['lon']);
    $d4 = ($b['lon'] - $a['lon']) * ($d['lat'] - $a['lat']) - ($b['lat'] - $a['lat']) * ($d['lon'] - $a['lon']);

    return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
        && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
}

function segmentCrossesPolygon(float $lat1, float $lon1, float $lat2, float $lon2, array $poly): bool
{
    $a = ['lat' => $lat1, 'lon' => $lon1];
    $b = ['lat' => $lat2, 'lon' => $lon2];
    $n = count($poly);

    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        if (segmentsIntersect($a, $b, $poly[$j], $poly[$i])) {
            return true;
        }
    }

    return false;
}

echo "=== Geofence crossing audit (last {$days} days, since {$since}) ===\n\n";

$devices = DB::connection('pgsql')
    ->table('tc_device_geofence')
    ->select('deviceid')
    ->distinct()
    ->orderBy('deviceid')
    ->pluck('deviceid');

$totals = [
    'positions' => 0,
    'invalid_coords' => 0,
    'point_flips' => 0,
    'segment_tunnel_misses' => 0,
    'large_gap_segments' => 0,
    'large_gap_tunnel_misses' => 0,
];

$samples = [];

foreach ($devices as $deviceId) {
    $deviceId = (int) $deviceId;
    $geofenceLinks = DB::connection('pgsql')
        ->table('tc_device_geofence')
        ->where('deviceid', $deviceId)
        ->pluck('geofenceid');

    $polygons = [];
    foreach ($geofenceLinks as $gid) {
        $area = DB::connection('pgsql')->table('tc_geofences')->where('id', $gid)->value('area');
        $poly = $area ? GeofenceGeometry::parsePolygonFromWkt((string) $area) : null;
        if ($poly) {
            $polygons[(int) $gid] = $poly;
        }
    }

    if ($polygons === []) {
        continue;
    }

    $prev = null;
    $deviceStats = [
        'positions' => 0,
        'invalid' => 0,
        'flips' => 0,
        'tunnel_misses' => 0,
        'max_gap' => 0,
    ];

    DB::connection('pgsql')
        ->table('tc_positions')
        ->where('deviceid', $deviceId)
        ->where('fixtime', '>=', $since)
        ->orderBy('fixtime')
        ->orderBy('id')
        ->select(['id', 'latitude', 'longitude', 'fixtime'])
        ->chunk(5000, function ($chunk) use (&$prev, &$deviceStats, &$totals, &$samples, $polygons, $deviceId) {
            foreach ($chunk as $p) {
                $deviceStats['positions']++;
                $totals['positions']++;

                $lat = is_numeric($p->latitude) ? (float) $p->latitude : null;
                $lon = is_numeric($p->longitude) ? (float) $p->longitude : null;

                if ($lat === null || $lon === null || ($lat == 0.0 && $lon == 0.0)) {
                    $deviceStats['invalid']++;
                    $totals['invalid_coords']++;
                    continue;
                }

                $insideNow = [];
                foreach ($polygons as $gid => $poly) {
                    if (GeofenceGeometry::pointInPolygon($lat, $lon, $poly)) {
                        $insideNow[$gid] = true;
                    }
                }

                if ($prev !== null) {
                    $gap = strtotime((string) $p->fixtime) - strtotime((string) $prev['fixtime']);
                    if ($gap > $deviceStats['max_gap']) {
                        $deviceStats['max_gap'] = $gap;
                    }

                    foreach ($polygons as $gid => $poly) {
                        $wasInside = isset($prev['inside'][$gid]);
                        $isInside = isset($insideNow[$gid]);

                        if ($wasInside !== $isInside) {
                            $deviceStats['flips']++;
                            $totals['point_flips']++;
                        }

                        if (! $wasInside && ! $isInside && segmentCrossesPolygon(
                            $prev['lat'], $prev['lon'], $lat, $lon, $poly
                        )) {
                            $deviceStats['tunnel_misses']++;
                            $totals['segment_tunnel_misses']++;
                            if (count($samples) < 8) {
                                $samples[] = [
                                    'device' => $deviceId,
                                    'geofence' => $gid,
                                    'from' => $prev['fixtime'],
                                    'to' => $p->fixtime,
                                    'gap_s' => $gap,
                                    'from_pos' => "{$prev['lat']},{$prev['lon']}",
                                    'to_pos' => "{$lat},{$lon}",
                                ];
                            }
                        }

                        if ($gap >= 60 && ! $wasInside && ! $isInside && segmentCrossesPolygon(
                            $prev['lat'], $prev['lon'], $lat, $lon, $poly
                        )) {
                            $totals['large_gap_tunnel_misses']++;
                        }
                    }

                    if ($gap >= 60) {
                        $totals['large_gap_segments']++;
                    }
                }

                $prev = [
                    'lat' => $lat,
                    'lon' => $lon,
                    'fixtime' => $p->fixtime,
                    'inside' => $insideNow,
                ];
            }
        });

    echo "Device #{$deviceId}: positions={$deviceStats['positions']} invalid={$deviceStats['invalid']} ";
    echo "point_flips={$deviceStats['flips']} tunnel_misses={$deviceStats['tunnel_misses']} max_gap={$deviceStats['max_gap']}s\n";
}

echo "\n=== Totals ===\n";
foreach ($totals as $k => $v) {
    echo "  {$k}: {$v}\n";
}

if ($samples) {
    echo "\n=== Sample tunnel misses (both points outside, path crosses zone) ===\n";
    foreach ($samples as $s) {
        echo "  dev={$s['device']} geo={$s['geofence']} gap={$s['gap_s']}s {$s['from']} -> {$s['to']}\n";
        echo "    {$s['from_pos']} -> {$s['to_pos']}\n";
    }
} else {
    echo "\nNo tunnel misses found in audit window.\n";
}

$events = DB::connection('pgsql')->selectOne("
    SELECT COUNT(*) AS c FROM tc_events
    WHERE type IN ('geofenceEnter','geofenceExit') AND eventtime >= ?
", [$since]);
echo "\nGenerated geofence events in window: ".(int) $events->c."\n";
