<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Models\Devices;
use App\Models\TcGroup;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * @var \App\Services\ReportService
     */
    protected \App\Services\ReportService $reportService;

    public function __construct(\App\Services\ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function vehicleStatus(Request $request)
    {
        $query = $this->buildVehicleStatusQuery($request);

        // Pagination or fetch all
        $perPage = $request->input('per_page', 25);

        $devices = $query->orderByDesc('id')->paginate($perPage);

        $this->enrichWithIgnitionData($devices->getCollection());

        return $devices;
    }

    public function exportVehicleStatusPdf(Request $request)
    {
        $query = $this->buildVehicleStatusQuery($request);
        $devices = $query->orderByDesc('id')->get();

        $this->enrichWithIgnitionData($devices);

        $rows = $this->processVehicleDataForPdf($devices);

        $pdf = Pdf::loadView('reports.vehicle_status_pdf', ['rows' => $rows]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('Vehicle_Status_Report_' . date('Y-m-d') . '.pdf');
    }

    private function buildVehicleStatusQuery(Request $request)
    {
        $user = $request->user();

        // Apply role-based access control
        if ($request->boolean('mine')) {
            $query = Devices::accessibleByUser($user);
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } else {
            $query = Devices::accessibleByUser($user);
        }

        // Filter by specific vehicle if provided
        if ($request->filled('vehicle_id')) {
            $query->where('device_id', $request->vehicle_id);
        }

        // Filter by group if provided
        if ($request->filled('group_id')) {
            $groupId = $request->group_id;
            $query->whereHas('tcDevice', function($q) use ($groupId) {
                $q->where('groupid', $groupId);
            });
        }

        // Eager load tcDevice and its current position
        $query->with(['tcDevice.position', 'manager']);

        return $query;
    }

    private function enrichWithIgnitionData($devicesCollection)
    {
        // Fetch last ignition events for these devices
        $deviceIds = $devicesCollection->pluck('device_id')->unique()->values()->all();

        if (empty($deviceIds)) {
            return;
        }

        $ignitionEvents = DB::connection('pgsql')
            ->table('tc_events')
            ->select('deviceid', 'type', DB::raw('MAX(eventtime) as last_time'))
            ->whereIn('deviceid', $deviceIds)
            ->whereIn('type', ['ignitionOn', 'ignitionOff'])
            ->groupBy('deviceid', 'type')
            ->get();

        $ignitionTimes = [];
        foreach ($ignitionEvents as $evt) {
            $ignitionTimes[$evt->deviceid][$evt->type] = $evt->last_time;
        }

        // Helper for date formatting
        $formatDate = function ($dateStr) {
            if (!$dateStr) return null;
            return date('d/m/Y - h:i A', strtotime($dateStr));
        };

        // Enrich the devices collection
        $devicesCollection->transform(function ($device) use ($ignitionTimes, $formatDate) {
            $ignOnTime = $ignitionTimes[$device->device_id]['ignitionOn'] ?? null;
            $ignOffTime = $ignitionTimes[$device->device_id]['ignitionOff'] ?? null;

            $device->last_ignition_on = $ignOnTime ? $formatDate($ignOnTime) : null;
            $device->last_ignition_off = $ignOffTime ? $formatDate($ignOffTime) : null;

            return $device;
        });
    }

    private function processVehicleDataForPdf($devices)
    {
        return $devices->map(function ($v) {
            $tc = $v->tcDevice;
            $pos = $tc?->position;

            $decode = fn($a) => is_string($a) ? json_decode($a, true) : (is_array($a) ? $a : []);
            $attrs = $decode($pos?->attributes ?? []);
            $deviceAttrs = $decode($tc?->attributes ?? []);
            $mergedAttrs = array_merge($deviceAttrs, $attrs);

            // Ignition
            $ignRaw = $mergedAttrs['ignition'] ?? $v->ignition ?? false;
            $ignition = ($ignRaw === true || $ignRaw === 1 || strtolower((string)$ignRaw) === 'on');

            // Speed
            $speedVal = $pos?->speed ?? 0; // knots
            $speedKmh = round($speedVal * 1.852);

            // Odometer
            $odometer = isset($mergedAttrs['odometer']) ? round($mergedAttrs['odometer'] / 1000) . ' km' : '0 km';
            if (isset($mergedAttrs['totalDistance'])) {
                 $odometer = round($mergedAttrs['totalDistance'] / 1000) . ' km';
            }

            // Location
            $lat = $pos?->latitude ? number_format($pos->latitude, 5) : 'N/A';
            $lon = $pos?->longitude ? number_format($pos->longitude, 5) : 'N/A';
            $address = $pos?->address ?? 'N/A';

            // Signal
            $sat = $mergedAttrs['sat'] ?? 0;
            $signal = 'Weak';
            if ($sat >= 7) $signal = 'Good';
            elseif ($sat >= 4) $signal = 'Fair';

            return [
                'vehicle_id' => $tc?->name ?? 'Unknown',
                'owner' => $v->manager?->name ?? 'N/A',
                'type_model' => trim(($deviceAttrs['type'] ?? '') . ' ' . ($tc?->model ?? '')),
                'device_model' => $tc?->model ?? 'N/A',
                'imei' => $tc?->uniqueid ?? 'N/A',
                'iccid' => $deviceAttrs['iccid'] ?? 'N/A',
                'odometer' => $odometer,
                'power' => $ignition ? 'On' : 'Off',
                'last_report' => $pos?->servertime ? date('d/m/Y - h:i A', strtotime($pos->servertime)) : 'N/A',
                'latitude' => $lat,
                'longitude' => $lon,
                'location' => $address,
                'speed' => $speedKmh . ' km/h',
                'gps_signal' => $signal,
                'ignition' => $ignition ? 'ON' : 'OFF',
                'last_ignition_on' => $v->last_ignition_on ?? 'N/A',
                'last_ignition_off' => $v->last_ignition_off ?? 'N/A',
                'activation_date' => $v->created_at ? $v->created_at->format('d/m/Y') : 'N/A',
            ];
        });
    }

    public function tripSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'device_ids' => 'sometimes|array',
            'device_ids.*' => 'integer'
        ]);

        $deviceIds = $this->getDeviceIds($request);

        if (empty($deviceIds)) {
            return response()->json([]);
        }

        return $this->reportService->fetchFleetSummary($request, $deviceIds);
    }

    public function dailyTrips(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) return response()->json([]);
        return $this->reportService->fetchDailyTrips($request, $deviceIds);
    }

    public function dailyBreakdownMap(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) return response()->json([]);
        return $this->reportService->fetchDailyBreakdownMap($request, $deviceIds);
    }

    public function dailySummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) return response()->json([]);
        return $this->reportService->fetchDailySummary($request, $deviceIds);
    }

    public function monthlySummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) return response()->json([]);
        return $this->reportService->fetchMonthlySummary($request, $deviceIds);
    }

    public function assetActivity(Request $request)
    {
        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $from = \Carbon\Carbon::parse($request->from_date);
            $to = \Carbon\Carbon::parse($request->to_date);
            if ($from->diffInDays($to) > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }

            $deviceIds = $this->getDeviceIds($request);
            if (empty($deviceIds)) return response()->json([]);
            return $this->reportService->fetchAssetActivity($request, $deviceIds);
        } catch (\Throwable $e) {
            Log::error('assetActivity failed', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    public function vehicleActivity(Request $request)
    {
        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $from = \Carbon\Carbon::parse($request->from_date);
            $to = \Carbon\Carbon::parse($request->to_date);
            if ($from->diffInDays($to) > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }

            $deviceIds = $this->getDeviceIds($request);
            if (empty($deviceIds)) return response()->json([]);
            return $this->reportService->fetchVehicleActivity($request, $deviceIds);
        } catch (\Throwable $e) {
            Log::error('vehicleActivity failed', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    public function idling(Request $request)
    {
        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            $from = \Carbon\Carbon::parse($request->from_date);
            $to = \Carbon\Carbon::parse($request->to_date);
            if ($from->diffInDays($to) > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }

            $deviceIds = $this->getDeviceIds($request);
            if (empty($deviceIds)) return response()->json([]);

            return $this->reportService->fetchIdlingReport($request, $deviceIds);
        } catch (\Throwable $e) {
            Log::error('idling report failed', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    public function utilisation(Request $request)
    {
        set_time_limit(300); // 5 minutes to prevent 504 Gateway Timeout

        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'device_ids' => 'required|array|min:1',
                'type' => 'sometimes|string|in:Movement,Engine Hours',
            ]);

            $from = \Carbon\Carbon::parse($request->from_date);
            $to = \Carbon\Carbon::parse($request->to_date);
            if ($from->diffInDays($to) > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }

            // We only support single device for this report as per UI
            $deviceId = $request->device_ids[0];

            return $this->reportService->fetchUtilisationReport($request, $deviceId);
        } catch (\Throwable $e) {
            Log::error('utilisation report failed', ['error' => $e->getMessage()]);
            try {
                $from = \Carbon\Carbon::parse($request->from_date);
                $to = \Carbon\Carbon::parse($request->to_date);
                $totalDays = max(1, $from->diffInDays($to) + 1);
                $deviceId = is_array($request->device_ids ?? null) ? ($request->device_ids[0] ?? null) : null;

                $vehicleNo = '';
                $uniqueId = $deviceId;

                if ($deviceId) {
                    try {
                        $vehicleRec = Devices::with('tcDevice')->where('device_id', $deviceId)->first();
                        $tcDevice = $vehicleRec ? $vehicleRec->tcDevice : null;
                        $uniqueId = $tcDevice ? $tcDevice->uniqueid : $deviceId;
                        $attributes = $tcDevice && $tcDevice->attributes ? $tcDevice->attributes : [];
                        if (is_string($attributes)) $attributes = json_decode($attributes, true);

                        $vehicleName = $tcDevice->name ?? '';
            $vehicleNoRaw = $attributes['vehicleNo'] ?? null;

            if ($vehicleNoRaw) {
                            $vehicleNo = "{$vehicleNoRaw} - {$vehicleName}";
                        } else {
                            $vehicleNo = $vehicleName;
                        }
                    } catch (\Throwable $ignore) {}
                }

                return response()->json([
                    'summary' => [
                        'vehicleIdDisplay' => $vehicleNo,
                        'deviceId' => $uniqueId,
                        'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                        'totalDays' => $totalDays
                    ],
                    'rows' => []
                ], 200);
            } catch (\Throwable $inner) {
                return response()->json(['message' => 'Failed to fetch report data.'], 500);
            }
        }
    }

    public function utilisationDb(Request $request)
    {
        set_time_limit(300);
        try {
            $request->validate([
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'device_ids' => 'required|array|min:1',
                'type' => 'sometimes|string|in:Movement,Engine Hours',
            ]);
            $from = \Carbon\Carbon::parse($request->from_date);
            $to = \Carbon\Carbon::parse($request->to_date);
            if ($from->diffInDays($to) > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }
            $deviceId = $request->device_ids[0];
            return $this->reportService->fetchUtilisationReportDb($request, $deviceId);
        } catch (\Throwable $e) {
            Log::error('utilisationDb failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch report data.'], 500);
        }
    }
    public function deviceOptions(Request $request)
    {
        $user = $request->user();

        if ($request->boolean('mine')) {
             $query = Devices::accessibleByUser($user);
             $query->whereHas('users', function($q) use ($user) {
                 $q->where('users.id', $user->id);
             });
        } else {
             $query = Devices::accessibleByUser($user);
        }

        $query->with(['tcDevice']);

        // Allow seeing soft-deleted devices if requesting all, to match index listing
        if ($request->boolean('includeAll') || $request->boolean('all')) {
            $query->withTrashed();
        }

        $list = $query->get();
        // Include all devices when explicitly requested; otherwise default to devices with current position
        $includeAll = $request->boolean('includeAll') || $request->boolean('all');
        $filtered = $includeAll ? $list : $list->filter(function ($d) {
            $tc = $d->tcDevice;
            return $tc && (int)($tc->positionid ?? 0) > 0;
        });
        if (!$includeAll && ($filtered->count() === 0)) {
            $filtered = $list;
        }

        $options = $filtered->map(function ($d) {
            $tc = $d->tcDevice;
            $unique = data_get($tc, 'uniqueId', data_get($tc, 'uniqueid', data_get($d, 'uniqueid', data_get($d, 'uniqueId', ''))));
            $name = data_get($tc, 'name', data_get($d, 'name', ''));
            $idFallback = (int) data_get($d, 'device_id');
            $tcId = (int) data_get($tc, 'id');
            $labelBase = trim(($unique ? ($unique . ' - ') : '') . $name);
            $label = $labelBase !== '' ? $labelBase : ('Device #' . ($idFallback ?: $tcId));
            return [
                'id' => $tcId ?: $idFallback,
                'deviceId' => $idFallback,
                'name' => $name,
                'uniqueId' => $unique,
                'label' => $label,
            ];
        })->values();

        return response()->json(['options' => $options]);
    }

    public function groupOptions(Request $request)
    {
        $user = $request->user();

        $query = Devices::accessibleByUser($user);
        $query->with('tcDevice');
        $devices = $query->get();

        $groupIds = $devices->pluck('tcDevice.groupid')
            ->filter()
            ->unique()
            ->values();

        if ($groupIds->isEmpty()) {
            return response()->json(['options' => []]);
        }

        $groups = TcGroup::whereIn('id', $groupIds)
            ->orderBy('name')
            ->get();

        $options = $groups->map(function($g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
            ];
        });

        return response()->json(['options' => $options]);
    }

    private function getDeviceIds(Request $request)
    {
        if (!$request->has('device_ids') || empty($request->device_ids)) {
            $devices = Devices::accessibleByUser($request->user())->get();
            return $devices->pluck('device_id')->toArray();
        }
        return $request->device_ids;
    }

    public function incidents(Request $request)
    {
        if (!Schema::hasTable('incidents')) {
            return response()->json(['rows' => []]);
        }
        $count = Incident::count();
        if ($count === 0) {
            try {
                $drivers = ['Sophia Martinez','Liam Johnson','Ava Smith','Mason Brown','Isabella Garcia','Noah Wilson','Olivia Taylor','Lucas Anderson','Mia Thomas','Jacob Jackson','Charlotte White','Amelia Harris','William Thompson'];
                $user = $request->user();
                $devices = Devices::accessibleByUser($user)->with('tcDevice')->get();
                $pool = [];
                $n = min(100, max(50, $devices->count()));
                for ($i = 0; $i < $n; $i++) {
                    $v = $devices[$i % max(1, $devices->count())];
                    $tc = $v->tcDevice;
                    $attrs = $tc && $tc->attributes ? (is_string($tc->attributes) ? (json_decode($tc->attributes, true) ?: []) : (is_array($tc->attributes) ? $tc->attributes : [])) : [];
                    $type = trim((string)data_get($attrs, 'type', ''));
                    $model = trim((string)data_get($tc, 'model', ''));
                    $typeModel = trim($type !== '' ? ($type . ' - ' . $model) : $model);
                    $vehicleNo = (string)data_get($attrs, 'vehicleNo', '');
                    $vehicleName = (string)data_get($tc, 'name', '');
                    $vehicleLabel = trim($vehicleNo !== '' ? ($vehicleNo . ' - ' . $vehicleName) : $vehicleName);
                    $dateBase = now()->subDays(rand(0, 30))->setTime(rand(0,23), [0,15,30,45][rand(0,3)], 0);
                    $impact = (clone $dateBase)->addHours(rand(0, 2))->addMinutes(rand(0, 59));
                    $start = (clone $dateBase);
                    $end = (clone $dateBase)->addDays(rand(0, 5));
                    $pool[] = [
                        'device_id' => (int)$v->device_id,
                        'vehicle_label' => $vehicleLabel,
                        'type_model' => $typeModel,
                        'incident_start' => $start,
                        'incident_end' => $end,
                        'impact_time' => $impact,
                        'driver' => $drivers[$i % count($drivers)],
                        'description' => 'This report details a single incident on ' . $impact->format('d/m/Y h:i A'),
                        'remarks' => $i % 3 === 0 ? 'N/A' : 'Reviewed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('incidents')->insert($pool);
            } catch (\Throwable $e) {
                Log::error('seed incidents failed', ['error' => $e->getMessage()]);
            }
        }
        $q = Incident::query();
        if ($request->filled('date')) {
            $day = date('Y-m-d', strtotime($request->date));
            $q->whereDate('impact_time', $day);
        }
        if ($request->filled('vehicle_query')) {
            $q->where('vehicle_label', 'ILIKE', '%' . $request->vehicle_query . '%');
        }
        if ($request->filled('vehicle_id')) {
            $q->where('device_id', (int)$request->vehicle_id);
        }
        $list = $q->orderByDesc('impact_time')->orderByDesc('id')->limit(100)->get();
        $rows = $list->map(function($r) {
            return [
                'deviceId' => (int)($r->device_id ?? 0),
                'incidentId' => (int)$r->id,
                'vehicleId' => (string)($r->vehicle_label ?? ''),
                'typeModel' => (string)($r->type_model ?? ''),
                'incidentStart' => $r->incident_start ? $r->incident_start->format('d-m-Y') : '',
                'incidentEnd' => $r->incident_end ? $r->incident_end->format('d-m-Y') : '',
                'impactTime' => $r->impact_time ? $r->impact_time->format('d-m-Y H:i') : '',
                'driver' => (string)($r->driver ?? 'N/A'),
                'description' => (string)($r->description ?? ''),
                'remarks' => (string)($r->remarks ?? ''),
            ];
        })->values();
        return response()->json(['rows' => $rows]);
    }

    public function exportIncidentsPdf(Request $request)
    {
        if ($request->filled('incident_id')) {
            $single = Incident::find((int)$request->incident_id);
            $rows = [];
            if ($single) {
                $rows[] = [
                    'vehicleId' => (string)($single->vehicle_label ?? ''),
                    'typeModel' => (string)($single->type_model ?? ''),
                    'incidentStart' => $single->incident_start ? $single->incident_start->format('d-m-Y') : '',
                    'incidentEnd' => $single->incident_end ? $single->incident_end->format('d-m-Y') : '',
                    'impactTime' => $single->impact_time ? $single->impact_time->format('d-m-Y H:i') : '',
                    'driver' => (string)($single->driver ?? 'N/A'),
                    'description' => (string)($single->description ?? ''),
                    'remarks' => (string)($single->remarks ?? ''),
                ];
            }
        } else {
            $rows = $this->incidents($request)->getData(true)['rows'] ?? [];
        }
        $pdf = Pdf::loadView('reports.incidents_pdf', ['rows' => $rows, 'date' => $request->date ?? date('Y-m-d')]);
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('Incident_Analysis_Report_' . date('Y-m-d') . '.pdf');
    }

    public function exportIncidentsExcel(Request $request)
    {
        if ($request->filled('incident_id')) {
            $single = Incident::find((int)$request->incident_id);
            $rows = [];
            if ($single) {
                $rows[] = [
                    'vehicleId' => (string)($single->vehicle_label ?? ''),
                    'typeModel' => (string)($single->type_model ?? ''),
                    'incidentStart' => $single->incident_start ? $single->incident_start->format('d-m-Y') : '',
                    'incidentEnd' => $single->incident_end ? $single->incident_end->format('d-m-Y') : '',
                    'impactTime' => $single->impact_time ? $single->impact_time->format('d-m-Y H:i') : '',
                    'driver' => (string)($single->driver ?? 'N/A'),
                    'description' => (string)($single->description ?? ''),
                    'remarks' => (string)($single->remarks ?? ''),
                ];
            }
        } else {
            $rows = $this->incidents($request)->getData(true)['rows'] ?? [];
        }
        $csvHeader = ['Vehicle ID','Type/Model','Incident Start','Incident End','Impact Date/Time','Driver','Description','Remarks'];
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, $csvHeader);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['vehicleId'] ?? '',
                $r['typeModel'] ?? '',
                $r['incidentStart'] ?? '',
                $r['incidentEnd'] ?? '',
                $r['impactTime'] ?? '',
                $r['driver'] ?? '',
                $r['description'] ?? '',
                $r['remarks'] ?? '',
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        $fileName = 'Incident_Analysis_Report_' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function storeIncident(Request $request)
    {
        $data = $request->validate([
            'vehicleId' => 'required|string',
            'driverId' => 'nullable|string',
            'incidentStart' => 'nullable|date',
            'incidentEnd' => 'nullable|date',
            'impactTime' => 'nullable|date',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
            'deviceId' => 'nullable|integer',
            'typeModel' => 'nullable|string',
        ]);
        $rec = Incident::create([
            'device_id' => $data['deviceId'] ?? null,
            'vehicle_label' => $data['vehicleId'],
            'type_model' => $data['typeModel'] ?? null,
            'incident_start' => $data['incidentStart'] ?? null,
            'incident_end' => $data['incidentEnd'] ?? null,
            'impact_time' => $data['impactTime'] ?? $data['incidentStart'] ?? null,
            'driver' => $data['driverId'] ?? null,
            'description' => $data['description'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);
        return response()->json(['message' => 'created', 'id' => $rec->id], 201);
    }
}
