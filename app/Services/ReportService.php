<?php

namespace App\Services;

use App\Helpers\Curl;
use App\Models\Devices;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    public function vehicleRanking($request)
    {
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date . ' 00:00:00'));
        $to = date('Y-m-d\TH:i:00\Z', strtotime($request->to_date . ' 23:59:59'));

        $vehicleIds = $request->vehicle_ids ?? [];

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        // Query string
        $query = 'from=' . $from . '&to=' . $to;

        if (empty($vehicleIds)) {
            $accessible = Devices::accessibleByUser($request->user())->pluck('device_id')->all();
            if (!empty($accessible)) {
                $query .= collect($accessible)->map(fn($vid) => '&deviceId=' . $vid)->implode('');
            }
        } else {
            $vehicleIds = is_string($vehicleIds) ? explode(',', $vehicleIds) : $vehicleIds;
            if (is_array($vehicleIds)) {
                $query .= collect($vehicleIds)->map(fn($vid) => '&deviceId=' . $vid)->implode('');
            }
        }

        $eventsQuery = $query;

        Log::info('VehicleRanking Service Request', ['query' => $query]);

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';

        // Parallel Requests
        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $sessionId, $query, $eventsQuery) {
            $reqHeaders = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Cookie' => $sessionId
            ];
            // Filter events to reduce payload size
            $eventsQueryFiltered = $eventsQuery . '&type=alarm&type=deviceOverspeed';

            return [
                $pool->as('summary')->withHeaders($reqHeaders)->get($baseUrl . '/api/reports/summary?' . $query),
                $pool->as('events')->withHeaders($reqHeaders)->get($baseUrl . '/api/reports/events?' . $eventsQueryFiltered),
            ];
        });

        // 1. Summary
        $summaries = [];
        if ($responses['summary']->ok()) {
            $summaries = $responses['summary']->json();
        } else {
            Log::error('VehicleRanking Service Summary Failed', ['status' => $responses['summary']->status(), 'body' => $responses['summary']->body()]);
        }

        // 2. Events
        $events = [];
        if ($responses['events']->ok()) {
            $events = $responses['events']->json();
        } else {
             Log::error('VehicleRanking Service Events Failed', ['status' => $responses['events']->status(), 'body' => $responses['events']->body()]);
        }
