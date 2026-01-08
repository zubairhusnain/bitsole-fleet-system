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
        return $this->fetchTravelHistoryDb($request);
    }

    public function fetchTravelHistoryDb($request)
    {
        $deviceId = $request->device_id;
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $trips = $this->fetchTripsDb($deviceId, $from, $to);
        return $trips;
    }

    public function fetchTripsDb($deviceId, $from, $to)
    {
        // 1. Fetch Ignition Events with Position Data
        $events = DB::connection('pgsql')->select("
            SELECT
                e.id, e.type, e.eventtime,
                p.latitude, p.longitude, p.address,
                CAST(COALESCE(NULLIF(CAST(p.attributes AS json)->>'totalDistance', ''), '0') AS FLOAT) as total_distance,
                CAST(COALESCE(NULLIF(CAST(p.attributes AS json)->>'odometer', ''), '0') AS FLOAT) as odometer
            FROM tc_events e
            LEFT JOIN tc_positions p ON e.positionid = p.id
            WHERE e.deviceid = ?
              AND e.eventtime BETWEEN ? AND ?
              AND e.type IN ('ignitionOn', 'ignitionOff')
            ORDER BY e.eventtime ASC
        ", [$deviceId, $from, $to]);

        $trips = [];
        $currentStart = null;

        foreach ($events as $event) {
            if ($event->type === 'ignitionOn') {
                $currentStart = $event;
            } elseif ($event->type === 'ignitionOff') {
                if ($currentStart !== null) {
                    // Close trip
                    $dist = $event->total_distance - $currentStart->total_distance;
                    if ($dist < 0) $dist = 0;

                    // Duration
                    $startTime = strtotime($currentStart->eventtime);
                    $endTime = strtotime($event->eventtime);
                    $duration = $endTime - $startTime;

                    // Filter noise (e.g. < 100m or < 2 min)
                    if ($duration > 120 || $dist > 100) {
                        // Fetch Max Speed
                        $maxSpeed = 0;
                        try {
                            $ms = DB::connection('pgsql')->selectOne("
                                SELECT MAX(speed) as max_speed
                                FROM tc_positions
                                WHERE deviceid = ?
                                  AND fixtime BETWEEN ? AND ?
                            ", [$deviceId, $currentStart->eventtime, $event->eventtime]);
                            $maxSpeed = $ms ? $ms->max_speed : 0;
                        } catch (\Throwable $t) {}

                        $avgSpeed = ($duration > 0) ? ($dist / $duration) * 1.94384 : 0; // m/s to knots if needed, or just km/h?
                        // Traccar usually reports speed in knots in DB, but API returns knots.
                        // dist is in meters. duration in seconds. m/s * 1.94384 = knots.
                        // Let's assume user wants knots (Traccar standard) or km/h?
                        // Traccar UI usually converts. Let's return knots to match API.

                        $trips[] = [
                            'deviceId' => $deviceId,
                            'deviceName' => '', // Fill if needed
                            'distance' => $dist, // meters
                            'averageSpeed' => $avgSpeed, // knots
                            'maxSpeed' => $maxSpeed, // knots
                            'spentFuel' => 0,
                            'startOdometer' => $currentStart->odometer,
                            'endOdometer' => $event->odometer,
                            'startTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($currentStart->eventtime)),
                            'endTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($event->eventtime)),
                            'startPositionId' => 0,
                            'endPositionId' => 0,
                            'startLat' => $currentStart->latitude,
                            'startLon' => $currentStart->longitude,
                            'endLat' => $event->latitude,
                            'endLon' => $event->longitude,
                            'startAddress' => $currentStart->address,
                            'endAddress' => $event->address,
                            'duration' => $duration * 1000, // ms
                            'driverUniqueId' => '',
                            'driverName' => ''
                        ];
                    }
                    $currentStart = null;
                }
            }
        }

        return $trips;
    }

    public function travelHistoryOld($request)
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
        // Redirect to DB implementation
        return $this->fetchVehicleRankingDb($request);
    }

    public function fetchVehicleRankingDb($request)
    {
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $vehicleIds = $request->vehicle_ids ?? [];

        // 1. Resolve Device IDs
        if (empty($vehicleIds)) {
            $accessible = Devices::accessibleByUser($request->user())->pluck('device_id')->all();
            $vehicleIds = $accessible;
        } else {
            $vehicleIds = is_string($vehicleIds) ? explode(',', $vehicleIds) : $vehicleIds;
        }

        if (empty($vehicleIds)) {
            return [];
        }

        $idsStr = implode(',', array_map('intval', $vehicleIds));

        // 2. Fetch Stats (Distance & Duration) from tc_positions
        // Using Direct Aggregation
        $stats = [];
        try {
            $statsData = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    SUM(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'distance', ''), '0') AS FLOAT)) as total_dist,
                    MIN(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'hours', ''), '0') AS FLOAT)) as min_hours,
                    MAX(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'hours', ''), '0') AS FLOAT)) as max_hours
                FROM tc_positions
                WHERE deviceid IN ($idsStr)
                  AND fixtime BETWEEN ? AND ?
                GROUP BY deviceid
            ", [$from, $to]);

            foreach ($statsData as $s) {
                $stats[$s->deviceid] = $s;
            }
        } catch (\Throwable $e) {
            Log::error('fetchVehicleRankingDb stats query failed: ' . $e->getMessage());
        }

        // 3. Fetch Events (Penalties) from tc_events
        // We need counts of: harshAcceleration, harshBraking, harshCornering, deviceOverspeed
        // In DB, type='deviceOverspeed'.
        // Harsh events might be type='alarm' AND attributes->>'alarm' IN (...)
        // OR type IN ('harshAcceleration', etc) depending on Traccar version.
        // We will check both for safety.

        $eventsData = [];
        try {
            $eventsQuery = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    SUM(CASE WHEN type = 'deviceOverspeed' THEN 1 ELSE 0 END) as count_overspeed,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardAcceleration' OR type = 'harshAcceleration' THEN 1
                        ELSE 0
                    END) as count_ha,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardBraking' OR type = 'harshBraking' THEN 1
                        ELSE 0
                    END) as count_hb,
                    SUM(CASE
                        WHEN (CAST(attributes AS json)->>'alarm') = 'hardCornering' OR type = 'harshCornering' THEN 1
                        ELSE 0
                    END) as count_hc
                FROM tc_events
                WHERE deviceid IN ($idsStr)
                  AND eventtime BETWEEN ? AND ?
                  AND (
                    type = 'deviceOverspeed'
                    OR type IN ('harshAcceleration', 'harshBraking', 'harshCornering')
                    OR (type = 'alarm' AND (CAST(attributes AS json)->>'alarm') IN ('hardAcceleration', 'hardBraking', 'hardCornering'))
                  )
                GROUP BY deviceid
            ", [$from, $to]);

            foreach ($eventsQuery as $e) {
                $eventsData[$e->deviceid] = $e;
            }
        } catch (\Throwable $e) {
            Log::error('fetchVehicleRankingDb events query failed: ' . $e->getMessage());
        }

        // 4. Fetch Device Details
        $tcDevices = \App\Models\TcDevice::whereIn('id', $vehicleIds)->get()->keyBy('id');

        // 5. Build Result
        $rows = collect($vehicleIds)->map(function ($deviceId) use ($stats, $eventsData, $tcDevices) {
            $tcDev = $tcDevices->get($deviceId);
            $stat = $stats[$deviceId] ?? null;
            $evt = $eventsData[$deviceId] ?? null;

            $distanceM = $stat ? $stat->total_dist : 0;
            // Engine hours in DB are usually in milliseconds
            $minH = $stat ? $stat->min_hours : 0;
            $maxH = $stat ? $stat->max_hours : 0;
            $engineHoursMs = max(0, $maxH - $minH);

            // Counts
            $ha = $evt ? $evt->count_ha : 0;
            $hb = $evt ? $evt->count_hb : 0;
            $hc = $evt ? $evt->count_hc : 0;
            $sv = $evt ? $evt->count_overspeed : 0;

            // Calculate Points (100 - penalties)
            $points = 100 - ($ha * 5) - ($hb * 5) - ($hc * 5) - ($sv * 10);
            $model = $tcDev ? ($tcDev->model ?? $tcDev->category ?? 'N/A') : 'N/A';

            return [
                'vehicleId' => $tcDev->name ?? 'Unknown',
                'typeModel' => $model,
                'distance' => round($distanceM / 1000, 2) . ' KM',
                'duration' => $this->formatDurationHms($engineHoursMs),
                'totalHA' => (int)$ha,
                'totalHB' => (int)$hb,
                'totalHC' => (int)$hc,
                'totalSV' => (int)$sv,
                'points' => $points,
                'percentage' => max(0, min(100, $points)),
            ];
        });

        // 6. Sorting Logic (copied from original)
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
                    $sortBy = 'points';
                    $sortDesc = true;
                    break;
            }
        }

        $sortedRows = $sortDesc ? $rows->sortByDesc($sortBy) : $rows->sortBy($sortBy);

        // Tie-breaker
        if (empty($request->vehicle_ids) || ($sortBy === 'points' && $sortDesc === true)) {
            $sortedRows = $sortedRows->sort(function ($a, $b) {
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

    public function vehicleRankingOld($request)
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
        return $this->yearlyReportDashboardDb($request);
    }

    public function yearlyReportDashboardDb($request)
    {
        $deviceId = $request->device_id;
        $from = $request->from_date ? \Carbon\Carbon::parse($request->from_date)->startOfDay() : \Carbon\Carbon::now()->startOfYear();
        $to = $request->to_date ? \Carbon\Carbon::parse($request->to_date)->endOfDay() : \Carbon\Carbon::now()->endOfYear();

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        // Use existing fetchTripsDb
        $trips = $this->fetchTripsDb($deviceId, $fromStr, $toStr);

        // Month buckets
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyDistance = array_fill_keys($months, 0);
        $monthlyFuel = array_fill_keys($months, 0);
        $monthlySpeedData = array_fill_keys($months, ['total' => 0, 'count' => 0]);

        foreach ($trips as $trip) {
            $monthKey = $months[date('n', strtotime($trip['startTime'])) - 1];

            // Distance (m -> km)
            $distKm = round($trip['distance'] / 1000, 1);
            $monthlyDistance[$monthKey] += $distKm;

            // Fuel
            $monthlyFuel[$monthKey] += $trip['spentFuel'] ?? 0;

            // Speed (knots -> km/h)
            $avgSpeedKnots = $trip['averageSpeed'];
             if ($avgSpeedKnots <= 162) {
                $monthlySpeedData[$monthKey]['total'] += $avgSpeedKnots * 1.852;
                $monthlySpeedData[$monthKey]['count'] += 1;
            }
        }

        // Compute averages
        $monthlyAvgSpeed = collect($months)->mapWithKeys(function ($m) use ($monthlySpeedData) {
            $total = $monthlySpeedData[$m]['total'];
            $cnt = $monthlySpeedData[$m]['count'] ?: 1;
            return [$m => round($total / $cnt, 1)];
        })->all();

        // Build Chart
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

        $totals = [
            'totalDistanceKm' => array_sum($monthlyDistance),
            'totalFuelL' => array_sum($monthlyFuel),
            'avgSpeedKph' => round(array_sum($monthlyAvgSpeed) / count($monthlyAvgSpeed), 1),
        ];

        return [
            'chart' => $chart,
            'raw' => [
                'distance_km' => array_values($monthlyDistance),
                'fuel_litres' => array_values($monthlyFuel),
                'avg_speed_kph' => array_values($monthlyAvgSpeed),
            ],
            'totals' => $totals,
            'from' => $from->format('Y-m-d\TH:i:s\Z'),
            'to' => $to->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function yearlyReportDashboardOld($request)
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
        return $this->report_summaryDb($request);
    }

    public function report_summaryDb($request)
    {
        $deviceId = $request->device_id;
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $toStr = $request->to_date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
            $toStr .= ' 23:59:59';
        }
        $to = \Carbon\Carbon::parse($toStr)->format('Y-m-d H:i:s');

        // 1. Fetch Trips
        $trips = $this->fetchTripsDb($deviceId, $from, $to);

        // 2. Calculate Stops
        $stops = [];
        usort($trips, fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
        for ($i = 0; $i < count($trips) - 1; $i++) {
            $currentTrip = $trips[$i];
            $nextTrip = $trips[$i+1];
            $stopStart = $currentTrip['endTime'];
            $stopEnd = $nextTrip['startTime'];
            $duration = strtotime($stopEnd) - strtotime($stopStart);
            if ($duration > 180) {
                 $stops[] = ['duration' => $duration * 1000];
            }
        }

        // 3. Fetch Events
        $eventTypesRaw = $request->event_types ?? 'harshBraking,harshAcceleration,overspeed';
        $eventTypes = explode(',', $eventTypesRaw);

        $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $events = DB::connection('pgsql')->select("
            SELECT type, count(*) as count
            FROM tc_events
            WHERE deviceid = ?
              AND eventtime BETWEEN ? AND ?
              AND type IN ($placeholders)
            GROUP BY type
        ", array_merge([$deviceId, $from, $to], $eventTypes));

        $eventCounts = [];
        foreach ($events as $e) {
            $eventCounts[$e->type] = $e->count;
        }

        // 4. Calculate Summary Metrics
        $totalDistance = collect($trips)->sum('distance'); // meters
        $maxSpeed = collect($trips)->max('maxSpeed') ?? 0;
        if ($maxSpeed > 162) $maxSpeed = 0;
        $avgSpeed = collect($trips)->avg('averageSpeed') ?? 0;
        if ($avgSpeed > 162) $avgSpeed = 0;

        $engineHoursMs = collect($trips)->sum('duration'); // ms

        $deviceName = DB::connection('pgsql')->table('tc_devices')->where('id', $deviceId)->value('name');

        return [
            [
                'deviceName' => $deviceName ?? '',
                'distance_km' => round($totalDistance / 1000, 2),
                'spentFuel_litres' => 0, // Placeholder
                'avgFuel_l_per_100km' => 0, // Placeholder
                'engineHours' => round($engineHoursMs / 3600000, 2),
                'maxSpeed_kph' => round($maxSpeed * 1.852, 1),
                'avgSpeed_kph' => round($avgSpeed * 1.852, 1),
                'tripCount' => count($trips),
                'stopCount' => count($stops),
                'idleTime_minutes' => round(collect($stops)->sum('duration') / 60000, 1),
                'harshBraking' => $eventCounts['harshBraking'] ?? 0,
                'harshAcceleration' => $eventCounts['harshAcceleration'] ?? 0,
                'overspeedEvents' => ($eventCounts['overspeed'] ?? 0) + ($eventCounts['deviceOverspeed'] ?? 0),
            ]
        ];
    }

    public function report_summaryOld($request)
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
        return $this->fetchFleetSummaryDb($request, $deviceIds);
    }

    public function fetchFleetSummaryOld($request, $deviceIds)
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
        return $this->fetchDailyTripsDb($request, $deviceIds);
    }

    public function fetchDailyTripsDb($request, $deviceIds)
    {
        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $allTrips = [];
        $allStops = [];

        foreach ($deviceIds as $deviceId) {
            $trips = $this->fetchTripsDb($deviceId, $from, $to);
            $allTrips = array_merge($allTrips, $trips);

            // Calculate Stops from Trips
            // Sort trips by startTime
            usort($trips, fn($a, $b) => strcmp($a['startTime'], $b['startTime']));

            for ($i = 0; $i < count($trips) - 1; $i++) {
                $currentTrip = $trips[$i];
                $nextTrip = $trips[$i+1];

                $stopStart = $currentTrip['endTime'];
                $stopEnd = $nextTrip['startTime'];

                $startTs = strtotime($stopStart);
                $endTs = strtotime($stopEnd);
                $duration = $endTs - $startTs;

                if ($duration > 180) { // Filter short stops < 3 mins
                     $allStops[] = [
                         'deviceId' => $deviceId,
                         'startTime' => $stopStart,
                         'endTime' => $stopEnd,
                         'duration' => $duration * 1000,
                         'address' => $currentTrip['endAddress'] ?? 'N/A', // Stop location is end of previous trip
                         'lat' => $currentTrip['endLat'],
                         'lon' => $currentTrip['endLon']
                     ];
                }
            }
        }

        // Format Rows (Trips)
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
                'distance_m' => $trip['distance'] ?? 0,
                'duration_ms' => $trip['duration'] ?? 0
            ];
        });

        // Format Stops
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

        // Summary
        $totalDistance = collect($allTrips)->sum('distance');
        $totalDuration = collect($allTrips)->sum('duration');
        $totalIdle = collect($allStops)->sum('duration');
        $maxSpeed = collect($allTrips)->max('maxSpeed') ?? 0;
        if ($maxSpeed > 162) $maxSpeed = 0; // Sanity check

        // Fuel - Placeholder
        $totalFuel = 0;
 
        return [
            'rows' => $rows,
            'stops' => $stopsFormatted,
            'summary' => [
                 'totalDistance' => $totalDistance,
                 'totalDuration' => $totalDuration,
                 'totalIdle' => $totalIdle,
                 'totalFuel' => $totalFuel,
                 'avgKmL' => 0,
                 'maxSpeed' => $maxSpeed * 1.852,
            ]
        ];
    }

    public function fetchDailyTripsOld($request, $deviceIds)
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
        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

            if (empty($deviceIds)) {
                return [
                    'rows' => [],
                    'summary' => [
                        'totalDistance' => 0,
                        'totalDuration' => 0,
                        'totalIdle' => 0,
                        'totalFuel' => 0,
                        'avgKmL' => 0
                    ],
                    'chart' => []
                ];
            }

            $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');
            $dailyStats = [];

            foreach ($deviceIds as $deviceId) {
                $trips = $this->fetchTripsDb($deviceId, $fromIso, $toIso);

                foreach ($trips as $trip) {
                    $day = date('Y-m-d', strtotime($trip['startTime']));
                    $key = $deviceId . '|' . $day;

                    if (!isset($dailyStats[$key])) {
                        $dailyStats[$key] = [
                            'deviceid' => $deviceId,
                            'day' => $day,
                            'distance_m' => 0,
                            'trip_ms' => 0,
                            'idle_ms' => 0,
                            'trips' => []
                        ];
                    }

                    $dailyStats[$key]['distance_m'] += $trip['distance'];
                    $dailyStats[$key]['trip_ms'] += $trip['duration'];
                    $dailyStats[$key]['trips'][] = $trip;
                }
            }

            // Calculate idle time
            foreach ($dailyStats as $key => &$stat) {
                usort($stat['trips'], fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
                $trips = $stat['trips'];
                $idleMs = 0;
                for ($i = 0; $i < count($trips) - 1; $i++) {
                    $stopStart = strtotime($trips[$i]['endTime']);
                    $stopEnd = strtotime($trips[$i+1]['startTime']);
                    $gap = ($stopEnd - $stopStart) * 1000;
                    if ($gap > 180000) { // > 3 mins in ms
                        $idleMs += $gap;
                    }
                }
                $stat['idle_ms'] = $idleMs;
                unset($stat['trips']);
            }

            $groupBy = (string)($request->group_by ?? '');
            $rows = collect($dailyStats)->values();

            if ($groupBy === 'vehicle') {
                $rows = $rows->groupBy('deviceid')->map(function ($group) use ($tcDevices) {
                    $first = $group->first();
                    $deviceId = $first['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';

                    return [
                        'key' => $deviceId,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                });
            } elseif ($groupBy === 'date') {
                 $rows = $rows->groupBy('day')->map(function ($group) {
                    $first = $group->first();
                    $day = $first['day'];
                    return [
                        'key' => $day,
                        'date' => date('d/m/Y', strtotime($day)),
                        'dateRaw' => $day,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                 });
            } else {
                 $rows = $rows->map(function ($r) use ($tcDevices) {
                    $deviceId = $r['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';
                    $day = $r['day'];

                    return [
                        'key' => $deviceId . '_' . $day,
                        'date' => date('d/m/Y', strtotime($day)),
                        'dateRaw' => $day,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $r['distance_m'],
                        'trip_ms' => $r['trip_ms'],
                        'idle_ms' => $r['idle_ms'],
                    ];
                 });
            }

            // Formatting
            $rows = $rows->map(function ($r) {
                 $distKm = round($r['distance_m'] / 1000, 2);

                 $tripMs = $r['trip_ms'];
                 $durH = floor($tripMs / 3600000);
                 $durM = floor(($tripMs % 3600000) / 60000);
                 $durS = floor(($tripMs % 60000) / 1000);

                 $idleMs = $r['idle_ms'];
                 $idleH = floor($idleMs / 3600000);
                 $idleM = floor(($idleMs % 3600000) / 60000);
                 $idleS = floor(($idleMs % 60000) / 1000);

                 $totalTime = $tripMs + $idleMs;
                 $pct = ($totalTime > 0) ? round(($idleMs / $totalTime) * 100, 1) : 0;

                 $r['distance'] = $distKm . ' KM';
                 $r['trip'] = sprintf('%dh %dm %ds', $durH, $durM, $durS);
                 $r['idle'] = sprintf('%dh %dm %ds', $idleH, $idleM, $idleS);
                 $r['idlePct'] = $pct . '%';
                 return $r;
            })->values();

            $totalDistance = $rows->sum('distance_m');
            $totalDuration = $rows->sum('trip_ms');
            $totalIdle = $rows->sum('idle_ms');

             $chartData = $rows->groupBy('dateRaw')->map(function ($group, $date) {
                 return [
                    'date' => $date,
                    'distance' => $group->sum('distance_m'),
                    'tripDuration' => $group->sum('trip_ms'),
                    'idleDuration' => $group->sum('idle_ms')
                 ];
             })->values()->sortBy('date');

            return [
                'rows' => $rows,
                'summary' => [
                    'totalDistance' => $totalDistance,
                    'totalDuration' => $totalDuration,
                    'totalIdle' => $totalIdle,
                    'totalFuel' => 0,
                    'avgKmL' => 0
                ],
                'chart' => $chartData->values()
            ];
        } catch (\Throwable $e) {
            Log::error('fetchDailySummaryDb failed', ['error' => $e->getMessage()]);
            return [
                'rows' => [],
                'summary' => [
                    'totalDistance' => 0,
                    'totalDuration' => 0,
                    'totalIdle' => 0,
                    'totalFuel' => 0,
                    'avgKmL' => 0
                ],
                'chart' => []
            ];
        }
    }

    public function fetchDailySummaryDb($request, $deviceIds)
    {
        return $this->fetchDailySummary($request, $deviceIds);
    }

    public function fetchMonthlySummary($request, $deviceIds)
    {
        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

            if (empty($deviceIds)) {
                return [
                    'rows' => [],
                    'summary' => [
                        'totalDistance' => 0,
                        'totalDuration' => 0,
                        'totalIdle' => 0,
                        'totalFuel' => 0,
                        'avgKmL' => 0
                    ],
                    'chart' => []
                ];
            }

            $tcDevices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');
            $monthlyStats = [];

            foreach ($deviceIds as $deviceId) {
                $trips = $this->fetchTripsDb($deviceId, $fromIso, $toIso);

                foreach ($trips as $trip) {
                    $month = date('Y-m', strtotime($trip['startTime']));
                    $key = $deviceId . '|' . $month;

                    if (!isset($monthlyStats[$key])) {
                        $monthlyStats[$key] = [
                            'deviceid' => $deviceId,
                            'month' => $month,
                            'distance_m' => 0,
                            'trip_ms' => 0,
                            'idle_ms' => 0,
                            'trips' => []
                        ];
                    }

                    $monthlyStats[$key]['distance_m'] += $trip['distance'];
                    $monthlyStats[$key]['trip_ms'] += $trip['duration'];
                    $monthlyStats[$key]['trips'][] = $trip;
                }
            }

            // Calculate idle time
            foreach ($monthlyStats as $key => &$stat) {
                usort($stat['trips'], fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
                $trips = $stat['trips'];
                $idleMs = 0;
                for ($i = 0; $i < count($trips) - 1; $i++) {
                    $stopStart = strtotime($trips[$i]['endTime']);
                    $stopEnd = strtotime($trips[$i+1]['startTime']);
                    $gap = ($stopEnd - $stopStart) * 1000;
                    if ($gap > 180000) { // > 3 mins in ms
                        $idleMs += $gap;
                    }
                }
                $stat['idle_ms'] = $idleMs;
                unset($stat['trips']);
            }

            $groupBy = (string)($request->group_by ?? '');
            $rows = collect($monthlyStats)->values();

            if ($groupBy === 'vehicle') {
                $rows = $rows->groupBy('deviceid')->map(function ($group) use ($tcDevices) {
                    $first = $group->first();
                    $deviceId = $first['deviceid'];
                    $dev = $tcDevices->get($deviceId);
                    $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';

                    return [
                        'key' => $deviceId,
                        'vehicleId' => $deviceId,
                        'vehicle' => $vehicleName,
                        'distance_m' => $group->sum('distance_m'),
                        'trip_ms' => $group->sum('trip_ms'),
                        'idle_ms' => $group->sum('idle_ms'),
                    ];
                });
            } else {
                 if ($groupBy === 'date') {
                     $rows = $rows->groupBy('month')->map(function ($group) {
                        $first = $group->first();
                        $month = $first['month'];
                        return [
                            'key' => $month,
                            'date' => date('m/Y', strtotime($month . '-01')),
                            'dateRaw' => $month,
                            'distance_m' => $group->sum('distance_m'),
                            'trip_ms' => $group->sum('trip_ms'),
                            'idle_ms' => $group->sum('idle_ms'),
                        ];
                     });
                 } else {
                     $rows = $rows->map(function ($r) use ($tcDevices) {
                        $deviceId = $r['deviceid'];
                        $dev = $tcDevices->get($deviceId);
                        $vehicleName = $dev ? ($dev->name ?? 'Unknown') : 'Unknown';
                        $month = $r['month'];

                        return [
                            'key' => $deviceId . '_' . $month,
                            'date' => date('m/Y', strtotime($month . '-01')),
                            'dateRaw' => $month,
                            'vehicleId' => $deviceId,
                            'vehicle' => $vehicleName,
                            'distance_m' => $r['distance_m'],
                            'trip_ms' => $r['trip_ms'],
                            'idle_ms' => $r['idle_ms'],
                        ];
                     });
                 }
            }

            // Formatting
            $rows = $rows->map(function ($r) {
                 $distKm = round($r['distance_m'] / 1000, 2);

                 $tripMs = $r['trip_ms'];
                 $durH = floor($tripMs / 3600000);
                 $durM = floor(($tripMs % 3600000) / 60000);
                 $durS = floor(($tripMs % 60000) / 1000);

                 $idleMs = $r['idle_ms'];
                 $idleH = floor($idleMs / 3600000);
                 $idleM = floor(($idleMs % 3600000) / 60000);
                 $idleS = floor(($idleMs % 60000) / 1000);

                 $totalTime = $tripMs + $idleMs;
                 $pct = ($totalTime > 0) ? round(($idleMs / $totalTime) * 100, 1) : 0;

                 $r['distance'] = $distKm . ' KM';
                 $r['trip'] = sprintf('%dh %dm %ds', $durH, $durM, $durS);
                 $r['idle'] = sprintf('%dh %dm %ds', $idleH, $idleM, $idleS);
                 $r['idlePct'] = $pct . '%';
                 return $r;
            })->values();

            $totalDistance = $rows->sum('distance_m');
            $totalDuration = $rows->sum('trip_ms');
            $totalIdle = $rows->sum('idle_ms');

             $chartData = $rows->groupBy('dateRaw')->map(function ($group, $date) {
                 return [
                    'date' => $date,
                    'distance' => $group->sum('distance_m'),
                    'tripDuration' => $group->sum('trip_ms'),
                    'idleDuration' => $group->sum('idle_ms')
                 ];
             })->values()->sortBy('date');

            return [
                'rows' => $rows,
                'summary' => [
                    'totalDistance' => $totalDistance,
                    'totalDuration' => $totalDuration,
                    'totalIdle' => $totalIdle,
                    'totalFuel' => 0,
                    'avgKmL' => 0
                ],
                'chart' => $chartData->values()
            ];
        } catch (\Throwable $e) {
            Log::error('fetchMonthlySummary failed', ['error' => $e->getMessage()]);
            return [
                'rows' => [],
                'summary' => [
                    'totalDistance' => 0,
                    'totalDuration' => 0,
                    'totalIdle' => 0,
                    'totalFuel' => 0,
                    'avgKmL' => 0
                ],
                'chart' => []
            ];
        }
    }

    public function fetchDailyBreakdownMap($request, $deviceIds)
    {
        return $this->fetchDailyBreakdownMapDb($request, $deviceIds);
    }

    public function fetchDailyBreakdownMapDb($request, $deviceIds)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $from = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
        $to = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');

        $allTrips = [];
        $allEvents = [];
        $allRoutes = [];
        $allStops = [];

        foreach ($deviceIds as $deviceId) {
            // 1. Trips
            $trips = $this->fetchTripsDb($deviceId, $from, $to);
            foreach ($trips as &$t) {
                $t['deviceId'] = $deviceId;
            }
            // Fetch device name for the first trip
            $deviceName = DB::connection('pgsql')->table('tc_devices')->where('id', $deviceId)->value('name');
             foreach ($trips as &$t) {
                $t['deviceName'] = $deviceName;
            }

            $allTrips = array_merge($allTrips, $trips);

            // 2. Events (with location)
            $eventsData = DB::connection('pgsql')->select("
                SELECT
                    e.id, e.type, e.eventtime, e.deviceid, e.attributes, e.positionid,
                    p.latitude, p.longitude, p.address
                FROM tc_events e
                LEFT JOIN tc_positions p ON e.positionid = p.id
                WHERE e.deviceid = ?
                  AND e.eventtime BETWEEN ? AND ?
            ", [$deviceId, $from, $to]);

            foreach ($eventsData as $e) {
                $allEvents[] = [
                    'id' => $e->id,
                    'type' => $e->type,
                    'eventTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($e->eventtime)),
                    'deviceId' => $e->deviceid,
                    'attributes' => json_decode($e->attributes, true),
                    'positionId' => $e->positionid,
                    'latitude' => $e->latitude,
                    'longitude' => $e->longitude,
                    'address' => $e->address
                ];
            }

            // 3. Routes (Positions)
            $positions = DB::connection('pgsql')->select("
                SELECT id, deviceid, fixtime, latitude, longitude, speed, course, address, attributes
                FROM tc_positions
                WHERE deviceid = ?
                  AND fixtime BETWEEN ? AND ?
                ORDER BY fixtime ASC
            ", [$deviceId, $from, $to]);

            foreach ($positions as $p) {
                $allRoutes[] = [
                    'id' => $p->id,
                    'deviceId' => $p->deviceid,
                    'fixTime' => date('Y-m-d\TH:i:s.v\Z', strtotime($p->fixtime)),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'speed' => $p->speed,
                    'course' => $p->course,
                    'address' => $p->address,
                    'attributes' => json_decode($p->attributes, true)
                ];
            }

            // 4. Stops (Calculated)
            usort($trips, fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
            for ($i = 0; $i < count($trips) - 1; $i++) {
                $currentTrip = $trips[$i];
                $nextTrip = $trips[$i+1];
                $stopStart = $currentTrip['endTime'];
                $stopEnd = $nextTrip['startTime'];
                $duration = strtotime($stopEnd) - strtotime($stopStart);
                if ($duration > 180) {
                     $allStops[] = [
                         'deviceId' => $deviceId,
                         'startTime' => $stopStart,
                         'endTime' => $stopEnd,
                         'duration' => $duration * 1000,
                         'address' => $currentTrip['endAddress'] ?? 'N/A',
                         'latitude' => $currentTrip['endLat'],
                         'longitude' => $currentTrip['endLon']
                     ];
                }
            }
        }

        $trips = collect($allTrips);
        $events = collect($allEvents);
        $stops = collect($allStops);
        $routes = collect($allRoutes);

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

                $tripStart = strtotime($trip['startTime']);
                $tripEnd = strtotime($trip['endTime']);

                $tripEvents = $dayEvents->filter(function($e) use ($tripStart, $tripEnd) {
                    $t = strtotime($e['eventTime']);
                    return $t >= $tripStart && $t <= $tripEnd;
                });

                $svCount = $tripEvents->where('type', 'overspeed')->count() + $tripEvents->where('type', 'deviceOverspeed')->count();
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

            // Events
            $eventTimeline = $dayEvents->filter(function($event) {
                return !in_array($event['type'], ['deviceOnline', 'deviceOffline']);
            })->map(function($event) use ($dayRoutes) {
                $eventTs = strtotime($event['eventTime']);
                // Use joined location if available, otherwise fallback to route match (rare)
                $lat = $event['latitude'] ?? 0;
                $lon = $event['longitude'] ?? 0;
                $addr = $event['address'] ?? '';

                if ($lat == 0 && $lon == 0) {
                     $closest = $dayRoutes->sortBy(function($r) use ($eventTs) {
                        return abs(strtotime($r['fixTime']) - $eventTs);
                    })->first();
                    $lat = $closest['latitude'] ?? 0;
                    $lon = $closest['longitude'] ?? 0;
                    $addr = $closest['address'] ?? '';
                }

                $friendlyName = $event['type'];
                if ($event['type'] == 'overspeed' || $event['type'] == 'deviceOverspeed') $friendlyName = 'Overspeed';
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

            $durSec = floor($totalDur / 1000);
            $h = floor($durSec / 3600);
            $m = floor(($durSec % 3600) / 60);
            $s = $durSec % 60;
            $formattedDur = sprintf('%dh %dm %ds', $h, $m, $s);

            $idleSec = floor($totalIdle / 1000);
            $ih = floor($idleSec / 3600);
            $im = floor(($idleSec % 3600) / 60);
            $is = $idleSec % 60;
            $formattedIdle = sprintf('%dh %dm %ds', $ih, $im, $is);

            return [
                'date' => $date,
                'deviceId' => $deviceId,
                'deviceName' => $deviceName,
                'totalDistance' => round($totalDist / 1000, 2) . ' KM',
                'totalDuration' => $formattedDur,
                'totalIdle' => $formattedIdle,
                'timeline' => $timeline,
                'route' => $routePoints
            ];
        });

        return $result->values();
    }

    public function fetchDailyBreakdownMapOld($request, $deviceIds)
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

        $fromStr = $request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $fromStr .= ' 00:00:00';
        }

        $toStr = $request->to_date;
        if (!$toStr) {
            $toStr = date('Y-m-d H:i:s');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
        }

        $rows = DB::connection('pgsql')->table('tc_events as e')
            ->leftJoin('tc_positions as p', 'e.positionid', '=', 'p.id')
            ->select('e.eventtime', 'e.type', 'e.attributes')
            ->where('e.deviceid', (int)$deviceId)
            ->whereBetween('e.eventtime', [$fromStr, $toStr])
            ->orderBy('e.eventtime', 'asc')
            ->get();

        $formattedEvents = collect($rows)->map(function ($row) use ($deviceName) {
            $evt = [
                'eventTime' => $row->eventtime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $row->type ?? 'unknown',
                'attributes' => $row->attributes ?? null
            ];
            return [
                'eventTime' => $evt['eventTime'],
                'deviceName' => $evt['deviceName'],
                'type' => $evt['type'],
                'description' => $this->formatEventDescription($evt),
                'attributes' => $evt['attributes']
            ];
        })->all();

        return $formattedEvents;
    }

        public function getDeviceStops($request)
    {
        $deviceId = $request->device_id;

        // Check permission and get device details
        $deviceName = Devices::accessibleByUser($request->user())
            ->where('device_id', $deviceId)
            ->first();

        if (!$deviceName) {
             return [];
        }

        $fromStr = $request->from_date ?? date('Y-m-d H:i:s', strtotime('-7 days'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $fromStr .= ' 00:00:00';
        }

        $toStr = $request->to_date;
        if (!$toStr) {
            $toStr = date('Y-m-d H:i:s');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                $toStr .= ' 23:59:59';
            }
        }

        $rows = DB::connection('pgsql')->table('tc_events as e')
            ->leftJoin('tc_positions as p', 'e.positionid', '=', 'p.id')
            ->select('e.eventtime', 'e.type', 'e.attributes')
            ->where('e.deviceid', (int)$deviceId)
            ->whereBetween('e.eventtime', [$fromStr, $toStr])
            ->whereIn('e.type', ['deviceStopped'])
            ->orderBy('e.eventtime', 'asc')
            ->get();

        $formattedEvents = collect($rows)->map(function ($row) use ($deviceName) {
            $evt = [
                'eventTime' => $row->eventtime ?? date('Y-m-d H:i:s'),
                'deviceName' => $deviceName->device_modal ?? 'Unknown Device',
                'type' => $row->type ?? 'unknown',
                'attributes' => $row->attributes ?? null
            ];
            return [
                'eventTime' => $evt['eventTime'],
                'deviceName' => $evt['deviceName'],
                'type' => $evt['type'],
                'description' => $this->formatEventDescription($evt),
                'attributes' => $evt['attributes']
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

    public function fetchFleetSummaryDb($request, $deviceIds)
    {
        // Format the timestamps
        try {
            $fromIso = \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s');
            $toIso = \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::error("fetchFleetSummaryDb: Date parse error: " . $e->getMessage());
            return [];
        }

        // Calculate days for averaging
        $days = max(1, \Carbon\Carbon::parse($request->from_date)->diffInDays(\Carbon\Carbon::parse($request->to_date)) + 1);

        // Fetch Devices
        $devices = \App\Models\TcDevice::whereIn('id', $deviceIds)->get()->keyBy('id');

        // Events Counts & Fuel Refills
        try {
            $events = DB::connection('pgsql')->table('tc_events')
                ->select('deviceid', 'type', DB::raw('count(*) as count'), DB::raw("SUM(CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'amount', ''), '0') AS FLOAT)) as fuel_amount"))
                ->whereIn('deviceid', $deviceIds)
                ->whereBetween('eventtime', [$fromIso, $toIso])
                ->whereIn('type', ['harshAcceleration', 'harshBraking', 'overspeed', 'fuelIncrease'])
                ->groupBy('deviceid', 'type')
                ->get();
        } catch (\Throwable $e) {
            Log::error('fetchFleetSummaryDb events query failed: ' . $e->getMessage());
            $events = collect();
        }

        $eventsByDevice = $events->groupBy('deviceid');

        // Position Stats (Dist, Hours, Speed, Idle)
        // Direct aggregation for better performance and reliability
        $idsStr = implode(',', array_map('intval', $deviceIds));
        if (empty($idsStr)) return [];

        try {
            $stats = DB::connection('pgsql')->select("
                SELECT
                    deviceid,
                    MIN(fixtime) as start_time,
                    MAX(fixtime) as end_time,
                    COUNT(*) as total_points,
                    SUM(CASE WHEN speed < 1.0 AND (CAST(attributes AS json)->>'ignition') = 'true' THEN 1 ELSE 0 END) as idle_points,
                    SUM(CASE WHEN (CAST(attributes AS json)->>'motion') = 'true' THEN CAST(COALESCE(NULLIF(CAST(attributes AS json)->>'distance', ''), '0') AS FLOAT) ELSE 0 END) as total_dist,
                    AVG(speed) as avg_speed
                FROM tc_positions
                WHERE deviceid IN ($idsStr)
                  AND fixtime BETWEEN ? AND ?
                GROUP BY deviceid
            ", [$fromIso, $toIso]);
        } catch (\Throwable $e) {
            Log::error('fetchFleetSummaryDb stats query failed: ' . $e->getMessage());
            $stats = [];
        }

        $statsByDevice = collect($stats)->keyBy('deviceid');

        $fuelStatsByDevice = collect();
        try {
            $fuelStats = DB::connection('pgsql')->select("
                WITH pos_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        COALESCE(
                            NULLIF(CAST(attributes AS json)->>'io89', ''),
                            NULLIF(CAST(attributes AS json)->>'CAN_FuelPercentage_89', ''),
                            NULLIF(CAST(attributes AS json)->>'io48', ''),
                            NULLIF(CAST(attributes AS json)->>'io16', ''),
                            NULLIF(CAST(attributes AS json)->>'fuel', ''),
                            NULLIF(CAST(attributes AS json)->>'fuelLevel', '')
                        ) AS raw_fuel
                    FROM tc_positions
                    WHERE deviceid IN ($idsStr)
                      AND fixtime BETWEEN ? AND ?
                ),
                clean_data AS (
                    SELECT
                        deviceid,
                        fixtime,
                        CAST(raw_fuel AS FLOAT) as fuel_level
                    FROM pos_data
                    WHERE raw_fuel ~ '^[0-9]+(\.[0-9]+)?$'
                      AND CAST(raw_fuel AS FLOAT) BETWEEN 0 AND 100
                ),
                pos_with_prev AS (
                    SELECT
                        deviceid,
                        fixtime,
                        fuel_level,
                        LAG(fuel_level) OVER (PARTITION BY deviceid ORDER BY fixtime) as prev_fuel_level
                    FROM clean_data
                )
                SELECT
                    deviceid,
                    SUM(CASE WHEN prev_fuel_level > fuel_level THEN prev_fuel_level - fuel_level ELSE 0 END) as total_drop_pct,
                    SUM(CASE WHEN fuel_level > prev_fuel_level + 5 THEN fuel_level - prev_fuel_level ELSE 0 END) as total_increase_pct,
                    COUNT(CASE WHEN fuel_level > prev_fuel_level + 5 THEN 1 END) as refill_count
                FROM pos_with_prev
                GROUP BY deviceid
            ", [$fromIso, $toIso]);
            $fuelStatsByDevice = collect($fuelStats)->keyBy('deviceid');
        } catch (\Throwable $e) {
            Log::error('fetchFleetSummaryDb fuel stats query failed: ' . $e->getMessage());
        }

        $ignitionEvents = [];
        try {
            $ignitionEvents = DB::connection('pgsql')->select("
                SELECT deviceid, type, eventtime
                FROM tc_events
                WHERE deviceid IN ($idsStr)
                  AND eventtime BETWEEN ? AND ?
                  AND type IN ('ignitionOn','ignitionOff')
                ORDER BY deviceid, eventtime ASC
            ", [$fromIso, $toIso]);
        } catch (\Throwable $e) {
            Log::error('fetchFleetSummaryDb ignition events query failed: ' . $e->getMessage());
            $ignitionEvents = [];
        }

        $durMsByDevice = collect($ignitionEvents)->groupBy('deviceid')->map(function ($evts) {
            $currentStart = null;
            $total = 0;
            foreach ($evts as $e) {
                $type = is_object($e) ? ($e->type ?? '') : ($e['type'] ?? '');
                $time = is_object($e) ? ($e->eventtime ?? '') : ($e['eventtime'] ?? '');
                if ($type === 'ignitionOn') {
                    $currentStart = $time;
                } elseif ($type === 'ignitionOff') {
                    if ($currentStart) {
                        $start = strtotime($currentStart);
                        $end = strtotime($time);
                        if ($end > $start) {
                            $total += ($end - $start) * 1000;
                        }
                        $currentStart = null;
                    }
                }
            }
            return $total;
        });

        $rows = collect($deviceIds)->map(function ($deviceId) use ($devices, $statsByDevice, $eventsByDevice, $days, $durMsByDevice, $fuelStatsByDevice) {
            $dev = $devices->get($deviceId);
            $stat = $statsByDevice->get($deviceId);
            $devEvents = $eventsByDevice->get($deviceId, collect());

            $deviceName = $dev->name ?? 'Unknown';

            // Distance (Sum of deltas)
            $distM = $stat ? floatval($stat->total_dist) : 0;
            $distTotalKm = round($distM / 1000, 2);
            $distAvg = round($distTotalKm / $days, 2);

            $engineHoursMs = $durMsByDevice->get($deviceId, 0);

            $durTotalHours = floor($engineHoursMs / 3600000);
            $durTotalMinutes = floor(($engineHoursMs % 3600000) / 60000);
            $durTotalStr = "{$durTotalHours}h {$durTotalMinutes}m";

            $durAvgHours = $days > 0 ? floor(($engineHoursMs / $days) / 3600000) : 0;
            $durAvgMinutes = $days > 0 ? floor((($engineHoursMs / $days) % 3600000) / 60000) : 0;
            $durAvgStr = "{$durAvgHours}h {$durAvgMinutes}m";

            // Idle (Approximation based on points ratio)
            // Idle Duration = Total Time * (Idle Points / Total Points)
            $startTime = $stat ? strtotime($stat->start_time) : 0;
            $endTime = $stat ? strtotime($stat->end_time) : 0;
            $totalTimeSeconds = max(0, $endTime - $startTime);

            $totalPoints = $stat ? intval($stat->total_points) : 0;
            $idlePoints = $stat ? intval($stat->idle_points) : 0;

            $idleSeconds = ($totalPoints > 0) ? ($totalTimeSeconds * ($idlePoints / $totalPoints)) : 0;
            $idleMs = $idleSeconds * 1000;

            $idleTotalHours = floor($idleMs / 3600000);
            $idleTotalMinutes = floor(($idleMs % 3600000) / 60000);
            $idleTotalStr = "{$idleTotalHours}h {$idleTotalMinutes}m";

            $idleAvgHours = $days > 0 ? floor(($idleMs / $days) / 3600000) : 0;
            $idleAvgMinutes = $days > 0 ? floor((($idleMs / $days) % 3600000) / 60000) : 0;
            $idleAvgStr = "{$idleAvgHours}h {$idleAvgMinutes}m";

            // Utilisation
            $totalPossibleMs = $days * 24 * 60 * 60 * 1000;
            $utilPct = $totalPossibleMs > 0 ? round(($engineHoursMs / $totalPossibleMs) * 100, 1) : 0;

            $fuelStat = $fuelStatsByDevice->get($deviceId);

            // Get tank capacity from device attributes (default 50L)
            $attrs = is_string($dev->attributes) ? json_decode($dev->attributes, true) : (array)$dev->attributes;
            $tankCapacity = floatval($attrs['fuelTankCapacity'] ?? 50);
            if ($tankCapacity <= 0) $tankCapacity = 50;

            // Fuel Consumption (from level drops)
            $totalDropPct = $fuelStat ? floatval($fuelStat->total_drop_pct) : 0;
            $spentFuel = ($totalDropPct / 100) * $tankCapacity;

            $avgLitresPerDay = round($spentFuel / $days, 2);
            $avgKmL = ($spentFuel > 0) ? round($distTotalKm / $spentFuel, 2) : 0;

            // Refills (from level increases)
            $refillPct = $fuelStat ? floatval($fuelStat->total_increase_pct) : 0;
            $refillTotal = ($refillPct / 100) * $tankCapacity;
            $refillCount = $fuelStat ? intval($fuelStat->refill_count) : 0;

            // Speed
            $rawAvgSpeed = $stat->avg_speed ?? 0; // Knots
            $avgSpeed = round($rawAvgSpeed * 1.852, 1);

            return [
                'key' => $deviceId,
                'vehicleId' => $deviceId,
                'vehicleName' => $deviceName,
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

        return $rows->values();
    }

}
