<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AlertTypes
{
    public static function known(): array
    {
        return array_values(config('alert_types.known', []));
    }

    public static function labels(): array
    {
        return config('alert_types.labels', []);
    }

    /**
     * Known types plus any custom types found in tc_events for these devices.
     */
    public static function forUserDevices(array $deviceIds, ?int $userId = null): array
    {
        $types = static::known();

        if (config('alert_types.discover_custom', true) && $deviceIds !== []) {
            $types = array_merge($types, static::discoveredForDevices($deviceIds, $userId));
        }

        return static::sortedUnique($types);
    }

    private static function discoveredForDevices(array $deviceIds, ?int $userId): array
    {
        $deviceIds = array_values(array_unique(array_map('intval', $deviceIds)));
        sort($deviceIds);

        $cacheKey = 'alert_types:'.($userId ?? 'guest').':'.md5(implode(',', $deviceIds));
        $ttl = max(0, (int) config('alert_types.cache_ttl', 600));

        $fetch = static function () use ($deviceIds): array {
            return DB::connection('pgsql')
                ->table('tc_events')
                ->whereIn('deviceid', $deviceIds)
                ->distinct()
                ->orderBy('type')
                ->limit((int) config('alert_types.discover_limit', 100))
                ->pluck('type')
                ->filter(fn ($type) => is_string($type) && $type !== '')
                ->values()
                ->all();
        };

        if ($ttl === 0) {
            return $fetch();
        }

        return Cache::remember($cacheKey, $ttl, $fetch);
    }

    private static function sortedUnique(array $types): array
    {
        $types = array_values(array_unique(array_filter($types, fn ($type) => is_string($type) && $type !== '')));
        sort($types, SORT_STRING);

        return $types;
    }
}