// dd('  summaries ', $summaries,$eventsQuery,$events);
        // Fetch local device details for Model/Type
        $deviceIds = collect($summaries)->pluck('deviceId');
        $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');

        $eventsByDevice = collect($events)->groupBy('deviceId');

        // Process Data using Map
        $rows = collect($summaries)->map(function ($sum) use ($eventsByDevice, $tcDevices) {
            $deviceId = $sum['deviceId'];
            $evs = $eventsByDevice->get($deviceId, collect([]));
            $tcDev = $tcDevices->get($deviceId);

            $distanceM = $sum['distance'] ?? 0;
            $engineHoursMs = $sum['engineHours'] ?? 0;

            // Counts
            $ha = $evs->filter(fn($e) => ($e['attributes']['alarm'] ?? null) === 'hardAcceleration')->count();
            $hb = $evs->filter(fn($e) => ($e['attributes']['alarm'] ?? null) === 'hardBraking')->count();
            $hc = $evs->filter(fn($e) => ($e['attributes']['alarm'] ?? null) === 'hardCornering')->count();
            $sv = $evs->where('type', 'deviceOverspeed')->count();

            // Calculate Points (100 - penalties)
            $points = 100 - ($ha * 5) - ($hb * 5) - ($hc * 5) - ($sv * 10);
            $model = $tcDev ? ($tcDev->model ?? $tcDev->category ?? 'N/A') : 'N/A';

            return [
                'vehicleId' => $sum['deviceName'] ?? 'Unknown',
                'typeModel' => $model,
                'distance' => round($distanceM / 1000, 2) . ' KM',
                'duration' => $this->formatDurationHms($engineHoursMs),
                'totalHA' => $ha ?: 0,
                'totalHB' => $hb ?: 0,
                'totalHC' => $hc ?: 0,
                'totalSV' => $sv ?: 0,
                'points' => $points,
                'percentage' => max(0, min(100, $points)),
            ];
        });

        // Sorting Logic based on request 'type'
        $sortBy = 'points';
        $sortDesc = true;

        if ($request->has('type')) {
            switch ($request->type) {
                case 'percentage':
                    $sortBy = 'percentage';
                    $sortDesc = true;
                    break;
                case 'points':
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
                case 'behaviour':
                    // For behaviour, we might want to see who has the most penalties?
                    // Let's stick to points DESC (Best -> Worst) as default,
                    // or if user wants "Problematic" first, we'd use ASC.
                    // Assuming standard ranking (Best First):
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
            }
        }

        $sortedRows = $sortDesc ? $rows->sortByDesc($sortBy) : $rows->sortBy($sortBy);

        // If listing all devices or sorting by points, enforce points-based rank with stable tie-breaker
        if (empty($vehicleIds) || ($sortBy === 'points' && $sortDesc === true)) {
            $sortedRows = $rows->sort(function ($a, $b) {
                $cmp = ($b['points'] <=> $a['points']);
                if ($cmp !== 0) return $cmp;
                return strcmp((string)$a['vehicleId'], (string)$b['vehicleId']);
            });
        }

        $singleSelected = false;
        if ($request->has('vehicle_ids')) {
            $ids = $request->vehicle_ids;
            if (is_array($ids)) {
                $singleSelected = count($ids) === 1;
            } elseif (is_string($ids)) {
                $singleSelected = count(array_filter(explode(',', $ids))) === 1;
            }
        }

        if ($singleSelected) {
            return $sortedRows->values()->map(function ($row) {
                $row['rank'] = 1;
                return $row;
            });
        } else {
            $allSame = $sortedRows->pluck('points')->unique()->count() === 1;
            if ($allSame) {
                return $sortedRows->values()->map(function ($row) {
                    $row['rank'] = 1;
                    return $row;
                });
            } else {
                return $sortedRows->values()->map(function ($row, $index) {
                    $row['rank'] = $index + 1;
                    return $row;
                });
            }
        }
    }

    private function formatDurationHms($ms) {
        $seconds = floor($ms / 1000);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%dh %dm %ds', $h, $m, $s);
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
        $aggregated = collect($trips)->reduce(function ($acc, $trip) use ($months) {
            if (empty($trip['startTime'])) return $acc;

            $monthKey = $months[date('n', strtotime($trip['startTime'])) - 1];

            // Distance (m → km)
            if (isset($trip['distance'])) {
                $acc['distance'][$monthKey] += round($trip['distance'] / 1000, 1);
            }

            // Fuel spent
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
            if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                $acc['fuel'][$monthKey] += round($startFuel - $endFuel, 1);
            }

            // Average speed (knots → km/h)
            // Filter out unrealistic speeds (> 162 knots approx 300 km/h)
            if (isset($trip['averageSpeed']) && $trip['averageSpeed'] <= 162) {
                $acc['speed'][$monthKey]['total'] += $trip['averageSpeed'] * 1.852;
                $acc['speed'][$monthKey]['count'] += 1;
            }

            return $acc;
        }, [
            'distance' => $monthlyDistance,
            'fuel' => $monthlyFuel,
            'speed' => $monthlySpeedData
        ]);

        $monthlyDistance = $aggregated['distance'];
        $monthlyFuel = $aggregated['fuel'];
        $monthlySpeedData = $aggregated['speed'];

        // Compute monthly avg speed
        $monthlyAvgSpeed = collect($months)->mapWithKeys(function ($m) use ($monthlySpeedData) {
            $total = $monthlySpeedData[$m]['total'];
            $cnt = $monthlySpeedData[$m]['count'] ?: 1;
            return [$m => round($total / $cnt, 1)];
        })->all();

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
        // We will chunk requests to avoid URL length limits and improve parallelism
        $commonQuery = "from={$from}&to={$to}";

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

        // Chunk size of 20 devices per request group
        $chunks = array_chunk($deviceIds, 20);

        try {
            // Execute requests in parallel using HTTP Pool for all chunks
            $responses = Http::pool(function (Pool $pool) use ($chunks, $baseUrl, $headers, $commonQuery, $eventTypes) {
                $poolRequests = [];
                foreach ($chunks as $index => $chunkIds) {
                    $deviceQuery = collect($chunkIds)->map(function($id) {
                        return "deviceId={$id}";
                    })->implode('&');
                    $fullQuery = "{$deviceQuery}&{$commonQuery}";

                    $poolRequests[] = $pool->as("summary_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$fullQuery}");
                    $poolRequests[] = $pool->as("stops_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/stops?{$fullQuery}");
                    $poolRequests[] = $pool->as("events_{$index}")->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}&type=" . urlencode($eventTypes));
                }
                return $poolRequests;
            });
        } catch (\Exception $e) {
            Log::error('ReportService: Failed to fetch fleet summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }

        // Aggregate results
        $allSummary = [];
        $allStops = [];
        $allEvents = [];

        foreach ($chunks as $index => $chunkIds) {
            $summaryRes = $responses["summary_{$index}"] ?? null;
            $stopsRes = $responses["stops_{$index}"] ?? null;
            $eventsRes = $responses["events_{$index}"] ?? null;

            if ($summaryRes instanceof \Illuminate\Http\Client\Response) {
                if ($summaryRes->ok()) {
                    $allSummary = array_merge($allSummary, $summaryRes->json());
                } else {
                    Log::error("ReportService API Error [summary_{$index}]", ['status' => $summaryRes->status(), 'body' => $summaryRes->body()]);
                }
            }

            if ($stopsRes instanceof \Illuminate\Http\Client\Response && $stopsRes->ok()) {
                $allStops = array_merge($allStops, $stopsRes->json());
            }

            if ($eventsRes instanceof \Illuminate\Http\Client\Response && $eventsRes->ok()) {
                $allEvents = array_merge($allEvents, $eventsRes->json());
            }
        }

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
            $refillTotal = $refills->sum(function ($refill) {
                return $refill['attributes']['amount'] ?? 0;
            });
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

        $deviceQuery = collect($deviceIds)->map(function($id) {
            return "deviceId={$id}";
        })->implode('&');
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
        $totalFuel = collect($allTrips)->sum(function ($trip) {
            $startFuel = data_get($trip, 'start.attributes.fuel', 0);
            $endFuel = data_get($trip, 'end.attributes.fuel', 0);
             if ($startFuel && $endFuel && $startFuel >= $endFuel) {
                return $startFuel - $endFuel;
            }
            return data_get($trip, 'spentFuel', 0);
        });

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

        $deviceQuery = collect($deviceIds)->map(function($id) {
            return "deviceId={$id}";
        })->implode('&');
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

        $deviceQuery = collect($deviceIds)->map(function($id) {
            return "deviceId={$id}";
        })->implode('&');
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

        $deviceQuery = collect($deviceIds)->map(function($id) {
            return "deviceId={$id}";
        })->implode('&');
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
                return $dayTrips->contains(function($trip) use ($rTime) {
                    $start = strtotime($trip['startTime']);
                    $end = strtotime($trip['endTime']);
                    return $rTime >= $start && $rTime <= $end;
                });
            });

            // Build timeline
            $tripTimeline = $dayTrips->flatMap(function($trip) use ($dayEvents) {
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

                return [
                    [
                        'time_sort' => $tripStart,
                        'time' => date('h:i A', $tripStart),
                        'location' => $trip['startAddress'] ?? '',
                        'dist' => round(($trip['distance'] ?? 0)/1000, 2) . 'KM',
                        'dur' => $durStr,
                        'alert' => $alertBadge,
                        'type' => 'start',
                        'lat' => $trip['startLat'] ?? 0,
                        'lon' => $trip['startLon'] ?? 0
                    ],
                    [
                        'time_sort' => $tripEnd,
                        'time' => date('h:i A', $tripEnd),
                        'location' => $trip['endAddress'] ?? '',
                        'type' => 'end',
                        'lat' => $trip['endLat'] ?? 0,
                        'lon' => $trip['endLon'] ?? 0
                    ]
                ];
            });


            // Events (Hidden in list, visible on map)
            $eventTimeline = $dayEvents->filter(function($event) {
                return !in_array($event['type'], ['deviceOnline', 'deviceOffline']);
            })->map(function($event) use ($dayRoutes) {
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

                return [
                    'time_sort' => $eventTs,
                    'time' => date('h:i A', $eventTs),
                    'location' => $addr,
                    'alert' => $friendlyName,
                    'type' => 'alert',
                    'lat' => $lat,
                    'lon' => $lon,
                    'hidden' => true
                ];
            });

            // Stops
            $stopTimeline = $dayStops->map(function($stop) {
                $stopTs = strtotime($stop['startTime']);
                return [
                    'time_sort' => $stopTs,
                    'time' => date('h:i A', $stopTs),
                    'location' => $stop['address'] ?? '',
                    'type' => 'stop',
                    'lat' => $stop['latitude'] ?? 0,
                    'lon' => $stop['longitude'] ?? 0
                ];
            });

            // Merge and Sort
            $timeline = $tripTimeline->merge($eventTimeline)
                ->merge($stopTimeline)
                ->sortBy('time_sort')
                ->values()
                ->all();

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
        $formattedEvents = collect($events)->map(function ($event) use ($deviceName) {
            return [
                'eventTime' => $event->eventTime ?? $event->serverTime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $event->type ?? 'unknown',
                'description' => $this->formatEventDescription($event),
                'attributes' => $event->attributes ?? null
            ];
        })->all();

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
        $formattedEvents = collect($events)->map(function ($event) use ($deviceName) {
            return [
                'eventTime' => $event->eventTime ?? $event->serverTime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $event->type ?? 'unknown',
                'description' => $this->formatEventDescription($event),
                'attributes' => $event->attributes ?? null
            ];
        })->all();

        return $formattedEvents;
    }
    public function fetchAssetActivity($request, $deviceIds)
    {
        // Increase memory limit for this heavy report
        // Set to 1024M to handle large JSON responses from Traccar
        ini_set('memory_limit', '1024M');
        set_time_limit(300); // 5 minutes timeout

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        if (empty($deviceIds)) return [];

        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

            // Get limit from request, default to 100
            $limitParam = (int) $request->input('limit', 100);

            $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) return [];

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json'
        ];

        // Sequential processing with smaller chunks to save memory
        // Process 1 device at a time to minimize peak memory usage
        $chunks = collect(array_chunk($deviceIds, 1));

        $singleDeviceName = 'Unknown';
        $limitReached = false;
        $currentRowCount = 0;

        // Safety limits
        $maxRows = 20000;
        $memoryThreshold = 0.9; // Stop if 90% of memory limit is reached
        $allRows = $chunks->map(function ($chunkIds) use ($request, $from, $to, $baseUrl, $headers, &$singleDeviceName, &$limitReached, &$currentRowCount, $maxRows, $memoryThreshold, $limitParam) {

            if ($limitReached) return [];

            // Check memory usage
            $limit = $this->getMemoryLimitBytes();
            if ($limit > 0 && memory_get_usage(true) > ($limit * $memoryThreshold)) {
                Log::warning('AssetActivity: Memory limit approaching, stopping processing early.');
                $limitReached = true;
                return [];
            }

            // Check row count
            if ($currentRowCount >= $maxRows) {
                 Log::warning('AssetActivity: Row limit reached, stopping processing early.');
                 $limitReached = true;
                 return [];
            }

            $chunkRows = [];

            try {
                $deviceParams = [];
                foreach ($chunkIds as $id) {
                    $deviceParams[] = "deviceId={$id}";
                }
                $deviceQuery = implode('&', $deviceParams);
                $queryString = "{$deviceQuery}&from={$from}&to={$to}&limit={$limitParam}";

                // Fetch data for this chunk
                $responses = Http::pool(function (Pool $pool) use ($baseUrl, $headers, $queryString) {
                    return [
                        $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$queryString}"),
                        $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$queryString}"),
                        $pool->as('summary')->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$queryString}"),
                    ];
                });

                $routeData = ($responses['route']->ok()) ? $responses['route']->json() : [];
                $eventsData = ($responses['events']->ok()) ? $responses['events']->json() : [];
                $summaryData = ($responses['summary']->ok()) ? $responses['summary']->json() : [];

                // Map device names
                $chunkDeviceMap = [];
                if (is_array($summaryData)) {
                    foreach ($summaryData as $s) {
                        if (isset($s['deviceId'])) {
                            $name = $s['deviceName'] ?? 'Device ' . $s['deviceId'];
                            $chunkDeviceMap[$s['deviceId']] = $name;
                            if ($singleDeviceName === 'Unknown') $singleDeviceName = $name;
                        }
                    }
                }

                // Process Route
                if (is_array($routeData)) {
                    foreach ($routeData as $pos) {
                        $dId = $pos['deviceId'] ?? 0;
                        $time = $pos['fixTime'] ?? $pos['deviceTime'];
                        $dt = strtotime($time);
                        $attrs = $pos['attributes'] ?? [];

                        $lat = isset($pos['latitude']) ? round($pos['latitude'], 5) : 0;
                        $lon = isset($pos['longitude']) ? round($pos['longitude'], 5) : 0;
                        $power = $attrs['power'] ?? $attrs['battery'] ?? null;
                        if ($power) $power = round($power, 1) . 'V';
                        $fuel = $attrs['fuel'] ?? null;
                        if ($fuel) $fuel = round($fuel, 1) . ' L';

                        // GSM Signal Formatting
                        $rssi = $attrs['rssi'] ?? null;
                        $gsm = 'No Signal';
                        if ($rssi !== null) {
                            if ($rssi >= -70) $gsm = 'Excellent';
                            elseif ($rssi >= -85) $gsm = 'Good';
                            elseif ($rssi >= -100) $gsm = 'Fair';
                            elseif ($rssi >= -110) $gsm = 'Poor';
                            else $gsm = 'Very Poor';
                        }

                        // GPS Signal Formatting
                        $sat = $attrs['sat'] ?? 0;
                        $gps = 'No Signal';
                        if ($sat > 0) {
                            if ($sat >= 10) $gps = 'Excellent';
                            elseif ($sat >= 7) $gps = 'Good';
                            elseif ($sat >= 4) $gps = 'Fair';
                            else $gps = 'Poor';
                        }

                        // Ignition Formatting
                        $ign = $attrs['ignition'] ?? false;
                        $ignition = $ign ? 'ON' : 'OFF';

                        $chunkRows[] = [
                            'key' => 0, // Will reindex later
                            'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                            'groupDate' => date('d-m-Y l', $dt),
                            'date' => date('d-m-Y', $dt),
                            'time' => date('H:i:s', $dt),
                            'status' => 'Position Log',
                            'lat' => $lat,
                            'lon' => $lon,
                            'location' => $pos['address'] ?? '',
                            'direction' => $pos['course'] ?? 0,
                            'speed' => round(($pos['speed'] ?? 0) * 1.852, 0) . ' km/h',
                            'gsm' => $gsm,
                            'gps' => $gps,
                            'power' => $power,
                            'ignition' => $ignition,
                            'fuel' => $fuel,
                            'isEvent' => false,
                            'rawType' => 'position',
                            'epoch' => $dt
                        ];
                    }
                }

                // Process Events
                if (is_array($eventsData)) {
                    foreach ($eventsData as $evt) {
                        $dId = $evt['deviceId'] ?? 0;
                        $time = $evt['eventTime'] ?? $evt['serverTime'];
                        $dt = strtotime($time);
                        $attrs = $evt['attributes'] ?? [];

                        $chunkRows[] = [
                            'key' => 0,
                            'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                            'groupDate' => date('d-m-Y l', $dt),
                            'date' => date('d-m-Y', $dt),
                            'time' => date('H:i:s', $dt),
                            'status' => $this->formatEventDescription($evt),
                            'lat' => 0,
                            'lon' => 0,
                            'location' => '',
                            'direction' => 0,
                            'speed' => '0 km/h',
                            'gsm' => null,
                            'gps' => null,
                            'power' => null,
                            'ignition' => null,
                            'fuel' => null,
                            'isEvent' => true,
                            'rawType' => $evt['type'] ?? '',
                            'epoch' => $dt
                        ];
                    }
                }

                unset($routeData, $eventsData, $summaryData, $responses);

                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            } catch (\Throwable $e) {
                Log::error('fetchAssetActivity chunk exception', ['error' => $e->getMessage()]);
            }

            $currentRowCount += count($chunkRows);
            return $chunkRows;

        })->collapse()->values()->all();

        // Sort by Time
        usort($allRows, function($a, $b) {
            return $a['epoch'] <=> $b['epoch'];
        });

        // Limit to last N records
        if ($limitParam > 0 && count($allRows) > $limitParam) {
            $allRows = array_slice($allRows, -$limitParam);
        }

        // Re-index keys
        foreach ($allRows as $index => &$row) {
            $row['key'] = $index;
        }
        unset($row);

        // Header Info
        $lastRow = end($allRows);
        $lastTime = $lastRow ? date('Y-m-d H:i:s', $lastRow['epoch']) : 'N/A';

        // Find last position
        $lastAddress = '';
        for ($i = count($allRows) - 1; $i >= 0; $i--) {
            if ($allRows[$i]['rawType'] === 'position') {
                $lastAddress = $allRows[$i]['location'];
                if (!$lastAddress) {
                    $lastAddress = $allRows[$i]['lat'] . ', ' . $allRows[$i]['lon'];
                }
                break;
            }
        }

        $vehicleLabel = count($deviceIds) > 1 ? 'Multiple Vehicles (' . count($deviceIds) . ')' : $singleDeviceName;
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
            'rows' => $allRows
        ];
    }

    public function fetchVehicleActivity($request, $deviceIds)
    {
        // Increase memory limit for this heavy report
        // Set to 1024M to handle large JSON responses from Traccar
        ini_set('memory_limit', '1024M');
        set_time_limit(300); // 5 minutes timeout

        $sessionId = $request->user()->traccarSession ?? session('cookie');

        if (empty($deviceIds)) return [];

        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

            // Get limit from request, default to 100
            $limitParam = (int) $request->input('limit', 100);

            $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) return [];

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json'
        ];

        // Sequential processing with smaller chunks to save memory
        // Process 1 device at a time to minimize peak memory usage
        $chunks = collect(array_chunk($deviceIds, 1));

        $singleDeviceName = 'Unknown';
        $limitReached = false;
        $currentRowCount = 0;

        $allRows = $chunks->map(function ($chunkIds) use ($headers, $baseUrl, $from, $to, &$singleDeviceName, &$limitReached, &$currentRowCount, $limitParam) {

            if ($limitReached) return [];

            // If we have collected enough rows across chunks (approx check)
            if ($limitParam > 0 && $currentRowCount >= $limitParam * 2) {
                 $limitReached = true;
                 return [];
            }

            $deviceQuery = collect($chunkIds)->map(function($id) {
                return "deviceId={$id}";
            })->implode('&');
            $fullQuery = "{$deviceQuery}&from={$from}&to={$to}";

            try {
                $responses = Http::pool(fn (Pool $pool) => [
                    $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$fullQuery}"),
                    $pool->as('events')->withHeaders($headers)->get("{$baseUrl}/api/reports/events?{$fullQuery}"),
                    $pool->as('summary')->withHeaders($headers)->get("{$baseUrl}/api/reports/summary?{$fullQuery}"),
                ]);
            } catch (\Exception $e) {
                Log::error('fetchVehicleActivity chunk exception', ['error' => $e->getMessage()]);
                return [];
            }

            $routeData = ($responses['route']->ok()) ? $responses['route']->json() : [];
            $eventsData = ($responses['events']->ok()) ? $responses['events']->json() : [];
            $summaryData = ($responses['summary']->ok()) ? $responses['summary']->json() : [];

            $chunkRows = [];
            $chunkDeviceMap = [];

            // Process Summary to get device names
            if (is_array($summaryData)) {
                foreach ($summaryData as $s) {
                    if (isset($s['deviceId'])) {
                        $name = $s['deviceName'] ?? 'Device ' . $s['deviceId'];
                        $chunkDeviceMap[$s['deviceId']] = $name;
                        if ($singleDeviceName === 'Unknown') $singleDeviceName = $name;
                    }
                }
            }

            // Process Route
            if (is_array($routeData)) {
                foreach ($routeData as $pos) {
                    $dId = $pos['deviceId'] ?? 0;
                    $time = $pos['fixTime'] ?? $pos['deviceTime'];
                    $dt = strtotime($time);
                    $attrs = $pos['attributes'] ?? [];

                    $lat = isset($pos['latitude']) ? round($pos['latitude'], 5) : 0;
                    $lon = isset($pos['longitude']) ? round($pos['longitude'], 5) : 0;
                    $power = $attrs['power'] ?? $attrs['battery'] ?? null;
                    if ($power) $power = round($power, 1) . 'V';

                    $fuel = $this->formatFuel($attrs);

                    // GSM Signal Formatting
                    $rssi = $attrs['rssi'] ?? null;
                    $gsm = 'No Signal';
                    if ($rssi !== null) {
                        if ($rssi >= -70) $gsm = 'Excellent';
                        elseif ($rssi >= -85) $gsm = 'Good';
                        elseif ($rssi >= -100) $gsm = 'Fair';
                        elseif ($rssi >= -110) $gsm = 'Poor';
                        else $gsm = 'Very Poor';
                    }

                    // GPS Signal Formatting
                    $sat = $attrs['sat'] ?? 0;
                    $gps = 'No Signal';
                    if ($sat > 0) {
                        if ($sat >= 10) $gps = 'Excellent';
                        elseif ($sat >= 7) $gps = 'Good';
                        elseif ($sat >= 4) $gps = 'Fair';
                        else $gps = 'Poor';
                    }

                    // Ignition Formatting
                    $ign = $attrs['ignition'] ?? false;
                    $ignition = $ign ? 'ON' : 'OFF';

                    // Determine Status (Moving, Idling, Stopped)
                    $speedKnots = $pos['speed'] ?? 0;
                    $speedKph = round($speedKnots * 1.852, 0);

                    $status = 'Stopped';
                    if ($speedKph > 0) {
                        $status = 'Moving';
                    } elseif ($ign) {
                        $status = 'Idling';
                    }

                    $chunkRows[] = [
                        'key' => 0, // Will reindex later
                        'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                        'groupDate' => date('d-m-Y l', $dt),
                        'date' => date('d-m-Y', $dt),
                        'time' => date('H:i:s', $dt),
                        'status' => $status,
                        'lat' => $lat,
                        'lon' => $lon,
                        'location' => ($pos['address'] ?? null) ?: (($lat && $lon) ? ($lat . ', ' . $lon) : ''),
                        'direction' => $pos['course'] ?? 0,
                        'speed' => $speedKph . ' km/h',
                        'gsm' => $gsm,
                        'gps' => $gps,
                        'power' => $power,
                        'ignition' => $ignition,
                        'fuel' => $fuel,
                        'isEvent' => false,
                        'rawType' => 'position',
                        'epoch' => $dt
                    ];
                }
            }

            // Process Events
            if (is_array($eventsData)) {
                foreach ($eventsData as $evt) {
                    $dId = $evt['deviceId'] ?? 0;
                    $time = $evt['eventTime'] ?? $evt['serverTime'];
                    $dt = strtotime($time);
                    $attrs = $evt['attributes'] ?? [];

                    $chunkRows[] = [
                        'key' => 0,
                        'vehicle' => $chunkDeviceMap[$dId] ?? 'Unknown',
                        'groupDate' => date('d-m-Y l', $dt),
                        'date' => date('d-m-Y', $dt),
                        'time' => date('H:i:s', $dt),
                        'status' => $this->formatEventDescription($evt),
                        'lat' => 0,
                        'lon' => 0,
                        'location' => '',
                        'direction' => 0,
                        'speed' => '0 km/h',
                        'gsm' => null,
                        'gps' => null,
                        'power' => null,
                        'ignition' => null,
                        'fuel' => null,
                        'isEvent' => true,
                        'rawType' => $evt['type'] ?? '',
                        'epoch' => $dt
                    ];
                }
            }

            unset($routeData, $eventsData, $summaryData, $responses);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $currentRowCount += count($chunkRows);
            return $chunkRows;

        })->collapse()->values()->all();

        // Sort by Time
        usort($allRows, function($a, $b) {
            return $a['epoch'] <=> $b['epoch'];
        });

        // Limit to last N records
        if ($limitParam > 0 && count($allRows) > $limitParam) {
            $allRows = array_slice($allRows, -$limitParam);
        }

        // Re-index keys
        foreach ($allRows as $index => &$row) {
            $row['key'] = $index;
        }
        unset($row);

        // Header Info
        $lastRow = end($allRows);
        $lastTime = $lastRow ? date('Y-m-d H:i:s', $lastRow['epoch']) : 'N/A';

        // Find last position
        $lastAddress = '';
        for ($i = count($allRows) - 1; $i >= 0; $i--) {
            if ($allRows[$i]['rawType'] === 'position') {
                $lastAddress = $allRows[$i]['location'];
                if (!$lastAddress) {
                    $lastAddress = $allRows[$i]['lat'] . ', ' . $allRows[$i]['lon'];
                }
                break;
            }
        }

        $vehicleLabel = count($deviceIds) > 1 ? 'Multiple Vehicles (' . count($deviceIds) . ')' : $singleDeviceName;
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
            'rows' => $allRows
        ];
    }

    private function getMemoryLimitBytes()
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return -1;
        }
        $val = trim($limit);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    public function fetchIdlingReport($request, $deviceIds)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

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

        // Process sequentially to save memory
        $chunks = collect(array_chunk($deviceIds, 5)); // Process 5 devices at a time
        $allIdlingEvents = [];

        foreach ($chunks as $chunkIds) {
            $deviceQuery = collect($chunkIds)->map(function($id) {
                return "deviceId={$id}";
            })->implode('&');
            $queryString = "{$deviceQuery}&from={$from}&to={$to}";

            try {
                $responses = Http::pool(fn (Pool $pool) => [
                    $pool->as('route')->withHeaders($headers)->get("{$baseUrl}/api/reports/route?{$queryString}"),
                    $pool->as('devices')->withHeaders($headers)->get("{$baseUrl}/api/devices?{$deviceQuery}"), // To get names
                ]);
            } catch (\Exception $e) {
                Log::error('fetchIdlingReport chunk exception', ['error' => $e->getMessage()]);
                continue;
            }

            $routeData = ($responses['route']->ok()) ? $responses['route']->json() : [];
            $devicesData = ($responses['devices']->ok()) ? $responses['devices']->json() : [];

            $deviceMap = [];
            foreach ($devicesData as $d) {
                $deviceMap[$d['id']] = $d['name'];
            }

            // Group route data by device
            $groupedRoute = [];
            foreach ($routeData as $pos) {
                $dId = $pos['deviceId'];
                if (!isset($groupedRoute[$dId])) $groupedRoute[$dId] = [];
                $groupedRoute[$dId][] = $pos;
            }

            // Analyze for idling
            foreach ($groupedRoute as $dId => $positions) {
                // Sort by time just in case
                usort($positions, function($a, $b) {
                    return strtotime($a['fixTime']) - strtotime($b['fixTime']);
                });

                $currentStart = null;
                $deviceName = $deviceMap[$dId] ?? 'Unknown';

                foreach ($positions as $index => $pos) {
                    $speedKnots = $pos['speed'] ?? 0;
                    $speedKph = $speedKnots * 1.852;
                    $attrs = $pos['attributes'] ?? [];
                    $ignition = $attrs['ignition'] ?? false;

                    // Idling condition: Ignition ON AND Speed approx 0 (e.g., < 2 km/h)
                    $isIdling = $ignition && ($speedKph < 2);

                    if ($isIdling) {
                        if ($currentStart === null) {
                            $currentStart = $pos;
                        }
                    } else {
                        if ($currentStart !== null) {
                            // End of idling session
                            $startDt = strtotime($currentStart['fixTime']);
                            $endDt = strtotime($positions[$index-1]['fixTime']); // Use previous point as end
                            $duration = $endDt - $startDt;

                            if ($duration > 0) {
                                $allIdlingEvents[] = [
                                    'vehicle' => $deviceName,
                                    'deviceId' => $dId,
                                    'date' => date('d-m-Y', $startDt),
                                    'startTime' => date('H:i:s', $startDt),
                                    'endTime' => date('H:i:s', $endDt),
                                    'durationSeconds' => $duration,
                                    'durationFormatted' => $this->formatDuration($duration),
                                    'location' => $currentStart['address'] ?? ($currentStart['latitude'] . ', ' . $currentStart['longitude']),
                                    'lat' => $currentStart['latitude'],
                                    'lon' => $currentStart['longitude'],
                                    'startEpoch' => $startDt
                                ];
                            }
                            $currentStart = null;
                        }
                    }
                }

                // Check if still idling at the end of the list
                if ($currentStart !== null) {
                    $lastPos = end($positions);
                    $startDt = strtotime($currentStart['fixTime']);
                    $endDt = strtotime($lastPos['fixTime']);
                    $duration = $endDt - $startDt;

                    if ($duration > 0) {
                        $allIdlingEvents[] = [
                            'vehicle' => $deviceName,
                            'deviceId' => $dId,
                            'date' => date('d-m-Y', $startDt),
                            'startTime' => date('H:i:s', $startDt),
                            'endTime' => date('H:i:s', $endDt),
                            'durationSeconds' => $duration,
                            'durationFormatted' => $this->formatDuration($duration),
                            'location' => $currentStart['address'] ?? ($currentStart['latitude'] . ', ' . $currentStart['longitude']),
                            'lat' => $currentStart['latitude'],
                            'lon' => $currentStart['longitude'],
                            'startEpoch' => $startDt
                        ];
                    }
                }
            }

            // Cleanup
            unset($routeData, $groupedRoute, $responses);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }

        // Sort by start time
        usort($allIdlingEvents, function($a, $b) {
            return $b['startEpoch'] <=> $a['startEpoch'];
        });

        return $allIdlingEvents;
    }

    private function formatDuration($seconds)
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        $str = '';
        if ($h > 0) $str .= $h . 'h ';
        if ($m > 0) $str .= $m . 'm ';
        $str .= $s . 's';
        return trim($str);
    }

    private function formatFuel($attrs)
    {
        if (empty($attrs)) return null;

        $lower = array_change_key_case($attrs, CASE_LOWER);

        $getVal = function($keys) use ($attrs, $lower) {
            foreach ((array)$keys as $k) {
                if (isset($attrs[$k])) return $attrs[$k];
                $lk = strtolower($k);
                if (isset($lower[$lk])) return $lower[$lk];
            }
            return null;
        };

        $num = function($v) {
            return (is_numeric($v)) ? (float)$v : null;
        };

        // 1. Resolve Percent
        $percent = null;
        $percentCandidates = ['CAN_FuelPercentage_89', 'fuelPercent', 'fuelLevel', 'fuel_percent', 'io89', 'io48'];
        foreach ($percentCandidates as $key) {
            $val = $num($getVal($key));
            if ($val !== null && $val > -1) {
                $percent = max(0, min(100, round($val)));
                break;
            }
        }

        // 2. Resolve Liters
        $liters = null;
        $litersCandidates = ['CAN_FuelLeter_84', 'OBD_FuelLeter_48', 'fuelLiter', 'fuelLiters', 'fuel', 'io84'];
        foreach ($litersCandidates as $key) {
            $val = $num($getVal($key));
            if ($val !== null && $val > -1) {
                $liters = round($val * 10) / 10;
                break;
            }
        }

        // 3. Raw Analog Fallback
        $raw = null;
        if ($percent === null && $liters === null) {
            $rawCandidates = [
                'io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243',
                'fuelRaw', 'analog1', 'analog2', 'analog3', 'adc1', 'adc2', 'adc3'
            ];
            foreach ($rawCandidates as $key) {
                $val = $num($getVal($key));
                if ($val !== null && $val > -1) {
                    $raw = $val;
                    break;
                }
            }

            // Generic 'fuel' scan
            if ($raw === null) {
                foreach ($lower as $k => $v) {
                    if (strpos($k, 'fuel') !== false) {
                        $n = $num($v);
                        if ($n !== null && $n > -1) {
                            $raw = $n;
                            break;
                        }
                    }
                }
            }
        }

        // Return formatted string
        if ($liters !== null) return $liters . ' L';
        if ($percent !== null) return $percent . '%';
        if ($raw !== null) {
            if ($raw >= 0 && $raw <= 100) return round($raw) . '%';
            return (string)$raw;
        }

        return null;
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

    public function fetchUtilisationReport($request, $deviceId)
    {
        Log::info("fetchUtilisationReport started for device {$deviceId}");
        $type = $request->type ?? 'Movement';
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $from = date('Y-m-d\TH:i:00\Z', strtotime($request->from_date));
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = date('Y-m-d\TH:i:00\Z', strtotime($toStr));

        $baseUrl = is_string(Config::get('constants.Constants.host')) ? rtrim(Config::get('constants.Constants.host'), '/') : '';
        if (empty($baseUrl)) {

             Log::error('ReportService: Traccar Host URL is not configured.');
             $vehicleRec = Devices::accessibleByUser($request->user())->with('tcDevice')->where('device_id', $deviceId)->first();

             $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;
             $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;
             $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
             if (is_string($attributes)) $attributes = json_decode($attributes, true);

             $vehicleName = $tcDevice->name ?? 'Unknown';
             $vehicleNo = $attributes['vehicleNo'] ?? null;

             if ($vehicleNo) {
                 $vehicleIdDisplay = "{$vehicleNo} - {$vehicleName}";
             } else {
                 $vehicleIdDisplay = $vehicleName;
             }

             $totalDays = max(1, round((strtotime($toStr) - strtotime($request->from_date)) / (60 * 60 * 24)) + 1);
             return [
                 'summary' => [
                     'vehicleIdDisplay' => $vehicleIdDisplay,
                     'deviceId' => $uniqueId,
                     'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                     'totalDays' => $totalDays
                 ],
                 'rows' => []
             ];
        }

        $headers = [
            'Cookie' => $sessionId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        $fromIso = $request->from_date . 'T00:00:00Z';
        $toIso = (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $request->to_date) ? $request->to_date . ' 23:59:59' : $request->to_date);
        $toIso = date('Y-m-d\\TH:i:00\\Z', strtotime($toIso));

        $tripResp = null;
        $stopResp = null;
        $eventResp = null;

        $allTrips = [];
        $allStops = [];
        $allEvents = [];

        if ($type !== 'Engine Hours') {
            $tripResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/trips", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso
            ]);
            $stopResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/stops", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso
            ]);
            $allTrips = is_array($tripResp?->json()) ? $tripResp->json() : [];
            $allStops = is_array($stopResp?->json()) ? $stopResp->json() : [];
        } else {
            $eventResp = Http::timeout(300)->withHeaders($headers)->get("{$baseUrl}/api/reports/events", [
                'deviceId' => $deviceId,
                'from' => $fromIso,
                'to' => $toIso,
                'type' => ['ignitionOn', 'ignitionOff']
            ]);
            $allEvents = is_array($eventResp?->json()) ? $eventResp->json() : [];
        }

        Log::info("Fetched Total Data", [
            'trips' => is_array($allTrips) ? count($allTrips) : 0,
            'stops' => is_array($allStops) ? count($allStops) : 0,
            'events' => is_array($allEvents) ? count($allEvents) : 0
        ]);

        $foundVehicleName = null;
        if (!$foundVehicleName && count($allTrips) > 0) {
            $first = $allTrips[0];
            if (isset($first['deviceName'])) {
                $foundVehicleName = $first['deviceName'];
            }
        }
        unset($responses);

        $vehicleRec = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
        $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;

        $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;

        $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }

        $vehicleName = $tcDevice->name ?? 'Unknown';
        if ($vehicleName === 'Unknown' && $foundVehicleName) {
            $vehicleName = $foundVehicleName;
        }

         // Try to find vehicle no in attributes
         $vehicleNo = $attributes['vehicleNo'] ?? null;

         if ($vehicleNo) {
             $vehicleIdDisplay = "{$vehicleNo} - {$vehicleName}";
         } else {
             $vehicleIdDisplay = $vehicleName;
         }

         $totalDays = max(1, round((strtotime($toStr) - strtotime($request->from_date)) / (60 * 60 * 24)) + 1);

         return [
             'summary' => [
                 'vehicleIdDisplay' => $vehicleIdDisplay,
                 'deviceId' => $uniqueId,
                 'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                 'totalDays' => $totalDays
             ],
             'raw' => [
                 'trips' => $allTrips,
                 'stops' => $allStops,
                 'events' => $allEvents
             ],
             'type' => $type
         ];
    }

    public function fetchUtilisationReportDb($request, $deviceId)
    {
        $type = $request->type ?? 'Movement';
        $fromStr = $request->from_date;
        $toStr = $request->to_date;

        // Ensure proper timestamps
        $fromTs = strtotime($fromStr . ' 00:00:00');
        $toTs = strtotime($toStr . ' 23:59:59');
        $fromIso = date('Y-m-d H:i:s', $fromTs);
        $toIso = date('Y-m-d H:i:s', $toTs);

        // Fetch Device Info
        $vehicleRec = Devices::with('tcDevice')->whereHas('tcDevice', function ($q) use ($deviceId) {
            $q->where('id', (int)$deviceId);
        })->first();
        if (!$vehicleRec) {
            $vehicleRec = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
        }
        $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;
        $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;
        $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }
        $vehicleName = $tcDevice->name ?? 'Unknown';
        $vehicleNo = $attributes['vehicleNo'] ?? null;
        $vehicleIdDisplay = $vehicleNo ? "{$vehicleNo} - {$vehicleName}" : $vehicleName;
        $totalDays = max(1, round(($toTs - $fromTs) / (60 * 60 * 24)));

        $rows = [];
        $startDate = new \DateTime($fromStr);
        $endDate = new \DateTime($toStr);
        $endDate->modify('+1 day');
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);

        if ($type !== 'Engine Hours') {
            // Movement Report (Positions based)
            $positions = DB::connection('pgsql')
                ->table('tc_positions')
                ->select('fixtime', 'latitude', 'longitude', 'speed')
                ->where('deviceid', (int)$deviceId)
                ->whereBetween('fixtime', [$fromIso, $toIso])
                ->orderBy('fixtime')
                ->limit(500000) // Increased limit for larger ranges
                ->get()
                ->groupBy(function($item) {
                    return substr($item->fixtime, 0, 10);
                });

            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');
                $dayStart = strtotime($dateStr . ' 00:00:00') * 1000;
                $dayEnd = strtotime($dateStr . ' 23:59:59') * 1000;

                $dayPositions = isset($positions[$dateStr]) ? $positions[$dateStr] : [];

                $tripMs = 0;
                $distance = 0;
                $totalMs = 0;
                $hourlyMoveMs = array_fill(0, 24, 0);

                $count = count($dayPositions);
                if ($count > 1) {
                    $firstT = strtotime($dayPositions[0]->fixtime) * 1000;
                    $lastT = strtotime($dayPositions[$count - 1]->fixtime) * 1000;
                    $totalMs = max(0, $lastT - $firstT);

                    for ($i = 1; $i < $count; $i++) {
                        $prev = $dayPositions[$i - 1];
                        $cur = $dayPositions[$i];

                        $pt = strtotime($prev->fixtime) * 1000;
                        $ct = strtotime($cur->fixtime) * 1000;
                        $dtMs = max(0, $ct - $pt);

                        $speed = $prev->speed ?? 0;
                        $kmh = $speed * 1.852; // knots to kmh

                        // Distance
                        if ($prev->latitude && $prev->longitude && $cur->latitude && $cur->longitude) {
                             $distance += $this->haversine($prev->latitude, $prev->longitude, $cur->latitude, $cur->longitude) * 1000;
                        }

                        // Movement logic
                        if ($kmh > 5) {
                            $tripMs += $dtMs;
                            $midTime = $pt + ($dtMs / 2);
                            $h = (int)date('H', $midTime / 1000);
                            if (isset($hourlyMoveMs[$h])) {
                                $hourlyMoveMs[$h] += $dtMs;
                            }
                        }
                    }
                }

                $idleMs = max(0, $totalMs - $tripMs);
                $usagePct = $totalMs > 0 ? round(($tripMs / $totalMs) * 100) : 0;

                // Blue boxes logic
                $hours = array_fill(0, 24, false);
                $totalMoveHours = ceil($tripMs / 3600000);
                if ($totalMoveHours > 0) {
                    arsort($hourlyMoveMs);
                    $topHours = array_keys(array_slice($hourlyMoveMs, 0, $totalMoveHours, true));
                    foreach ($topHours as $h) {
                        $hours[$h] = true;
                    }
                }

                $rows[] = [
                    'day' => $dt->format('l d/m/Y'),
                    'usage' => $usagePct . '%',
                    'move' => $this->formatDurationMs($tripMs),
                    'idle' => $this->formatDurationMs($idleMs),
                    'dist' => round($distance / 1000, 2) . ' KM',
                    'hours' => $hours
                ];
            }

        } else {
            // Engine Hours Report (Events based)
            $events = DB::connection('pgsql')
                ->table('tc_events')
                ->select('type', 'eventtime')
                ->where('deviceid', (int)$deviceId)
                ->whereBetween('eventtime', [$fromIso, $toIso])
                ->whereIn('type', ['ignitionOn', 'ignitionOff'])
                ->orderBy('eventtime')
                ->limit(200000)
                ->get();

            // Build intervals
            $intervals = [];
            $start = null;
            foreach ($events as $ev) {
                if ($ev->type === 'ignitionOn') {
                    $start = $ev->eventtime;
                } elseif ($ev->type === 'ignitionOff' && $start) {
                    $intervals[] = ['start' => strtotime($start) * 1000, 'end' => strtotime($ev->eventtime) * 1000];
                    $start = null;
                }
            }

            foreach ($period as $dt) {
                $dateStr = $dt->format('Y-m-d');
                $dayStart = strtotime($dateStr . ' 00:00:00') * 1000;
                $dayEnd = strtotime($dateStr . ' 23:59:59') * 1000;

                $engineMs = 0;
                $hourlyEngineMs = array_fill(0, 24, 0);

                // Track min start and max end for "span" calculation (Usage/Idle)
                $minStart = null;
                $maxEnd = null;

                foreach ($intervals as $int) {
                    $s = $int['start'];
                    $e = $int['end'];

                    if ($e <= $dayStart || $s >= $dayEnd) continue;

                    $os = max($s, $dayStart);
                    $oe = min($e, $dayEnd);

                    if ($oe > $os) {
                        $diff = $oe - $os;
                        $engineMs += $diff;

                        // Update span
                        if ($minStart === null || $os < $minStart) $minStart = $os;
                        if ($maxEnd === null || $oe > $maxEnd) $maxEnd = $oe;

                        // Hourly distribution
                        $sh = (int)date('H', $os / 1000);
                        $eh = (int)date('H', $oe / 1000);

                        // Simple distribution: if it spans multiple hours, we might just add to start hour or split it.
                        // For accuracy, let's split it.
                        for ($h = $sh; $h <= $eh; $h++) {
                            $hStart = strtotime($dateStr . " $h:00:00") * 1000;
                            $hEnd = strtotime($dateStr . " $h:59:59") * 1000 + 1000; // end of hour

                            $hos = max($os, $hStart);
                            $hoe = min($oe, $hEnd);

                            if ($hoe > $hos) {
                                $hourlyEngineMs[$h] += ($hoe - $hos);
                            }
                        }
                    }
                }

                // Usage & Idle Logic (Consistent with Movement Report)
                $totalMs = 0;
                if ($minStart !== null && $maxEnd !== null) {
                    $totalMs = max(0, $maxEnd - $minStart);
                }

                $idleMs = max(0, $totalMs - $engineMs);
                $usagePct = $totalMs > 0 ? round(($engineMs / $totalMs) * 100) : 0;

                // Blue boxes logic (Quantity based)
                $hours = array_fill(0, 24, false);
                $totalEngineHours = ceil($engineMs / 3600000);

                if ($totalEngineHours > 0) {
                    arsort($hourlyEngineMs);
                    $topHours = array_keys(array_slice($hourlyEngineMs, 0, $totalEngineHours, true));
                    foreach ($topHours as $h) {
                        $hours[$h] = true;
                    }
                }

                $rows[] = [
                    'day' => $dt->format('l d/m/Y'),
                    'usage' => $usagePct . '%',
                    'move' => $this->formatDurationMs($engineMs),
                    'idle' => $this->formatDurationMs($idleMs),
                    'dist' => '0 KM',
                    'hours' => $hours
                ];
            }
        }

        return [
            'summary' => [
                'vehicleIdDisplay' => $vehicleIdDisplay,
                'deviceId' => $uniqueId,
                'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                'totalDays' => $totalDays
            ],
            'rows' => $rows,
            'type' => $type
        ];
    }

    private function haversine($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function formatDurationMs($ms) {
        $hours = floor($ms / 3600000);
        $minutes = floor(($ms % 3600000) / 60000);
        return "{$hours} hours {$minutes} minutes";
    }

}
