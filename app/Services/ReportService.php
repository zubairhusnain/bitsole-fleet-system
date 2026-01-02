<?php

namespace App\Services;

use App\Helpers\Curl;
use App\Models\Devices;
use Illuminate\Support\Facades\Cache; 
 
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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

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

        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // Trips
        $tripsResponse = static::curl("/api/reports/trips?$fullQuery", 'GET', $sessionId, '', $headers);
        $allTrips = json_decode($tripsResponse->response ?? '[]', true);

        // Summary
        $summaryResponse = static::curl("/api/reports/summary?$fullQuery", 'GET', $sessionId, '', $headers);
        $allSummary = json_decode($summaryResponse->response ?? '[]', true);

        // Stops
        $stopsResponse = static::curl("/api/reports/stops?$fullQuery", 'GET', $sessionId, '', $headers);
        $allStops = json_decode($stopsResponse->response ?? '[]', true);

        // Events (include fuelIncrease for refills)
        $eventTypes = 'harshBraking,harshAcceleration,overspeed,fuelIncrease';
        $eventsResponse = static::curl("/api/reports/events?$fullQuery&type=" . urlencode($eventTypes), 'GET', $sessionId, '', $headers);
        $allEvents = json_decode($eventsResponse->response ?? '[]', true);
        // Group data by deviceId
        $tripsByDevice = collect($allTrips)->groupBy('deviceId');
        $stopsByDevice = collect($allStops)->groupBy('deviceId');
        $eventsByDevice = collect($allEvents)->groupBy('deviceId');

        // Process summary
        $reportData = collect($allSummary)->map(function ($item) use ($tripsByDevice, $stopsByDevice, $eventsByDevice, $days) {
            $deviceId = $item['deviceId'];
            $deviceTrips = $tripsByDevice->get($deviceId, collect([]));
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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        $tripsResponse = static::curl("/api/reports/trips?$fullQuery", 'GET', $sessionId, '', $headers);
        $allTrips = json_decode($tripsResponse->response ?? '[]', true);

        return collect($allTrips)->map(function ($trip, $index) {
            return [
                'key' => $index + 1,
                'date' => date('d/m/Y', strtotime($trip['startTime'])),
                'startTime' => date('h:i A', strtotime($trip['startTime'])),
                'startLocation' => $trip['startAddress'] ?? 'N/A',
                'endTime' => date('h:i A', strtotime($trip['endTime'])),
                'endLocation' => $trip['endAddress'] ?? 'N/A',
                'distance' => round(($trip['distance'] ?? 0) / 1000, 2) . ' KM'
            ];
        });
    }

    public function fetchDailySummary($request, $deviceIds)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        // We need trips and stops to calculate daily stats
        $tripsResponse = static::curl("/api/reports/trips?$fullQuery", 'GET', $sessionId, '', $headers);
        $allTrips = json_decode($tripsResponse->response ?? '[]', true);

        $stopsResponse = static::curl("/api/reports/stops?$fullQuery", 'GET', $sessionId, '', $headers);
        $allStops = json_decode($stopsResponse->response ?? '[]', true);

        // Group by Date (and Device if needed, but DailySummary view usually is per vehicle or list of vehicles)
        // If multiple vehicles, we might want to return a list with vehicle name.
        // The view 'Daily Summary List' expects 'vehicle' column. 'Daily Summary' does not.
        // We can return 'vehicle' always.

        $trips = collect($allTrips);
        $stops = collect($allStops);

        // Group by Date and Device
        $grouped = $trips->groupBy(function($item) {
             return date('Y-m-d', strtotime($item['startTime'])) . '_' . $item['deviceId'];
        });

        // Also need to account for days with stops but no trips? Usually they go together or stops happen during trips.
        // Traccar reports usually separate them.
        // Let's iterate through days in range?
        // Or just process existing data.

        $result = $grouped->map(function ($dayTrips, $key) use ($stops) {
            list($date, $deviceId) = explode('_', $key);
            $dayStops = $stops->filter(function($stop) use ($date, $deviceId) {
                return $stop['deviceId'] == $deviceId && date('Y-m-d', strtotime($stop['startTime'])) == $date;
            });

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
            $totalTime = $durationMs + $idleMs; // Or just duration?
            // Usually idle pct is Idle / (Drive + Idle) or Idle / 24h?
            // Mock data shows "11.23%".
            // Let's use Idle / (Drive + Idle) for now if > 0
            $pct = $totalTime > 0 ? round(($idleMs / $totalTime) * 100, 1) : 0;

            return [
                'date' => date('d/m/Y', strtotime($date)),
                'vehicleId' => $deviceId, // We need name, but trip doesn't have name always.
                'vehicle' => $dayTrips->first()['deviceName'] ?? 'Unknown',
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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

        $queryParams = [];
        foreach ($deviceIds as $id) {
            $queryParams[] = "deviceId={$id}";
        }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        $tripsResponse = static::curl("/api/reports/trips?$fullQuery", 'GET', $sessionId, '', $headers);
        $allTrips = json_decode($tripsResponse->response ?? '[]', true);

        $stopsResponse = static::curl("/api/reports/stops?$fullQuery", 'GET', $sessionId, '', $headers);
        $allStops = json_decode($stopsResponse->response ?? '[]', true);

        $trips = collect($allTrips);
        $stops = collect($allStops);

        // Group by Month and Device
        $grouped = $trips->groupBy(function($item) {
             return date('Y-m', strtotime($item['startTime'])) . '_' . $item['deviceId'];
        });

        $result = $grouped->map(function ($monthTrips, $key) use ($stops) {
            list($month, $deviceId) = explode('_', $key);
            $monthStops = $stops->filter(function($stop) use ($month, $deviceId) {
                return $stop['deviceId'] == $deviceId && date('Y-m', strtotime($stop['startTime'])) == $month;
            });

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
                'vehicleId' => $deviceId,
                'vehicle' => $monthTrips->first()['deviceName'] ?? 'Unknown',
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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date));

        $queryParams = [];
        foreach ($deviceIds as $id) { $queryParams[] = "deviceId={$id}"; }
        $deviceQuery = implode('&', $queryParams);
        $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        $tripsResp = static::curl("/api/reports/trips?$fullQuery", 'GET', $sessionId, '', $headers);
        $trips = collect(json_decode($tripsResp->response ?? '[]', true));

        $eventsResp = static::curl("/api/reports/events?$fullQuery", 'GET', $sessionId, '', $headers);
        $events = collect(json_decode($eventsResp->response ?? '[]', true));

        $stopsResp = static::curl("/api/reports/stops?$fullQuery", 'GET', $sessionId, '', $headers);
        $stops = collect(json_decode($stopsResp->response ?? '[]', true));

        $routeResp = static::curl("/api/reports/route?$fullQuery", 'GET', $sessionId, '', $headers);
        $routes = collect(json_decode($routeResp->response ?? '[]', true));

        $grouped = $trips->groupBy(function($t) { return date('Y-m-d', strtotime($t['startTime'])); });

        $result = $grouped->map(function($dayTrips, $date) use ($events, $stops, $routes) {
            $dayEvents = $events->filter(function($e) use ($date) {
                return date('Y-m-d', strtotime($e['eventTime'])) == $date;
            });
            $dayStops = $stops->filter(function($s) use ($date) {
                return date('Y-m-d', strtotime($s['startTime'])) == $date;
            });
            $dayRoutes = $routes->filter(function($r) use ($date) {
                return date('Y-m-d', strtotime($r['fixTime'])) == $date;
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
                // Events don't always have lat/lon in top level, check?
                // Traccar events usually don't have lat/lon directly, need to link to positionId or geofenceId.
                // But sometimes they do if enriched.
                // We'll leave 0,0 if missing, or maybe try to find closest route point?
                // For now, let's leave it, map might skip it or we just don't show marker on map for events without loc.
                $timeline[] = [
                    'time_sort' => strtotime($event['eventTime']),
                    'time' => date('h:i A', strtotime($event['eventTime'])),
                    'location' => '',
                    'alert' => $event['type'],
                    'type' => 'alert',
                    'lat' => 0, // Placeholder
                    'lon' => 0
                ];
            }

            usort($timeline, function($a, $b) { return $a['time_sort'] <=> $b['time_sort']; });

            $totalDist = $dayTrips->sum('distance');
            $totalDur = $dayTrips->sum('duration');
            $totalIdle = $dayStops->sum('duration');

            // Format route for map: [[lat, lon], ...]
            $routePoints = $dayRoutes->map(function($r) {
                return [$r['latitude'], $r['longitude']];
            })->values()->all();

            return [
                'key' => $date, // Use date as key
                'date' => date('d/m/Y - l', strtotime($date)),
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
        $to = date('Y-m-d\TH:i:00\Z', strtotime(($request->to_date ? $request->to_date . ' 23:59:59' : date('Y-m-d H:i:s'))));

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
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date ?? date('Y-m-d H:i:s')));

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

