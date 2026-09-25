<?php

namespace App\Support;

/**
 * Parse Traccar geofence WKT and test point membership.
 */
class GeofenceGeometry
{
    /**
     * @return list<array{lat: float, lon: float}>|null
     */
    public static function parsePolygonFromWkt(string $area): ?array
    {
        $area = trim($area);
        if ($area === '' || ! str_starts_with($area, 'POLYGON')) {
            return null;
        }

        if (! preg_match('/\(\((.*?)\)\)/s', $area, $matches)) {
            return null;
        }

        $polygon = [];
        foreach (explode(',', $matches[1]) as $pointStr) {
            $coords = preg_split('/\s+/', trim($pointStr));
            if (count($coords) < 2) {
                continue;
            }

            $polygon[] = [
                'lat' => (float) $coords[1],
                'lon' => (float) $coords[0],
            ];
        }

        return count($polygon) >= 3 ? $polygon : null;
    }

    public static function pointInsideArea(float $lat, float $lon, string $area): bool
    {
        $polygon = self::parsePolygonFromWkt($area);

        return $polygon !== null && self::pointInPolygon($lat, $lon, $polygon);
    }

    /**
     * Ray-casting point-in-polygon (lat/lon vertex order).
     *
     * @param  list<array{lat: float, lon: float}>  $polygon
     */
    public static function pointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lon'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lon'];

            $intersect = (($xi > $lat) != ($xj > $lat))
                && ($lon < ($yj - $yi) * ($lat - $xi) / ($xj - $xi) + $yi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
