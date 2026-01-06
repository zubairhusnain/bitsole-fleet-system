<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Models\Devices;
use App\Models\TcGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
}
