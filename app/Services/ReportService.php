<?php

namespace App\Services;

use App\Helpers\Curl;
use App\Models\Devices;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Class ReportService
 * @package App\Services
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

    public function travelHistory($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $id = $request->device_id;
        $data = 'id=' . $id;
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date ?? date('Y-01-01 00:00:00')));
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date ?? date('Y-01-01 00:00:00')));

        $data = 'deviceId=' . $id . '&from=' . $from . '&to=' . $to;
        $tripsRaw = static::curl('/api/reports/trips?' . $data, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        // dd($tripsRaw);
        $trips = [];
        if ($tripsRaw->responseCode ==200 && isset($tripsRaw->responseCode)) {
            // $tripsRaw = json_decode($tripsRaw->response);
            $trips = json_decode($tripsRaw->response);
            // $trips = is_array($tmp) ? $tmp : [];
        }
        // dd($trips);
        return $trips;
    }

    public function yearlyReportDashboard($request)
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
        foreach ($trips as $trip) {
            if (empty($trip['startTime']))
                continue;

            $monthKey = $months[date('n', strtotime($trip['startTime'])) - 1];

            // Distance (m → km)
            if (isset($trip['distance']))
                $monthlyDistance[$monthKey] += round($trip['distance'] / 1000, 1);

            // Fuel spent
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
            if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                $monthlyFuel[$monthKey] += round($startFuel - $endFuel, 1);
            }

            // Average speed (knots → km/h)
            if (isset($trip['averageSpeed'])) {
                $monthlySpeedData[$monthKey]['total'] += $trip['averageSpeed'] * 1.852;
                $monthlySpeedData[$monthKey]['count'] += 1;
            }
        }

        // Compute monthly avg speed
        $monthlyAvgSpeed = [];
        foreach ($months as $m) {
            $total = $monthlySpeedData[$m]['total'];
            $cnt = $monthlySpeedData[$m]['count'] ?: 1;
            $monthlyAvgSpeed[$m] = round($total / $cnt, 1);
        }

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
        $eventTypes = trim((string)($request->event_types ?? 'harshBraking,harshAcceleration,overspeed'));
        if ($eventTypes === '') { $eventTypes = 'harshBraking,harshAcceleration,overspeed'; }
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
            $eventsResponse = static::curl("/api/reports/events?$queryString&type=" . urlencode($eventTypes), 'GET', $sessionId, '', $headers);
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
                    'idleTime_minutes' => round(array_sum(array_map(fn($s) => $s->duration ?? 0, $stops)) / 60000, 1),
                    'harshBraking' => count(array_filter($events, fn($e) => $e->type === 'harshBraking')),
                    'harshAcceleration' => count(array_filter($events, fn($e) => $e->type === 'harshAcceleration')),
                    'overspeedEvents' => count(array_filter($events, fn($e) => $e->type === 'overspeed')),
                ];
            });

            return $reportData;
        });
    }

    public function fetchFleetSummary($request, $deviceIds)
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
        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $commonQuery = "from={$from}&to={$to}";
        $fullQuery = "{$deviceQuery}&{$commonQuery}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
            Log::error('ReportService: Traccar Host URL is not configured.');
            return [];
        }

        $eventTypes = 'harshBraking,harshAcceleration,overspeed,fuelIncrease';

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            // Execute requests in parallel using HTTP Pool
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('summary')->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}&type=" . urlencode($eventTypes)),
            ]);
        } catch (\Exception $e) {
            Log::error('ReportService: Failed to fetch fleet summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }

        // Debug logging
        foreach ($responses as $key => $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && !$response->ok()) {
                Log::error("ReportService API Error [{$key}]", [
                    'url' => "{$baseUrl}/api/reports/{$key}",
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $fullQuery
                ]);
            }
        }

        $summaryRes = $responses['summary'] ?? null;
        $stopsRes = $responses['stops'] ?? null;
        $eventsRes = $responses['events'] ?? null;

        $allSummary = ($summaryRes instanceof \Illuminate\Http\Client\Response && $summaryRes->ok()) ? $summaryRes->json() : [];
        $allStops = ($stopsRes instanceof \Illuminate\Http\Client\Response && $stopsRes->ok()) ? $stopsRes->json() : [];
        $allEvents = ($eventsRes instanceof \Illuminate\Http\Client\Response && $eventsRes->ok()) ? $eventsRes->json() : [];

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
            $refillTotal = 0;
            foreach ($refills as $refill) {
                $attrs = $refill['attributes'] ?? [];
                if (isset($attrs['amount'])) {
                    $refillTotal += $attrs['amount'];
                }
            }
            $refillCount = $refills->count();

            $avgSpeed = round(($item['averageSpeed'] ?? 0) * 1.852, 1);

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
                'util' => $utilPct . '%',
                'avgLitres' => $avgLitresPerDay,
                'avgKmL' => $avgKmL,
                'fuelRefill' => round($refillTotal, 1) . ' L',
                'fuelRefillFreq' => $refillCount,
                'speed' => $avgSpeed . ' km/h'
            ];
        });

        return $reportData->values();
    }

    public function fetchDailyTrips($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
             Log::error('ReportService: Traccar Host URL is not configured.');
             return [
                 'rows' => [],
                 'summary' => []
             ];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
            ]);
        } catch (\Exception $e) {
             Log::error('fetchDailyTrips exception', ['error' => $e->getMessage()]);
             return [
                 'rows' => [],
                 'summary' => []
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
                'distance' => round(($trip['distance'] ?? 0) / 1000, 2) . ' KM',
                // Raw values for calculation if needed
                'distance_m' => $trip['distance'] ?? 0,
                'duration_ms' => $trip['duration'] ?? 0
            ];
        });

        $stopsFormatted = collect($allStops)->map(function ($stop) {
            return [
                'startTime' => date('h:i A', strtotime($stop['startTime'])),
                'startTimeIso' => $stop['startTime'],
                'endTime' => date('h:i A', strtotime($stop['endTime'])),
                'endTimeIso' => $stop['endTime'],
                'duration_ms' => $stop['duration'] ?? 0,
                'address' => $stop['address'] ?? 'N/A'
            ];
        });

        // Calculate Summary
        $totalDistance = collect($allTrips)->sum('distance');
        $totalDuration = collect($allTrips)->sum('duration');
        $totalIdle = collect($allStops)->sum('duration');
        $maxSpeed = collect($allTrips)->max('maxSpeed') ?? 0;

        // Fuel (if available in trip attributes)
        $totalFuel = 0;
        foreach ($allTrips as $trip) {
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
             if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                $totalFuel += ($startFuel - $endFuel);
            } else {
                 $totalFuel += data_get($trip, 'spentFuel', 0);
            }
        }

        return [
            'rows' => $rows,
            'stops' => $stopsFormatted,
            'summary' => [
                'totalDistance' => $totalDistance, // meters
                'totalDuration' => $totalDuration, // ms
                'totalIdle' => $totalIdle, // ms
                'maxSpeed' => $maxSpeed * 1.852, // knots to km/h
                'totalFuel' => $totalFuel
            ]
        ];
    }

    public function fetchDailySummary($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
             Log::error('ReportService: Traccar Host URL is not configured.');
             return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
            ]);
        } catch (\Exception $e) {
            Log::error('fetchDailySummary exception', ['error' => $e->getMessage()]);
            return [];
        }

        $allTrips = ($responses['trips']->ok()) ? $responses['trips']->json() : [];
        $allStops = ($responses['stops']->ok()) ? $responses['stops']->json() : [];

        // Group by Date (and Device if needed, but DailySummary view usually is per vehicle or list of vehicles)
        // If multiple vehicles, we might want to return a list with vehicle name.
        // The view 'Daily Summary List' expects 'vehicle' column. 'Daily Summary' does not.
        // We can return 'vehicle' always.

        $trips = collect($allTrips);
        $stops = collect($allStops);

        $groupBy = $request->group_by ?? 'vehicle_date';

        // Group by Date and Device
        if ($groupBy === 'date') {
            $grouped = $trips->groupBy(function($item) {
                 return date('Y-m-d', strtotime($item['startTime']));
            });
        } else {
            $grouped = $trips->groupBy(function($item) {
                 return date('Y-m-d', strtotime($item['startTime'])) . '_' . $item['deviceId'];
            });
        }

        $result = $grouped->map(function ($dayTrips, $key) use ($stops, $groupBy) {
            if ($groupBy === 'date') {
                $date = $key;
                $deviceId = null; // Aggregated
                $dayStops = $stops->filter(function($stop) use ($date) {
                    return date('Y-m-d', strtotime($stop['startTime'])) == $date;
                });
            } else {
                list($date, $deviceId) = explode('_', $key);
                $dayStops = $stops->filter(function($stop) use ($date, $deviceId) {
                    return $stop['deviceId'] == $deviceId && date('Y-m-d', strtotime($stop['startTime'])) == $date;
                });
            }

            $distance = $dayTrips->sum('distance');
            $durationMs = $dayTrips->sum('duration');
            $idleMs = $dayStops->sum('duration');

            // Format Duration
            $durH = floor($durationMs / 3600000);
            $durM = floor(($durationMs % 3600000) / 60000);
            $durS = floor((($durationMs % 3600000) % 60000) / 1000);

            // Format Idle
            $idleH = floor($idleMs / 3600000);
            $idleM = floor(($idleMs % 3600000) / 60000);
            $idleS = floor((($idleMs % 3600000) % 60000) / 1000);

            // Idle Pct
            $totalTime = $durationMs + $idleMs;
            $pct = $totalTime > 0 ? round(($idleMs / $totalTime) * 100, 1) : 0;

            return [
                'date' => date('d/m/Y', strtotime($date)),
                'vehicleId' => $deviceId ?? 'All',
                'vehicle' => $deviceId ? ($dayTrips->first()['deviceName'] ?? 'Unknown') : 'All Vehicles',
                'distance' => round($distance / 1000, 2) . ' KM',
                'trip' => sprintf('%dh %dm %ds', $durH, $durM, $durS),
                'idle' => sprintf('%dh %dm %ds', $idleH, $idleM, $idleS),
                'idlePct' => $pct . '%'
            ];
        });

        return $result->values();
    }

    public function fetchMonthlySummary($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
             Log::error('ReportService: Traccar Host URL is not configured.');
             return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
            ]);
        } catch (\Exception $e) {
            Log::error('fetchMonthlySummary exception', ['error' => $e->getMessage()]);
            return [];
        }

        $allTrips = ($responses['trips']->ok()) ? $responses['trips']->json() : [];
        $allStops = ($responses['stops']->ok()) ? $responses['stops']->json() : [];

        $trips = collect($allTrips);
        $stops = collect($allStops);

        $groupBy = $request->group_by ?? 'vehicle_month';

        // Group by Month and Device
        if ($groupBy === 'month') {
            $grouped = $trips->groupBy(function($item) {
                 return date('Y-m', strtotime($item['startTime']));
            });
        } else {
            $grouped = $trips->groupBy(function($item) {
                 return date('Y-m', strtotime($item['startTime'])) . '_' . $item['deviceId'];
            });
        }

        $result = $grouped->map(function ($monthTrips, $key) use ($stops, $groupBy) {
            if ($groupBy === 'month') {
                $month = $key;
                $deviceId = null;
                $monthStops = $stops->filter(function($stop) use ($month) {
                    return date('Y-m', strtotime($stop['startTime'])) == $month;
                });
            } else {
                list($month, $deviceId) = explode('_', $key);
                $monthStops = $stops->filter(function($stop) use ($month, $deviceId) {
                    return $stop['deviceId'] == $deviceId && date('Y-m', strtotime($stop['startTime'])) == $month;
                });
            }

            $distance = $monthTrips->sum('distance');
            $durationMs = $monthTrips->sum('duration');
            $idleMs = $monthStops->sum('duration');

            $durH = floor($durationMs / 3600000);
            $durM = floor(($durationMs % 3600000) / 60000);

            $idleH = floor($idleMs / 3600000);
            $idleM = floor(($idleMs % 3600000) / 60000);

            $totalTime = $durationMs + $idleMs;
            $pct = $totalTime > 0 ? round(($idleMs / $totalTime) * 100, 1) : 0;

            // Format date 08/2025
            $dateStr = date('m/Y', strtotime($month));

            return [
                'date' => $dateStr,
                'vehicleId' => $deviceId ?? 'All',
                'vehicle' => $deviceId ? ($monthTrips->first()['deviceName'] ?? 'Unknown') : 'All Vehicles',
                'distance' => round($distance / 1000, 2) . ' KM',
                'trip' => sprintf('%dd %02dh %02dm', floor($durH/24), $durH%24, $durM),
                'idle' => sprintf('%dh %dm', $idleH, $idleM),
                'idlePct' => $pct . '%'
            ];
        });

        return $result->values();
    }

    public function fetchDailyBreakdownMap($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));

        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $queryParams = [];
        foreach ($deviceIds as $id) { $queryParams[] = "deviceId={$id}"; }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {
             Log::error('ReportService: Traccar Host URL is not configured.');
             return [];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
                $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$fullQuery}"),
            ]);
        } catch (\Exception $e) {
            Log::error('fetchDailyBreakdownMap exception', ['error' => $e->getMessage()]);
            return [];
        }

        $trips = collect(($responses['trips']->ok()) ? $responses['trips']->json() : []);
        $events = collect(($responses['events']->ok()) ? $responses['events']->json() : []);
        $stops = collect(($responses['stops']->ok()) ? $responses['stops']->json() : []);
        $routes = collect(($responses['route']->ok()) ? $responses['route']->json() : []);

        $grouped = $trips->groupBy(function($t) {
             return date('Y-m-d', strtotime($t['startTime'])) . '_' . $t['deviceId'];
        });

        $result = $grouped->map(function($dayTrips, $key) use ($events, $stops, $routes) {
            list($date, $deviceId) = explode('_', $key);
            $deviceName = $dayTrips->first()['deviceName'] ?? 'Unknown';

            $dayEvents = $events->filter(function($e) use ($date, $deviceId) {
                return $e['deviceId'] == $deviceId && date('Y-m-d', strtotime($e['eventTime'])) == $date;
            });
            $dayStops = $stops->filter(function($s) use ($date, $deviceId) {
                return $s['deviceId'] == $deviceId && date('Y-m-d', strtotime($s['startTime'])) == $date;
            });
            $dayRoutes = $routes->filter(function($r) use ($date, $deviceId) {
                return $r['deviceId'] == $deviceId && date('Y-m-d', strtotime($r['fixTime'])) == $date;
            });

            // Build timeline
            $timeline = [];

            foreach ($dayTrips as $trip) {
                $timeline[] = [
                    'time_sort' => strtotime($trip['startTime']),
                    'time' => date('h:i A', strtotime($trip['startTime'])),
                    'location' => $trip['startAddress'] ?? '',
                    'dist' => round(($trip['distance'] ?? 0)/1000, 1) . 'KM',
                    'dur' => gmdate('H\h i\m', ($trip['duration'] ?? 0)/1000),
                    'type' => 'start',
                    'lat' => $trip['startLat'] ?? 0,
                    'lon' => $trip['startLon'] ?? 0
                ];
                $timeline[] = [
                    'time_sort' => strtotime($trip['endTime']),
                    'time' => date('h:i A', strtotime($trip['endTime'])),
                    'location' => $trip['endAddress'] ?? '',
                    'type' => 'end',
                    'lat' => $trip['endLat'] ?? 0,
                    'lon' => $trip['endLon'] ?? 0
                ];
            }

            foreach ($dayEvents as $event) {
                if ($event['type'] == 'deviceOnline' || $event['type'] == 'deviceOffline') continue;

                $eventTs = strtotime($event['eventTime']);
                $closest = $dayRoutes->sortBy(function($r) use ($eventTs) {
                    return abs(strtotime($r['fixTime']) - $eventTs);
                })->first();

                $lat = $closest['latitude'] ?? 0;
                $lon = $closest['longitude'] ?? 0;
                $addr = $closest['address'] ?? '';
                
                // Friendly event name
                $friendlyName = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $event['type']));

                $timeline[] = [
                    'time_sort' => $eventTs,
                    'time' => date('h:i A', $eventTs),
                    'location' => $addr,
                    'alert' => $friendlyName, // e.g. "Harsh Braking"
                    'type' => 'alert',
                    'lat' => $lat,
                    'lon' => $lon
                ];
            }

            // Add Stops to Timeline
            foreach ($dayStops as $stop) {
                $stopTs = strtotime($stop['startTime']);
                $timeline[] = [
                    'time_sort' => $stopTs,
                    'time' => date('h:i A', $stopTs),
                    'location' => $stop['address'] ?? '',
                    'dur' => gmdate('H\h i\m', ($stop['duration'] ?? 0)/1000),
                    'type' => 'stop', // New type
                    'lat' => $stop['latitude'] ?? 0,
                    'lon' => $stop['longitude'] ?? 0
                ];
            }

            usort($timeline, function($a, $b) { return $a['time_sort'] <=> $b['time_sort']; });

            $totalDist = $dayTrips->sum('distance');
            $totalDur = $dayTrips->sum('duration');
            $totalIdle = $dayStops->sum('duration');

            // Format route for map: [[lat, lon, timestamp], ...]
            $routePoints = $dayRoutes->map(function($r) {
                return [
                    $r['latitude'], 
                    $r['longitude'],
                    strtotime($r['fixTime']) * 1000 // ms for JS
                ];
            })->values()->all();

            return [
                'key' => $key,
                'date' => date('d/m/Y - l', strtotime($date)) . ' (' . $deviceName . ')',
                'distance' => round($totalDist/1000, 2) . ' KM',
                'isOpen' => false,
                'summary' => [
                    'date' => date('d/m/Y - l', strtotime($date)),
                    'dist' => round($totalDist/1000, 2) . ' km',
                    'dur' => gmdate('H\h i\m s\s', $totalDur/1000),
                    'idle' => gmdate('H\h i\m s\s', $totalIdle/1000),
                    'behav' => $dayEvents->count() . ' Events'
                ],
                'timeline' => $timeline,
                'route' => $routePoints
            ];
        });

        return $result->values();
    }

    public function getDeviceEvents($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $deviceId = $request->device_id;

        // Check permission and get device details
        $deviceName = Devices::accessibleByUser($request->user())
            ->where('device_id', $deviceId)
            ->first();

        if (!$deviceName) {
             // Return empty or throw exception?
             // Since this returns an array, maybe return empty array or let the controller handle it.
             // But existing code didn't check for null, so it would crash.
             // Let's assume valid request for now but at least scope the query.
             // If we want to be safe:
             return [];
        }

        // Format the timestamps
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'))));

        $toStr = $request->to_date;
        if (!$toStr) {
            $to = date('Y-m-d\TH:i:00\Z');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
            $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));
        }

        $queryString = "deviceId={$deviceId}&from={$from}&to={$to}&type=allEvents";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        // Events Report - following same pattern as other methods
        $eventsResponse = static::curl("/api/reports/events?$queryString", 'GET', $sessionId, '', $headers);

        $events = [];
        if ($eventsResponse->responseCode == 200 && isset($eventsResponse->responseCode)) {
            $events = json_decode($eventsResponse->response);
            $events = is_array($events) ? $events : [];
        }
        // dd($events);
        // Format events data
        $formattedEvents = [];
        if (is_array($events)) {
            foreach ($events as $event) {
                $formattedEvents[] = [
                    'eventTime' => $event->eventTime ?? $event->serverTime ?? date('Y-m-d H:i:s'),
                    'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                    'type' => $event->type ?? 'unknown',
                    'description' => $this->formatEventDescription($event),
                    'attributes' => $event->attributes ?? null
                ];
            }
        }

        return $formattedEvents;
    }

        public function getDeviceStops($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $deviceId = $request->device_id;

        // Check permission and get device details
        $deviceName = Devices::accessibleByUser($request->user())
            ->where('device_id', $deviceId)
            ->first();

        if (!$deviceName) {
             return [];
        }

        // Format the timestamps
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'))));

        $toStr = $request->to_date;
        if (!$toStr) {
            $to = date('Y-m-d\TH:i:00\Z');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
            $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));
        }

        $queryString = "deviceId={$deviceId}&from={$from}&to={$to}&type=allEvents";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        // Events Report - following same pattern as other methods
        $eventsResponse = static::curl("/api/reports/stops?$queryString", 'GET', $sessionId, '', $headers);

        $events = [];
        if ($eventsResponse->responseCode == 200 && isset($eventsResponse->responseCode)) {
            $events = json_decode($eventsResponse->response);
            $events = is_array($events) ? $events : [];
        }
        // dd($events);
        // Format events data
        $formattedEvents = [];
        if (is_array($events)) {
            foreach ($events as $event) {
                $formattedEvents[] = [
                    'eventTime' => $event->eventTime ?? $event->serverTime ?? date('Y-m-d H:i:s'),
                    'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                    'type' => $event->type ?? 'unknown',
                    'description' => $this->formatEventDescription($event),
                    'attributes' => $event->attributes ?? null
                ];
            }
        }

        return $formattedEvents;
    }
    private function formatEventDescription($event)
    {
        $type = $event->type ?? 'unknown';
        $deviceName = $event->deviceName ?? 'Device';

        switch ($type) {
            case 'overspeed':
                $speed = isset($event->speed) ? round($event->speed * 1.852, 1) : 'Unknown';
                return "{$deviceName} exceeded speed limit at {$speed} km/h";

            case 'harshBraking':
                return "{$deviceName} performed harsh braking";

            case 'harshAcceleration':
                return "{$deviceName} performed harsh acceleration";

            case 'ignitionOn':
                return "{$deviceName} ignition turned on";

            case 'ignitionOff':
                return "{$deviceName} ignition turned off";

            case 'geofenceEnter':
                return "{$deviceName} entered geofence zone";

            case 'geofenceExit':
                return "{$deviceName} exited geofence zone";

            case 'alarm':
                $alarm = 'Unknown alarm';
                if (isset($event->attributes)) {
                    $attributes = is_string($event->attributes) ? json_decode($event->attributes, true) : $event->attributes;
                    $alarm = $attributes['alarms'] ?? 'Unknown alarm';
                }
                return "{$deviceName} alarm: {$alarm}";

            default:
                return "{$deviceName} event: {$type}";
        }
    }

}

