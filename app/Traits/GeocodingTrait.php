<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait GeocodingTrait
{
    /**
     * Get address from latitude and longitude using Nominatim with caching
     * 
     * @param float|string $lat
     * @param float|string $lon
     * @return string
     */
    public function getAddress($lat, $lon)
    {
        if (empty($lat) || empty($lon)) return '';

        // Round to 4 decimal places (~11m precision) to improve cache hit rate
        $lat = round((float)$lat, 4);
        $lon = round((float)$lon, 4);
        $lang = 'en';
        $key = "geo_addr_{$lang}_{$lat}_{$lon}";

        return Cache::remember($key, 86400 * 30, function () use ($lat, $lon, $lang) {
            try {
                $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lon}&zoom=18&addressdetails=1&accept-language={$lang}";
                $response = Http::withHeaders([
                    'User-Agent' => 'OmayerFleetSystem/1.0',
                    'Accept-Language' => $lang
                ])->get($url);
                if ($response->successful()) {
                    return $response->json()['display_name'] ?? '';
                }
            } catch (\Throwable $e) {
                Log::error("Geocoding failed: " . $e->getMessage());
            }
            return '';
        });
    }
}
