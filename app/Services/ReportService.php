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
            // Filter out unrealistic speeds (> 162 knots approx 300 km/h)
            if (isset($trip['averageSpeed']) && $trip['averageSpeed'] <= 162) {
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
        $rawMaxSpeed = $maxSpeed;
        // Sanity check for max speed (> 162 knots is unrealistic)
        if ($rawMaxSpeed > 162) {
            $rawMaxSpeed = 0;
        }

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
                'maxSpeed' => $rawMaxSpeed * 1.852, // knots to km/h
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
            $fuel = round(optional($dayTrips->first()['spentFuel'] ?? null)['value'] ?? 0, 2); // Approximate if available per trip, but usually it's per report item.
            // If spentFuel is not in trips, we might need summary report. But let's assume 0 for now if missing.

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
                'key' => $key, // Unique key for frontend
                'date' => date('d/m/Y', strtotime($date)),
                'dateRaw' => $date,
                'vehicleId' => $deviceId ?? 'All',
                'vehicle' => $deviceId ? ($dayTrips->first()['deviceName'] ?? 'Unknown') : 'All Vehicles',
                'distance' => round($distance / 1000, 2) . ' KM',
                'distance_m' => $distance,
                'trip' => sprintf('%dh %dm %ds', $durH, $durM, $durS),
                'trip_ms' => $durationMs,
                'idle' => sprintf('%dh %dm %ds', $idleH, $idleM, $idleS),
                'idle_ms' => $idleMs,
                'idlePct' => $pct . '%'
            ];
        });

        $rows = $result->values();

        // Calculate Totals for Summary Widget
        $totalDistance = $rows->sum('distance_m');
        $totalDuration = $rows->sum('trip_ms');
        $totalIdle = $rows->sum('idle_ms');

        // Chart Data
        $chartData = $rows->map(function($r) {
            return [
                'date' => $r['dateRaw'],
                'distance' => $r['distance_m'],
                'tripDuration' => $r['trip_ms'],
                'idleDuration' => $r['idle_ms']
            ];
        });

        return [
            'rows' => $rows,
            'summary' => [
                'totalDistance' => $totalDistance,
                'totalDuration' => $totalDuration,
                'totalIdle' => $totalIdle,
                'totalFuel' => 0, // Placeholder as fuel data isn't reliably in trip list
                'avgKmL' => 0 // Placeholder
            ],
            'chart' => $chartData
        ];
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
                'key' => $key, // Unique key for frontend (YYYY-MM or YYYY-MM_deviceId)
                'date' => $dateStr,
                'dateRaw' => $month, // YYYY-MM for sorting
                'vehicleId' => $deviceId ?? 'All',
                'vehicle' => $deviceId ? ($monthTrips->first()['deviceName'] ?? 'Unknown') : 'All Vehicles',
                'distance' => round($distance / 1000, 2) . ' KM',
                'distance_m' => $distance,
                'trip' => sprintf('%dd %02dh %02dm', floor($durH/24), $durH%24, $durM),
                'trip_ms' => $durationMs,
                'idle' => sprintf('%dh %dm', $idleH, $idleM),
                'idle_ms' => $idleMs,
                'idlePct' => $pct . '%'
            ];
        });

        $rows = $result->values();

        // Calculate Totals for Summary Widget
        $totalDistance = $rows->sum('distance_m');
        $totalDuration = $rows->sum('trip_ms');
        $totalIdle = $rows->sum('idle_ms');

        // Chart Data
        $chartData = $rows->map(function($r) {
            return [
                'date' => $r['dateRaw'],
                'distance' => $r['distance_m'],
                'tripDuration' => $r['trip_ms'],
                'idleDuration' => $r['idle_ms']
            ];
        });

        return [
            'rows' => $rows,
            'summary' => [
                'totalDistance' => $totalDistance,
                'totalDuration' => $totalDuration,
                'totalIdle' => $totalIdle,
                'totalFuel' => 0,
                'avgKmL' => 0
            ],
            'chart' => $chartData
        ];
    }

    public function fetchDailyBreakdownMap($request, $deviceIds)
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

        $trips = collect([]);
        $events = collect([]);
        $stops = collect([]);
        $routes = collect([]);

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('trips')->withHeaders($headers)->get("{$baseUrl}/api/reports/trips?{$fullQuery}"),
                $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
                $pool->as('stops')->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}"),
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
                 Log::error("DailyBreakdownMap Route API Error", [
                    'status' => $routeResponse->status(),
                    'body' => substr($routeResponse->body(), 0, 500)
                ]);
            }
        } catch (\Throwable $e) {
             Log::error('fetchDailyBreakdownMap route exception', ['error' => $e->getMessage()]);
             // Continue without routes
        }

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
            $dayRoutes = $routes->filter(function($r) use ($dayTrips, $deviceId) {
                if ($r['deviceId'] != $deviceId) return false;
                $rTime = strtotime($r['fixTime']);
                // Check if point belongs to any trip in this day
                foreach ($dayTrips as $trip) {
                    $start = strtotime($trip['startTime']);
                    $end = strtotime($trip['endTime']);
                    if ($rTime >= $start && $rTime <= $end) {
                        return true;
                    }
                }
                return false;
            });

            // Build timeline
            $timeline = [];

            foreach ($dayTrips as $trip) {
                $durMs = $trip['duration'] ?? 0;
                $durSec = floor($durMs / 1000);
                $h = floor($durSec / 3600);
                $m = floor(($durSec % 3600) / 60);
                $s = $durSec % 60;
                $durStr = ($h > 0) ? sprintf('%dh %dm %ds', $h, $m, $s) : sprintf('%dm %ds', $m, $s);

                // Aggregate events for this trip
                $tripStart = strtotime($trip['startTime']);
                $tripEnd = strtotime($trip['endTime']);

                $tripEvents = $dayEvents->filter(function($e) use ($tripStart, $tripEnd) {
                    $t = strtotime($e['eventTime']);
                    return $t >= $tripStart && $t <= $tripEnd;
                });

                $svCount = $tripEvents->where('type', 'overspeed')->count();
                $haCount = $tripEvents->where('type', 'harshAcceleration')->count();
                $hbCount = $tripEvents->where('type', 'harshBraking')->count();

                $badges = [];
                if ($svCount > 0) $badges[] = "$svCount SV";
                if ($haCount > 0) $badges[] = "$haCount HA";
                if ($hbCount > 0) $badges[] = "$hbCount HB";
                $alertBadge = implode(', ', $badges);

                $timeline[] = [
                    'time_sort' => $tripStart,
                    'time' => date('h:i A', $tripStart),
                    'location' => $trip['startAddress'] ?? '',
                    'dist' => round(($trip['distance'] ?? 0)/1000, 2) . 'KM',
                    'dur' => $durStr,
                    'alert' => $alertBadge,
                    'type' => 'start',
                    'lat' => $trip['startLat'] ?? 0,
                    'lon' => $trip['startLon'] ?? 0
                ];
                $timeline[] = [
                    'time_sort' => $tripEnd,
                    'time' => date('h:i A', $tripEnd),
                    'location' => $trip['endAddress'] ?? '',
                    'type' => 'end',
                    'lat' => $trip['endLat'] ?? 0,
                    'lon' => $trip['endLon'] ?? 0
                ];
            }

            // Events (Hidden in list, visible on map)
            foreach ($dayEvents as $event) {
                if ($event['type'] == 'deviceOnline' || $event['type'] == 'deviceOffline') continue;

                $eventTs = strtotime($event['eventTime']);
                $closest = $dayRoutes->sortBy(function($r) use ($eventTs) {
                    return abs(strtotime($r['fixTime']) - $eventTs);
                })->first();

                $lat = $closest['latitude'] ?? 0;
                $lon = $closest['longitude'] ?? 0;
                $addr = $closest['address'] ?? '';

                $friendlyName = $event['type'];
                if ($event['type'] == 'overspeed') $friendlyName = 'Overspeed';
                if ($event['type'] == 'harshAcceleration') $friendlyName = 'Harsh Acceleration';
                if ($event['type'] == 'harshBraking') $friendlyName = 'Harsh Braking';

                $timeline[] = [
                    'time_sort' => $eventTs,
                    'time' => date('h:i A', $eventTs),
                    'location' => $addr,
                    'alert' => $friendlyName,
                    'type' => 'alert',
                    'lat' => $lat,
                    'lon' => $lon,
                    'hidden' => true
                ];
            }

            // Stops
            foreach ($dayStops as $stop) {
                $stopTs = strtotime($stop['startTime']);
                $timeline[] = [
                    'time_sort' => $stopTs,
                    'time' => date('h:i A', $stopTs),
                    'location' => $stop['address'] ?? '',
                    'type' => 'stop',
                    'lat' => $stop['latitude'] ?? 0,
                    'lon' => $stop['longitude'] ?? 0
                ];
            }

            // Sort timeline
            usort($timeline, function($a, $b) {
                return $a['time_sort'] <=> $b['time_sort'];
            });

            // Route points
            $routePoints = $dayRoutes->map(function($r) {
                return [$r['latitude'], $r['longitude'], strtotime($r['fixTime']) * 1000];
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
            if ($totalSV > 0) $badges[] = "$totalSV SV";
            if ($totalHA > 0) $badges[] = "$totalHA HA";
            if ($totalHB > 0) $badges[] = "$totalHB HB";
            $behavStr = empty($badges) ? '0' : implode(', ', $badges);

            return [
                'key' => $key,
                'date' => date('d/m/Y - l', strtotime($date)),
                'distance' => round($totalDist/1000, 2) . ' KM',
                'isOpen' => true,
                'summary' => [
                    'date' => date('d/m/Y - l', strtotime($date)),
                    'dist' => round($totalDist/1000, 2) . ' km',
                    'dur' => $formattedDur,
                    'idle' => $formattedIdle,
                    'behav' => $behavStr
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
    public function fetchAssetActivity($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');

        if (empty($deviceIds)) return [];

        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) return [];

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json'
        ];

        // Build query string for multiple devices
        $deviceParams = [];
        foreach ($deviceIds as $id) {
            $deviceParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $deviceParams);
        $queryString = "{$deviceQuery}&from={$from}&to={$to}";

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$queryString}"),
                $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$queryString}"),
                $pool->as('summary')->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$queryString}"),
            ]);
        } catch (\Exception $e) {
            Log::error('fetchAssetActivity exception', ['error' => $e->getMessage()]);
            return [];
        }

        $route = ($responses['route']->ok()) ? $responses['route']->json() : [];
        $events = ($responses['events']->ok()) ? $responses['events']->json() : [];
        $summaryData = ($responses['summary']->ok()) ? $responses['summary']->json() : [];

        // Map deviceId to deviceName
        $deviceMap = [];
        foreach ($summaryData as $s) {
            if (isset($s['deviceId'])) {
                $deviceMap[$s['deviceId']] = $s['deviceName'] ?? 'Device ' . $s['deviceId'];
            }
        }

        // Normalize Data for Merging
        $merged = collect();

        // 1. Process Route (Positions)
        foreach ($route as $pos) {
            $time = $pos['fixTime'] ?? $pos['deviceTime'];
            $merged->push([
                'type' => 'position',
                'raw' => $pos,
                'timestamp' => $time,
                'epoch' => strtotime($time),
            ]);
        }

        // 2. Process Events
        foreach ($events as $evt) {
            $time = $evt['eventTime'] ?? $evt['serverTime'];
            $merged->push([
                'type' => 'event',
                'raw' => $evt,
                'timestamp' => $time,
                'epoch' => strtotime($time),
            ]);
        }

        // 3. Sort by Time
        $sorted = $merged->sortBy('epoch')->values();

        // 4. Map to Display Format
        $rows = $sorted->map(function ($item, $index) use ($deviceMap) {
            $raw = $item['raw'];
            $isPos = $item['type'] === 'position';

            $dId = $raw['deviceId'] ?? 0;
            $vehicleName = $deviceMap[$dId] ?? 'Unknown';

            $dt = strtotime($item['timestamp']);
            $date = date('d-m-Y', $dt);
            $day = date('l', $dt); // Monday
            $time = date('H:i:s', $dt);

            $attrs = $raw['attributes'] ?? [];

            // Status
            $status = $isPos ? 'Position Log' : $this->formatEventDescription($raw);

            // Location
            $lat = $isPos ? ($raw['latitude'] ?? 0) : 0;
            $lon = $isPos ? ($raw['longitude'] ?? 0) : 0;
            // Round coords
            $lat = $lat ? round($lat, 5) : '';
            $lon = $lon ? round($lon, 5) : '';

            $location = $raw['address'] ?? '';

            // Speed
            $speedKnots = $raw['speed'] ?? 0;
            $speedKph = round($speedKnots * 1.852, 0);

            // Direction
            $course = $raw['course'] ?? 0;

            // GSM/GPS
            $gsm = $attrs['rssi'] ?? null;
            $gps = $attrs['sat'] ?? null;

            // Power
            $power = $attrs['power'] ?? $attrs['battery'] ?? null;
            if ($power) $power = round($power, 1) . 'V';

            // Ignition
            $ignition = $attrs['ignition'] ?? null; // boolean

            // Fuel
            $fuel = $attrs['fuel'] ?? null;
            if ($fuel) $fuel = round($fuel, 1) . ' L';

            return [
                'key' => $index,
                'vehicle' => $vehicleName,
                'groupDate' => "$date $day",
                'date' => $date,
                'time' => $time,
                'status' => $status,
                'lat' => $lat,
                'lon' => $lon,
                'location' => $location,
                'direction' => $course,
                'speed' => $speedKph . ' km/h',
                'gsm' => $gsm,
                'gps' => $gps,
                'power' => $power,
                'ignition' => $ignition,
                'fuel' => $fuel,
                'isEvent' => !$isPos,
                'rawType' => $isPos ? 'position' : ($raw['type'] ?? '')
            ];
        });

        // Header Info
        $lastRow = $sorted->last();
        $lastTime = $lastRow ? date('Y-m-d H:i:s', $lastRow['epoch']) : 'N/A';

        $lastPos = $sorted->where('type', 'position')->last();
        $lastAddress = $lastPos['raw']['address'] ?? '';
        if (!$lastAddress && $lastPos) {
            $lastAddress = round($lastPos['raw']['latitude'], 5) . ', ' . round($lastPos['raw']['longitude'], 5);
        }

        $vehicleLabel = count($deviceIds) > 1 ? 'Multiple Vehicles (' . count($deviceIds) . ')' : ($summaryData[0]['deviceName'] ?? 'Unknown');
        $deviceIdLabel = count($deviceIds) > 1 ? 'Multiple' : ($deviceIds[0] ?? 'N/A');

        $header = [
            'vehicleId' => $vehicleLabel,
            'deviceId' => $deviceIdLabel,
            'duration' => date('Y/m/d H:i', strtotime($from)) . ' - ' . date('Y/m/d H:i', strtotime($to)),
            'lastReport' => $lastTime,
            'lastLocation' => $lastAddress,
        ];

        return [
            'header' => $header,
            'rows' => $rows
        ];
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
                return "Harsh braking detected";

            case 'harshAcceleration':
                return "Harsh acceleration detected";

            case 'ignitionOn':
                return "Ignition turned ON";

            case 'ignitionOff':
                return "Ignition turned OFF";

            case 'geofenceEnter':
                return "Entered geofence";

            case 'geofenceExit':
                return "Exited geofence";

            case 'deviceStopped':
                return "Device stopped";

            case 'deviceOnline':
                return "Device online";

            case 'deviceOffline':
                return "Device offline";

            case 'deviceMoving':
                return "Device moving";

            case 'alarm':
                $alarmKey = $attributes['alarm'] ?? 'general';
                return "Alarm: " . ucfirst($alarmKey);

            default:
                return ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $type));
        }
    }

}

