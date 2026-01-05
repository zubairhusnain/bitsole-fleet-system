<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Models\Devices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        // Eager load tcDevice and its current position
        $query->with(['tcDevice.position', 'manager']);

        // Pagination or fetch all
        $perPage = $request->input('per_page', 25);
        
        $devices = $query->orderByDesc('id')->paginate($perPage);

        // Fetch last ignition events for these devices
        $deviceIds = $devices->pluck('device_id')->unique()->values()->all();

        if (!empty($deviceIds)) {
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
                return date('d/m/Y-H:i', strtotime($dateStr));
            };

            // Enrich the devices collection
            $devices->getCollection()->transform(function ($device) use ($ignitionTimes, $formatDate) {
                $ignOnTime = $ignitionTimes[$device->device_id]['ignitionOn'] ?? null;
                $ignOffTime = $ignitionTimes[$device->device_id]['ignitionOff'] ?? null;

                $device->last_ignition_on = $ignOnTime ? $formatDate($ignOnTime) : null;
                $device->last_ignition_off = $ignOffTime ? $formatDate($ignOffTime) : null;

                return $device;
            });
        }
        
        return $devices;
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
            if ($from->diffInDays($to) > 31) {
                return response()->json(['message' => 'Date range cannot exceed 31 days.'], 422);
            }

            $deviceIds = $this->getDeviceIds($request);
            if (empty($deviceIds)) return response()->json([]);
            return $this->reportService->fetchAssetActivity($request, $deviceIds);
        } catch (\Throwable $e) {
            Log::error('assetActivity failed', ['error' => $e->getMessage()]);
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

    private function getDeviceIds(Request $request)
    {
        if (!$request->has('device_ids') || empty($request->device_ids)) {
            $devices = Devices::accessibleByUser($request->user())->get();
            return $devices->pluck('device_id')->toArray();
        }
        return $request->device_ids;
    }
}
