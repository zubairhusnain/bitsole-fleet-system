<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait GeocodingTrait
{
    public function isBlankAddress($address): bool
    {
        return $address === null || trim((string) $address) === '';
    }

    public function geoCacheKey($lat, $lon, string $lang = 'en'): string
    {
        $lat = round((float) $lat, 4);
        $lon = round((float) $lon, 4);

        return "geo_addr_{$lang}_{$lat}_{$lon}";
    }

    public function formatCoordFallback($lat, $lon): string
    {
        return round((float) $lat, 5).', '.round((float) $lon, 5);
    }

    public function persistPositionAddress(?int $positionId, string $address): void
    {
        if (! $positionId || trim($address) === '') {
            return;
        }

        try {
            DB::connection('pgsql')->table('tc_positions')
                ->where('id', $positionId)
                ->update(['address' => $address]);
        } catch (\Throwable $e) {
            Log::warning('persistPositionAddress failed: '.$e->getMessage());
        }
    }

    protected function nominatimUserAgent(): string
    {
        $contact = config('services.nominatim.email', env('NOMINATIM_CONTACT_EMAIL', 'fleet@bitsole.local'));

        return 'BitsoleFleetSystem/1.0 ('.$contact.')';
    }

    /**
     * Reverse geocode via Nominatim (1 req/s policy) with 30-day cache.
     */
    public function getAddress($lat, $lon)
    {
        if (empty($lat) || empty($lon)) {
            return '';
        }

        $lat = round((float) $lat, 4);
        $lon = round((float) $lon, 4);
        $lang = 'en';
        $key = $this->geoCacheKey($lat, $lon, $lang);

        return Cache::remember($key, 86400 * 30, function () use ($lat, $lon, $lang) {
            try {
                $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lon}&zoom=18&addressdetails=1&accept-language={$lang}";
                $response = Http::timeout(15)->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept-Language' => $lang,
                ])->get($url);

                if ($response->successful()) {
                    return $response->json()['display_name'] ?? '';
                }

                if ($response->status() === 429) {
                    Log::warning('Nominatim rate limited (429)');
                }
            } catch (\Throwable $e) {
                Log::error('Geocoding failed: '.$e->getMessage());
            }

            return '';
        });
    }

    /**
     * Fill missing trip start/end addresses (cache + Nominatim + persist to tc_positions).
     *
     * @param  array<int, array<string, mixed>>  $trips
     * @return array<int, array<string, mixed>>
     */
    public function enrichTripsWithAddresses(array $trips, int $maxApiCalls = 12): array
    {
        $apiCalls = 0;
        $pending = [];

        foreach ($trips as &$trip) {
            foreach ([
                ['startAddress', 'startLat', 'startLon', 'startPositionId'],
                ['endAddress', 'endLat', 'endLon', 'endPositionId'],
            ] as [$addrKey, $latKey, $lonKey, $posKey]) {
                if (! $this->isBlankAddress($trip[$addrKey] ?? null)) {
                    continue;
                }

                $lat = $trip[$latKey] ?? null;
                $lon = $trip[$lonKey] ?? null;
                if ($lat === null || $lon === null || (float) $lat == 0.0 || (float) $lon == 0.0) {
                    continue;
                }

                $cacheKey = $this->geoCacheKey($lat, $lon);
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $trip[$addrKey] = $cached;
                    if (! empty($trip[$posKey])) {
                        $this->persistPositionAddress((int) $trip[$posKey], $cached);
                    }

                    continue;
                }

                $ck = round((float) $lat, 4).'_'.round((float) $lon, 4);
                if (! isset($pending[$ck])) {
                    $pending[$ck] = ['lat' => $lat, 'lon' => $lon, 'refs' => []];
                }
                $pending[$ck]['refs'][] = [&$trip, $addrKey, $posKey];
            }
        }
        unset($trip);

        foreach ($pending as $item) {
            $fallback = $this->formatCoordFallback($item['lat'], $item['lon']);

            if ($apiCalls >= $maxApiCalls) {
                foreach ($item['refs'] as $ref) {
                    $ref[0][$ref[1]] = $fallback;
                }

                continue;
            }

            $cacheKey = $this->geoCacheKey($item['lat'], $item['lon']);
            $wasCached = Cache::has($cacheKey);
            $addr = $this->getAddress($item['lat'], $item['lon']);

            if (! $wasCached) {
                $apiCalls++;
                usleep(1100000);
            }

            $resolved = $addr !== '' ? $addr : $fallback;

            foreach ($item['refs'] as $ref) {
                $ref[0][$ref[1]] = $resolved;
                if ($addr !== '' && ! empty($ref[0][$ref[2]])) {
                    $this->persistPositionAddress((int) $ref[0][$ref[2]], $addr);
                }
            }
        }

        return $trips;
    }
}
