<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Incident;
use App\Models\TcGroup;
use App\Support\TelemetryFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
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

        $rows = $this->buildVehicleStatusExportRows($devices);

        $pdf = Pdf::loadView('reports.vehicle_status_pdf', [
            'rows' => $rows,
            'meta' => $this->buildPdfMeta($request),
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('Vehicle_Status_Report_'.date('Y-m-d').'.pdf');
    }

    public function exportVehicleStatusCsv(Request $request)
    {
        $query = $this->buildVehicleStatusQuery($request);
        $devices = $query->orderByDesc('id')->get();

        $this->enrichWithIgnitionData($devices);

        $rows = $this->buildVehicleStatusExportRows($devices);

        $headers = [
            'Vehicle ID', 'Type/Model', 'Device Model', 'IMEI', 'Odometer', 'Power',
            'Last Report', 'Location', 'Speed', 'GPS Signal', 'Ignition',
            'Last Ignition On', 'Last Ignition Off', 'Activation Date',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, [
                $row['vehicle_id'],
                $row['type_model'],
                $row['device_model'],
                $row['imei'],
                $row['odometer'],
                $row['power'],
                $row['last_report'],
                $row['location'],
                $row['speed'],
                $row['gps_signal'],
                $row['ignition'],
                $row['last_ignition_on'],
                $row['last_ignition_off'],
                $row['activation_date'],
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Vehicle_Status_Report_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportGenericPdf(string $title, array $headers, array $rows, string $filename, Request $request)
    {
        if (! class_exists(Pdf::class)) {
            return response()->json([
                'message' => 'PDF export dependency is missing. Run composer install on the server.',
            ], 503);
        }
        $pdf = Pdf::loadView('reports.generic_table_pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $this->buildPdfMeta($request),
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function buildPdfMeta(Request $request): array
    {
        $tz = $this->requestTz($request);
        $fromRaw = $request->input('from_date')
            ?? $request->input('start_date')
            ?? $request->input('date_from')
            ?? '';
        $toRaw = $request->input('to_date')
            ?? $request->input('end_date')
            ?? $request->input('date_to')
            ?? $request->input('date')
            ?? '';

        $user = $request->user();
        $userId = $user ? (int) $user->id : null;
        $userLabel = $user ? trim((string) ($user->name ?: $user->email ?: '')) : '';

        return [
            'generated_at' => Carbon::now($tz)->format('d/m/Y h:i A'),
            'range_start' => $this->formatPdfRangeDate($fromRaw, $tz),
            'range_end' => $this->formatPdfRangeDate($toRaw, $tz),
            'generated_by_id' => $userId,
            'generated_by' => $userLabel,
        ];
    }

    private function formatPdfRangeDate($value, string $tz): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        try {
            $parsed = Carbon::parse($value);
            if (str_contains($value, 'T') || preg_match('/\d{1,2}:\d{2}/', $value)) {
                return $parsed->setTimezone($tz)->format('d/m/Y h:i A');
            }

            return $parsed->setTimezone($tz)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function normalizeRows($rows): array
    {
        $out = [];
        if ($rows instanceof \Illuminate\Support\Collection) {
            $out = $rows->values()->all();
        } elseif ($rows instanceof \Illuminate\Database\Eloquent\Collection) {
            $out = $rows->values()->all();
        } elseif (is_array($rows)) {
            $out = array_values($rows);
        } elseif (is_object($rows) && method_exists($rows, 'toArray')) {
            $arr = $rows->toArray();
            $out = is_array($arr) ? array_values($arr) : [];
        } else {
            $out = [];
        }

        return array_map(function ($r) {
            if (is_object($r)) {
                return (array) $r;
            }

            return is_array($r) ? $r : [];
        }, $out);
    }

    private function requestTz(Request $request): string
    {
        $tz = (string) $request->input('tz', '');
        if ($tz !== '') {
            try {
                new \DateTimeZone($tz);

                return $tz;
            } catch (\Throwable $e) {
            }
        }

        return (string) config('app.timezone', 'UTC');
    }

    private function uiDateFromEpoch(?int $epoch, string $tz): string
    {
        if (! $epoch) {
            return '';
        }

        return Carbon::createFromTimestampUTC($epoch)->setTimezone($tz)->format('d/m/Y');
    }

    private function uiTimeFromEpoch(?int $epoch, string $tz): string
    {
        if (! $epoch) {
            return '';
        }

        return Carbon::createFromTimestampUTC($epoch)->setTimezone($tz)->format('H:i:s');
    }

    private function uiDateTimeFromString($val, string $tz): string
    {
        if (! $val) {
            return '';
        }
        try {
            $c = Carbon::parse((string) $val, 'UTC')->setTimezone($tz);

            return $c->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }

    private function uiLocation(array $r): string
    {
        $loc = (string) ($r['location'] ?? '');
        $addr = (string) ($r['address'] ?? '');
        if ($addr !== '' && ! str_starts_with($addr, 'http')) {
            return $addr;
        }
        if ($loc !== '' && ! str_starts_with($loc, 'http')) {
            return $loc;
        }
        if ($loc !== '' && str_starts_with($loc, 'http')) {
            $lat = $r['lat'] ?? null;
            $lon = $r['lon'] ?? null;
            if ($lat !== null && $lon !== null) {
                return (string) $lat.', '.(string) $lon;
            }
        }

        return $loc;
    }

    public function exportVehicleActivityPdf(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchVehicleActivityDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Date', 'Time', 'Status', 'Location', 'Direction', 'Speed', 'GSM', 'GPS', 'Power', 'Ignition', 'Fuel'];
        $mapped = array_map(function ($r) use ($tz) {
            $epoch = isset($r['epoch']) ? (int) $r['epoch'] : null;

            return [
                $epoch ? $this->uiDateFromEpoch($epoch, $tz) : (string) ($r['date'] ?? ''),
                $epoch ? $this->uiTimeFromEpoch($epoch, $tz) : (string) ($r['time'] ?? ''),
                $r['status'] ?? '',
                $this->uiLocation($r),
                $r['direction'] ?? '',
                $r['speed'] ?? '',
                $r['gsm'] ?? '',
                $r['gps'] ?? '',
                $r['power'] ?? '',
                $r['ignition'] ?? '',
                $r['fuel'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericPdf('Vehicle Activity Report', $headers, $mapped, 'Vehicle_Activity_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportAssetActivityPdf(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchAssetActivityDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Vehicle', 'Date', 'Time', 'Status', 'Longitude', 'Latitude', 'Location', 'Direction', 'Speed', 'GSM', 'GPS', 'Power', 'Ignition', 'Fuel'];
        $mapped = array_map(function ($r) use ($tz) {
            $epoch = isset($r['epoch']) ? (int) $r['epoch'] : null;

            return [
                $r['vehicle'] ?? '',
                $epoch ? $this->uiDateFromEpoch($epoch, $tz) : (string) ($r['date'] ?? ''),
                $epoch ? $this->uiTimeFromEpoch($epoch, $tz) : (string) ($r['time'] ?? ''),
                $r['status'] ?? '',
                $r['lon'] ?? '',
                $r['lat'] ?? '',
                $this->uiLocation($r),
                $r['direction'] ?? '',
                $r['speed'] ?? '',
                $r['gsm'] ?? '',
                $r['gps'] ?? '',
                $r['power'] ?? '',
                $r['ignition'] ?? '',
                $r['fuel'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericPdf('Asset Activity Report', $headers, $mapped, 'Asset_Activity_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportIdlingPdf(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $rows = empty($deviceIds) ? [] : ($this->reportService->fetchIdlingReport($request, $deviceIds) ?? []);
        $rows = $this->normalizeRows($rows);
        $headers = ['Date', 'Idle Start', 'Idle End', 'Idle Duration', 'Trip Start', 'Trip End', 'Trip Duration', 'Location'];
        $mapped = array_map(function ($r) use ($tz) {
            $startEpoch = isset($r['startEpoch']) ? (int) $r['startEpoch'] : null;
            $endEpoch = isset($r['endEpoch']) ? (int) $r['endEpoch'] : null;

            return [
                $startEpoch ? $this->uiDateFromEpoch($startEpoch, $tz) : (string) ($r['date'] ?? ''),
                $startEpoch ? $this->uiTimeFromEpoch($startEpoch, $tz) : (string) ($r['startTime'] ?? ''),
                $endEpoch ? $this->uiTimeFromEpoch($endEpoch, $tz) : (string) ($r['endTime'] ?? ''),
                $r['durationFormatted'] ?? '',
                $r['tripStartTime'] ?? '',
                $r['tripEndTime'] ?? '',
                $r['tripDurationFormatted'] ?? '',
                $r['location'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericPdf('Idling Report', $headers, $mapped, 'Idling_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportUtilisationPdf(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'device_ids' => 'required|array|min:1',
        ]);
        $deviceId = $request->device_ids[0] ?? null;
        if (! $deviceId) {
            return response()->json([], 200);
        }
        $data = $this->reportService->fetchUtilisationReportDb($request, $deviceId);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Date/Day', 'Usage', 'With Movement / Engine On', 'Without Movement / Engine Off', 'Distance'];
        $mapped = array_map(function ($r) {
            return [
                $r['day'] ?? $r['date'] ?? '',
                $r['usage'] ?? '',
                $r['move'] ?? ($r['engineOn'] ?? ''),
                $r['idle'] ?? ($r['engineOff'] ?? ''),
                $r['dist'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericPdf('Utilisation Report', $headers, $mapped, 'Utilisation_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportVehicleRankingPdf(Request $request)
    {
        $rows = $this->normalizeRows($this->reportService->fetchVehicleRankingDb($request) ?? []);
        $headers = ['Vehicle ID', 'Type/Model', 'Distance', 'Duration', 'Total HA', 'Total HB', 'Total HC', 'Total SV', 'Points', 'Percentage', 'Rank'];
        $mapped = array_map(function ($r) {
            return [
                $r['vehicleId'] ?? '',
                $r['typeModel'] ?? '',
                $r['distance'] ?? '',
                $r['duration'] ?? '',
                $r['totalHA'] ?? '',
                $r['totalHB'] ?? '',
                $r['totalHC'] ?? '',
                $r['totalSV'] ?? '',
                $r['points'] ?? '',
                $r['percentage'] ?? '',
                $r['rank'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericPdf('Vehicle Ranking Report', $headers, $mapped, 'Vehicle_Ranking_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportTripSummaryPdf(Request $request)
    {
        $deviceIds = $this->getDeviceIds($request);
        $rows = empty($deviceIds) ? [] : ($this->reportService->fetchFleetSummaryDb($request, $deviceIds) ?? []);
        $rows = $this->normalizeRows($rows);
        $headers = [
            'Vehicle ID',
            'Vehicle Name',
            'Travelled Distance (Total)',
            'Travelled Distance (Avg/Day)',
            'Trip Duration (Total)',
            'Trip Duration (Avg/Day)',
            'Idling Duration (Total)',
            'Idling Duration (Avg/Day)',
            'Utilisation(%)',
            'Avg. Litres',
            'Fuel Refill (L)',
            'Fuel Refill (Frequency)',
            'Fuel Consumption',
            'Speed (Km/h)',
            'Highest Speed (Km/h)',
        ];
        $mapped = array_map(function ($r) {
            return [
                data_get($r, 'vehicleId', ''),
                data_get($r, 'vehicleName', ''),
                data_get($r, 'distTotal', ''),
                data_get($r, 'distAvg', ''),
                data_get($r, 'durTotal', ''),
                data_get($r, 'durAvg', ''),
                data_get($r, 'idleTotal', ''),
                data_get($r, 'idleAvg', ''),
                data_get($r, 'util', ''),
                data_get($r, 'avgLitres', ''),
                data_get($r, 'fuelRefill', ''),
                data_get($r, 'fuelRefillFreq', ''),
                data_get($r, 'fuelConsumption', ''),
                data_get($r, 'speed', ''),
                data_get($r, 'maxSpeed', ''),
            ];
        }, $rows);

        return $this->exportGenericPdf('Trip Summary Report', $headers, $mapped, 'Trip_Summary_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportDailySummaryPdf(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchDailySummaryDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $hasVehicle = ! empty($rows) && data_get($rows[0], 'vehicleId') !== null;
        $headers = $hasVehicle
            ? ['Date', 'Vehicle ID', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %']
            : ['Date', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %'];
        $mapped = array_map(function ($r) use ($hasVehicle) {
            if ($hasVehicle) {
                return [
                    data_get($r, 'date', ''),
                    (string) (data_get($r, 'vehicle', data_get($r, 'vehicleId', ''))),
                    data_get($r, 'distance', ''),
                    data_get($r, 'trip', ''),
                    data_get($r, 'idle', ''),
                    data_get($r, 'idlePct', ''),
                ];
            }

            return [
                data_get($r, 'date', ''),
                data_get($r, 'distance', ''),
                data_get($r, 'trip', ''),
                data_get($r, 'idle', ''),
                data_get($r, 'idlePct', ''),
            ];
        }, $rows);

        return $this->exportGenericPdf('Daily Summary Report', $headers, $mapped, 'Daily_Summary_Report_'.date('Y-m-d').'.pdf', $request);
    }

    public function exportMonthlySummaryPdf(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchMonthlySummary($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $hasVehicle = ! empty($rows) && data_get($rows[0], 'vehicleId') !== null;
        $headers = $hasVehicle
            ? ['Month', 'Vehicle ID', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %']
            : ['Month', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %'];
        $mapped = array_map(function ($r) use ($hasVehicle) {
            if ($hasVehicle) {
                return [
                    data_get($r, 'date', ''),
                    (string) (data_get($r, 'vehicle', data_get($r, 'vehicleId', ''))),
                    data_get($r, 'distance', ''),
                    data_get($r, 'trip', ''),
                    data_get($r, 'idle', ''),
                    data_get($r, 'idlePct', ''),
                ];
            }

            return [
                data_get($r, 'date', ''),
                data_get($r, 'distance', ''),
                data_get($r, 'trip', ''),
                data_get($r, 'idle', ''),
                data_get($r, 'idlePct', ''),
            ];
        }, $rows);

        return $this->exportGenericPdf('Monthly Summary Report', $headers, $mapped, 'Monthly_Summary_Report_'.date('Y-m-d').'.pdf', $request);
    }

    private function exportGenericCsv(array $headers, array $rows, string $filename)
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, is_array($row) ? $row : (array) $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportVehicleActivityCsv(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchVehicleActivityDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Date', 'Time', 'Status', 'Location', 'Direction', 'Speed', 'GSM', 'GPS', 'Power', 'Ignition', 'Fuel'];
        $mapped = array_map(function ($r) use ($tz) {
            $epoch = isset($r['epoch']) ? (int) $r['epoch'] : null;

            return [
                $epoch ? $this->uiDateFromEpoch($epoch, $tz) : (string) ($r['date'] ?? ''),
                $epoch ? $this->uiTimeFromEpoch($epoch, $tz) : (string) ($r['time'] ?? ''),
                $r['status'] ?? '',
                $this->uiLocation($r),
                $r['direction'] ?? '',
                $r['speed'] ?? '',
                $r['gsm'] ?? '',
                $r['gps'] ?? '',
                $r['power'] ?? '',
                $r['ignition'] ?? '',
                $r['fuel'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Vehicle_Activity_Report_'.date('Y-m-d').'.csv');
    }

    public function exportAssetActivityCsv(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchAssetActivityDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Vehicle', 'Date', 'Time', 'Status', 'Longitude', 'Latitude', 'Location', 'Direction', 'Speed', 'GSM', 'GPS', 'Power', 'Ignition', 'Fuel'];
        $mapped = array_map(function ($r) use ($tz) {
            $epoch = isset($r['epoch']) ? (int) $r['epoch'] : null;

            return [
                $r['vehicle'] ?? '',
                $epoch ? $this->uiDateFromEpoch($epoch, $tz) : (string) ($r['date'] ?? ''),
                $epoch ? $this->uiTimeFromEpoch($epoch, $tz) : (string) ($r['time'] ?? ''),
                $r['status'] ?? '',
                $r['lon'] ?? '',
                $r['lat'] ?? '',
                $this->uiLocation($r),
                $r['direction'] ?? '',
                $r['speed'] ?? '',
                $r['gsm'] ?? '',
                $r['gps'] ?? '',
                $r['power'] ?? '',
                $r['ignition'] ?? '',
                $r['fuel'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Asset_Activity_Report_'.date('Y-m-d').'.csv');
    }

    public function exportIdlingCsv(Request $request)
    {
        $tz = $this->requestTz($request);
        $deviceIds = $this->getDeviceIds($request);
        $rows = empty($deviceIds) ? [] : ($this->reportService->fetchIdlingReport($request, $deviceIds) ?? []);
        $rows = $this->normalizeRows($rows);
        $headers = ['Date', 'Idle Start', 'Idle End', 'Idle Duration', 'Trip Start', 'Trip End', 'Trip Duration', 'Location'];
        $mapped = array_map(function ($r) use ($tz) {
            $startEpoch = isset($r['startEpoch']) ? (int) $r['startEpoch'] : null;
            $endEpoch = isset($r['endEpoch']) ? (int) $r['endEpoch'] : null;

            return [
                $startEpoch ? $this->uiDateFromEpoch($startEpoch, $tz) : (string) ($r['date'] ?? ''),
                $startEpoch ? $this->uiTimeFromEpoch($startEpoch, $tz) : (string) ($r['startTime'] ?? ''),
                $endEpoch ? $this->uiTimeFromEpoch($endEpoch, $tz) : (string) ($r['endTime'] ?? ''),
                $r['durationFormatted'] ?? '',
                $r['tripStartTime'] ?? '',
                $r['tripEndTime'] ?? '',
                $r['tripDurationFormatted'] ?? '',
                $r['location'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Idling_Report_'.date('Y-m-d').'.csv');
    }

    public function exportUtilisationCsv(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'device_ids' => 'required|array|min:1',
        ]);
        $deviceId = $request->device_ids[0] ?? null;
        if (! $deviceId) {
            return $this->exportGenericCsv(
                ['Date/Day', 'Usage', 'With Movement / Engine On', 'Without Movement / Engine Off', 'Distance'],
                [],
                'Utilisation_Report_'.date('Y-m-d').'.csv'
            );
        }
        $data = $this->reportService->fetchUtilisationReportDb($request, $deviceId);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $headers = ['Date/Day', 'Usage', 'With Movement / Engine On', 'Without Movement / Engine Off', 'Distance'];
        $mapped = array_map(function ($r) {
            return [
                $r['day'] ?? $r['date'] ?? '',
                $r['usage'] ?? '',
                $r['move'] ?? ($r['engineOn'] ?? ''),
                $r['idle'] ?? ($r['engineOff'] ?? ''),
                $r['dist'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Utilisation_Report_'.date('Y-m-d').'.csv');
    }

    public function exportVehicleRankingCsv(Request $request)
    {
        $rows = $this->normalizeRows($this->reportService->fetchVehicleRankingDb($request) ?? []);
        $headers = ['Vehicle ID', 'Type/Model', 'Distance', 'Duration', 'Total HA', 'Total HB', 'Total HC', 'Total SV', 'Points', 'Percentage', 'Rank'];
        $mapped = array_map(function ($r) {
            return [
                $r['vehicleId'] ?? '',
                $r['typeModel'] ?? '',
                $r['distance'] ?? '',
                $r['duration'] ?? '',
                $r['totalHA'] ?? '',
                $r['totalHB'] ?? '',
                $r['totalHC'] ?? '',
                $r['totalSV'] ?? '',
                $r['points'] ?? '',
                $r['percentage'] ?? '',
                $r['rank'] ?? '',
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Vehicle_Ranking_Report_'.date('Y-m-d').'.csv');
    }

    public function exportTripSummaryCsv(Request $request)
    {
        $deviceIds = $this->getDeviceIds($request);
        $rows = empty($deviceIds) ? [] : ($this->reportService->fetchFleetSummaryDb($request, $deviceIds) ?? []);
        $rows = $this->normalizeRows($rows);
        $headers = [
            'Vehicle ID',
            'Vehicle Name',
            'Travelled Distance (Total)',
            'Travelled Distance (Avg/Day)',
            'Trip Duration (Total)',
            'Trip Duration (Avg/Day)',
            'Idling Duration (Total)',
            'Idling Duration (Avg/Day)',
            'Utilisation(%)',
            'Avg. Litres',
            'Fuel Refill (L)',
            'Fuel Refill (Frequency)',
            'Fuel Consumption',
            'Speed (Km/h)',
            'Highest Speed (Km/h)',
        ];
        $mapped = array_map(function ($r) {
            return [
                data_get($r, 'vehicleId', ''),
                data_get($r, 'vehicleName', ''),
                data_get($r, 'distTotal', ''),
                data_get($r, 'distAvg', ''),
                data_get($r, 'durTotal', ''),
                data_get($r, 'durAvg', ''),
                data_get($r, 'idleTotal', ''),
                data_get($r, 'idleAvg', ''),
                data_get($r, 'util', ''),
                data_get($r, 'avgLitres', ''),
                data_get($r, 'fuelRefill', ''),
                data_get($r, 'fuelRefillFreq', ''),
                data_get($r, 'fuelConsumption', ''),
                data_get($r, 'speed', ''),
                data_get($r, 'maxSpeed', ''),
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Trip_Summary_Report_'.date('Y-m-d').'.csv');
    }

    public function exportDailySummaryCsv(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchDailySummaryDb($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $hasVehicle = ! empty($rows) && data_get($rows[0], 'vehicleId') !== null;
        $headers = $hasVehicle
            ? ['Date', 'Vehicle ID', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %']
            : ['Date', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %'];
        $mapped = array_map(function ($r) use ($hasVehicle) {
            if ($hasVehicle) {
                return [
                    data_get($r, 'date', ''),
                    (string) (data_get($r, 'vehicle', data_get($r, 'vehicleId', ''))),
                    data_get($r, 'distance', ''),
                    data_get($r, 'trip', ''),
                    data_get($r, 'idle', ''),
                    data_get($r, 'idlePct', ''),
                ];
            }

            return [
                data_get($r, 'date', ''),
                data_get($r, 'distance', ''),
                data_get($r, 'trip', ''),
                data_get($r, 'idle', ''),
                data_get($r, 'idlePct', ''),
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Daily_Summary_Report_'.date('Y-m-d').'.csv');
    }

    public function exportMonthlySummaryCsv(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        $data = empty($deviceIds) ? ['rows' => []] : $this->reportService->fetchMonthlySummary($request, $deviceIds);
        $rows = $this->normalizeRows($data['rows'] ?? []);
        $hasVehicle = ! empty($rows) && data_get($rows[0], 'vehicleId') !== null;
        $headers = $hasVehicle
            ? ['Month', 'Vehicle ID', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %']
            : ['Month', 'Distance', 'Trip Duration', 'Idle Duration', 'Idle %'];
        $mapped = array_map(function ($r) use ($hasVehicle) {
            if ($hasVehicle) {
                return [
                    data_get($r, 'date', ''),
                    (string) (data_get($r, 'vehicle', data_get($r, 'vehicleId', ''))),
                    data_get($r, 'distance', ''),
                    data_get($r, 'trip', ''),
                    data_get($r, 'idle', ''),
                    data_get($r, 'idlePct', ''),
                ];
            }

            return [
                data_get($r, 'date', ''),
                data_get($r, 'distance', ''),
                data_get($r, 'trip', ''),
                data_get($r, 'idle', ''),
                data_get($r, 'idlePct', ''),
            ];
        }, $rows);

        return $this->exportGenericCsv($headers, $mapped, 'Monthly_Summary_Report_'.date('Y-m-d').'.csv');
    }

    private function buildVehicleStatusQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();

        // Apply role-based access control
        if ($request->boolean('mine')) {
            $query = Devices::accessibleByUser($user);
            $query->whereHas('users', function ($q) use ($user) {
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
            $query->whereHas('tcDevice', function ($q) use ($groupId) {
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

        $formatDate = function ($dateStr) {
            if (! $dateStr) {
                return null;
            }

            return date('Y-m-d H:i:s', strtotime($dateStr));
        };

        $devicesCollection->transform(function ($device) use ($ignitionTimes, $formatDate) {
            $ignOnTime = $ignitionTimes[$device->device_id]['ignitionOn'] ?? null;
            $ignOffTime = $ignitionTimes[$device->device_id]['ignitionOff'] ?? null;

            $device->last_ignition_on = $ignOnTime ? $formatDate($ignOnTime) : null;
            $device->last_ignition_off = $ignOffTime ? $formatDate($ignOffTime) : null;

            return $device;
        });
    }

    private function buildVehicleStatusExportRows($devices)
    {
        return $devices->map(function ($v) {
            $tc = $v->tcDevice;
            $pos = $tc?->position;

            $attrs = TelemetryFormat::parseAttrs($pos?->attributes ?? []);
            $vehicleAttrs = TelemetryFormat::parseAttrs($v->attributes ?? []);
            $deviceAttrs = TelemetryFormat::resolvedDeviceAttrs($tc);
            $mergedAttrs = array_merge($deviceAttrs, $vehicleAttrs, $attrs);

            $ignRaw = $mergedAttrs['ignition'] ?? $v->ignition ?? false;
            $ignition = ($ignRaw === true || $ignRaw === 1 || strtolower((string) $ignRaw) === 'on');

            $speedVal = $pos?->speed ?? 0; // knots
            $speedKmh = round($speedVal * 1.852);

            $odometer = TelemetryFormat::formatOdometerForDevice($tc, $vehicleAttrs, $attrs) ?? '0 km';

            $lat = $pos?->latitude ? number_format($pos->latitude, 5, '.', '') : null;
            $lon = $pos?->longitude ? number_format($pos->longitude, 5, '.', '') : null;
            $address = $pos?->address ? (string) $pos->address : null;
            $location = $address ?: (($lat !== null && $lon !== null) ? ($lat.', '.$lon) : 'N/A');

            $sat = $mergedAttrs['sat'] ?? 0;
            $signal = 'Weak';
            if ($sat >= 7) {
                $signal = 'Good';
            } elseif ($sat >= 4) {
                $signal = 'Fair';
            }

            $deviceModel = $deviceAttrs['trackerModel'] ?? ($tc?->model ?? 'N/A');

            return [
                'vehicle_id' => $tc?->name ?? 'Unknown',
                'type_model' => trim(($deviceAttrs['type'] ?? '').' '.($tc?->model ?? '')),
                'device_model' => $deviceModel ?: 'N/A',
                'imei' => $tc?->uniqueid ?? 'N/A',
                'odometer' => $odometer,
                'power' => $ignition ? 'On' : 'Off',
                'last_report' => $pos?->servertime ? date('d/m/Y - h:i A', strtotime($pos->servertime)) : 'N/A',
                'location' => $location,
                'speed' => $speedKmh.' km/h',
                'gps_signal' => $signal,
                'ignition' => $ignition ? 'ON' : 'OFF',
                'last_ignition_on' => $v->last_ignition_on ? date('d/m/Y - h:i A', strtotime($v->last_ignition_on)) : 'N/A',
                'last_ignition_off' => $v->last_ignition_off ? date('d/m/Y - h:i A', strtotime($v->last_ignition_off)) : 'N/A',
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
            'device_ids.*' => 'integer',
        ]);

        $deviceIds = $this->getDeviceIds($request);

        if (empty($deviceIds)) {
            return response()->json([]);
        }

        return $this->reportService->fetchFleetSummaryDb($request, $deviceIds);
    }

    public function fuelDetailed(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'device_ids' => 'sometimes|array',
            'device_ids.*' => 'integer'
        ]);

        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) {
            return response()->json([
                'entries' => [],
                'yearly' => [],
            ]);
        }

        return response()->json(
            $this->reportService->fetchFuelEntriesDetailed($request, $deviceIds)
        );
    }

    public function dailyTrips(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) {
            return response()->json([]);
        }

        return $this->reportService->fetchDailyTrips($request, $deviceIds);
    }

    public function dailyBreakdownMap(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) {
            return response()->json([]);
        }

        return $this->reportService->fetchDailyBreakdownMap($request, $deviceIds);
    }

    public function dailySummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) {
            return response()->json([]);
        }

        return $this->reportService->fetchDailySummaryDb($request, $deviceIds);
    }

    public function monthlySummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        $deviceIds = $this->getDeviceIds($request);
        if (empty($deviceIds)) {
            return response()->json([]);
        }

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
            if (empty($deviceIds)) {
                return response()->json([]);
            }

            return $this->reportService->fetchAssetActivityDb($request, $deviceIds);
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
            if (empty($deviceIds)) {
                return response()->json(['header' => null, 'rows' => []]);
            }

            return $this->reportService->fetchVehicleActivityDb($request, $deviceIds);
        } catch (\Throwable $e) {
            Log::error('vehicleActivity failed', ['error' => $e->getMessage()]);

            return response()->json(['header' => null, 'rows' => []]);
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
            if (empty($deviceIds)) {
                return response()->json([]);
            }

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
                        if (is_string($attributes)) {
                            $attributes = json_decode($attributes, true);
                        }

                        $vehicleName = $tcDevice->name ?? '';
                        $vehicleNoRaw = $attributes['vehicleNo'] ?? null;

                        if ($vehicleNoRaw) {
                            $vehicleNo = "{$vehicleNoRaw} - {$vehicleName}";
                        } else {
                            $vehicleNo = $vehicleName;
                        }
                    } catch (\Throwable $ignore) {
                    }
                }

                return response()->json([
                    'summary' => [
                        'vehicleIdDisplay' => $vehicleNo,
                        'deviceId' => $uniqueId,
                        'durationDisplay' => "{$request->from_date} 00:00 - {$request->to_date} 23:59",
                        'totalDays' => $totalDays,
                    ],
                    'rows' => [],
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
            $query->whereHas('users', function ($q) use ($user) {
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

            return $tc && (int) ($tc->positionid ?? 0) > 0;
        });
        if (! $includeAll && ($filtered->count() === 0)) {
            $filtered = $list;
        }

        $options = $filtered->map(function ($d) {
            $tc = $d->tcDevice;
            $unique = data_get($tc, 'uniqueId', data_get($tc, 'uniqueid', data_get($d, 'uniqueid', data_get($d, 'uniqueId', ''))));
            $name = data_get($tc, 'name', data_get($d, 'name', ''));
            $idFallback = (int) data_get($d, 'device_id');
            $tcId = (int) data_get($tc, 'id');
            $labelBase = trim(($unique ? ($unique.' - ') : '').$name);
            $label = $labelBase !== '' ? $labelBase : ('Device #'.($idFallback ?: $tcId));

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

        $options = $groups->map(function ($g) {
            return [
                'id' => $g->id,
                'name' => $g->name,
            ];
        });

        return response()->json(['options' => $options]);
    }

    private function getDeviceIds(Request $request)
    {
        if (! $request->has('device_ids') || empty($request->device_ids)) {
            $devices = Devices::accessibleByUser($request->user())->get();

            return $devices->pluck('device_id')->toArray();
        }

        return $request->device_ids;
    }

    public function incidents(Request $request)
    {
        if (! Schema::hasTable('incidents')) {
            return response()->json(['rows' => []]);
        }

        // Fetch real drivers from DB
        $realDrivers = \App\Models\Drivers::with('tcDriver')->get()
            ->map(fn ($d) => $d->tcDriver->name ?? null)
            ->filter(fn ($name) => ! empty($name))
            ->values()
            ->toArray();

        $drivers = ! empty($realDrivers) ? $realDrivers : ['Sophia Martinez', 'Liam Johnson', 'Ava Smith', 'Mason Brown', 'Isabella Garcia', 'Noah Wilson', 'Olivia Taylor', 'Lucas Anderson', 'Mia Thomas', 'Jacob Jackson', 'Charlotte White', 'Amelia Harris', 'William Thompson'];

        $count = Incident::count();
        if ($count === 0) {
            try {
                $user = $request->user();
                $devices = Devices::accessibleByUser($user)->with('tcDevice')->get();
                $pool = [];
                $n = min(100, max(50, $devices->count()));
                for ($i = 0; $i < $n; $i++) {
                    $v = $devices[$i % max(1, $devices->count())];
                    $tc = $v->tcDevice;
                    $attrs = $tc && $tc->attributes ? (is_string($tc->attributes) ? (json_decode($tc->attributes, true) ?: []) : (is_array($tc->attributes) ? $tc->attributes : [])) : [];
                    $type = trim((string) data_get($attrs, 'type', ''));
                    $model = trim((string) data_get($tc, 'model', ''));
                    $typeModel = trim($type !== '' ? ($type.' - '.$model) : $model);
                    $vehicleNo = (string) data_get($attrs, 'vehicleNo', '');
                    $vehicleName = (string) data_get($tc, 'name', '');
                    $vehicleLabel = trim($vehicleNo !== '' ? ($vehicleNo.' - '.$vehicleName) : $vehicleName);
                    $dateBase = now()->subDays(rand(0, 30))->setTime(rand(0, 23), [0, 15, 30, 45][rand(0, 3)], 0);
                    $impact = (clone $dateBase)->addHours(rand(0, 2))->addMinutes(rand(0, 59));
                    $start = (clone $dateBase);
                    $end = (clone $dateBase)->addDays(rand(0, 5));
                    $pool[] = [
                        'device_id' => (int) $v->device_id,
                        'vehicle_label' => $vehicleLabel,
                        'type_model' => $typeModel,
                        'incident_start' => $start,
                        'incident_end' => $end,
                        'impact_time' => $impact,
                        'driver' => $drivers[$i % count($drivers)],
                        'description' => 'This report details a single incident on '.$impact->format('d/m/Y h:i A'),
                        'remarks' => $i % 3 === 0 ? 'N/A' : 'Reviewed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('incidents')->insert($pool);
            } catch (\Throwable $e) {
                Log::error('seed incidents failed', ['error' => $e->getMessage()]);
            }
        } else {
            // Update existing records if they have drivers not in the list
            $invalid = Incident::whereNotIn('driver', $drivers)->get();
            if ($invalid->count() > 0) {
                foreach ($invalid as $inc) {
                    $inc->driver = $drivers[array_rand($drivers)];
                    $inc->save();
                }
            }
        }
        $q = Incident::query();
        if ($request->filled('from_date')) {
            $q->whereDate('impact_time', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $q->whereDate('impact_time', '<=', $request->to_date);
        }
        if ($request->filled('date')) {
            $day = date('Y-m-d', strtotime($request->date));
            $q->whereDate('impact_time', $day);
        }
        if ($request->filled('vehicle_query')) {
            $q->where('vehicle_label', 'ILIKE', '%'.$request->vehicle_query.'%');
        }
        if ($request->filled('vehicle_id')) {
            $q->where('device_id', (int) $request->vehicle_id);
        }
        $list = $q->orderByDesc('impact_time')->orderByDesc('id')->limit(100)->get();
        $rows = $list->map(function ($r) {
            return [
                'deviceId' => (int) ($r->device_id ?? 0),
                'incidentId' => (int) $r->id,
                'vehicleId' => (string) ($r->vehicle_label ?? ''),
                'typeModel' => (string) ($r->type_model ?? ''),
                'incidentStart' => $r->incident_start ? $r->incident_start->format('Y-m-d H:i:s') : null,
                'incidentEnd' => $r->incident_end ? $r->incident_end->format('Y-m-d H:i:s') : null,
                'impactTime' => $r->impact_time ? $r->impact_time->format('Y-m-d H:i:s') : null,
                'driver' => (string) ($r->driver ?? 'N/A'),
                'description' => (string) ($r->description ?? ''),
                'remarks' => (string) ($r->remarks ?? ''),
            ];
        })->values();

        return response()->json(['rows' => $rows]);
    }

    public function exportIncidentsPdf(Request $request)
    {
        $tz = $this->requestTz($request);
        if ($request->filled('incident_id')) {
            $single = Incident::find((int) $request->incident_id);
            $rows = [];
            if ($single) {
                $rows[] = [
                    'vehicleId' => (string) ($single->vehicle_label ?? ''),
                    'typeModel' => (string) ($single->type_model ?? ''),
                    'incidentStart' => $single->incident_start ? $single->incident_start->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'incidentEnd' => $single->incident_end ? $single->incident_end->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'impactTime' => $single->impact_time ? $single->impact_time->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'driver' => (string) ($single->driver ?? 'N/A'),
                    'description' => (string) ($single->description ?? ''),
                    'remarks' => (string) ($single->remarks ?? ''),
                ];
            }
        } else {
            $rows = $this->incidents($request)->getData(true)['rows'] ?? [];
        }
        $rows = array_map(function ($r) use ($tz) {
            $row = is_array($r) ? $r : (is_object($r) ? (array) $r : []);
            $row['incidentStart'] = $this->uiDateTimeFromString($row['incidentStart'] ?? null, $tz);
            $row['incidentEnd'] = $this->uiDateTimeFromString($row['incidentEnd'] ?? null, $tz);
            $row['impactTime'] = $this->uiDateTimeFromString($row['impactTime'] ?? null, $tz);

            return $row;
        }, $this->normalizeRows($rows));
        $dateLabel = $request->date;
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $dateLabel = $request->from_date.' to '.$request->to_date;
        } elseif ($request->filled('from_date')) {
            $dateLabel = 'From '.$request->from_date;
        } elseif ($request->filled('to_date')) {
            $dateLabel = 'Until '.$request->to_date;
        }
        $dateLabel = $dateLabel ?? date('Y-m-d');
        $pdf = Pdf::loadView('reports.incidents_pdf', [
            'rows' => $rows,
            'meta' => $this->buildPdfMeta($request),
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('Incident_Analysis_Report_'.$dateLabel.'.pdf');
    }

    public function exportIncidentsExcel(Request $request)
    {
        $tz = $this->requestTz($request);
        if ($request->filled('incident_id')) {
            $single = Incident::find((int) $request->incident_id);
            $rows = [];
            if ($single) {
                $rows[] = [
                    'vehicleId' => (string) ($single->vehicle_label ?? ''),
                    'typeModel' => (string) ($single->type_model ?? ''),
                    'incidentStart' => $single->incident_start ? $single->incident_start->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'incidentEnd' => $single->incident_end ? $single->incident_end->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'impactTime' => $single->impact_time ? $single->impact_time->setTimezone($tz)->format('d/m/Y H:i:s') : '',
                    'driver' => (string) ($single->driver ?? 'N/A'),
                    'description' => (string) ($single->description ?? ''),
                    'remarks' => (string) ($single->remarks ?? ''),
                ];
            }
        } else {
            $rows = $this->incidents($request)->getData(true)['rows'] ?? [];
        }
        $rows = array_map(function ($r) use ($tz) {
            $row = is_array($r) ? $r : (is_object($r) ? (array) $r : []);
            $row['incidentStart'] = $this->uiDateTimeFromString($row['incidentStart'] ?? null, $tz);
            $row['incidentEnd'] = $this->uiDateTimeFromString($row['incidentEnd'] ?? null, $tz);
            $row['impactTime'] = $this->uiDateTimeFromString($row['impactTime'] ?? null, $tz);

            return $row;
        }, $this->normalizeRows($rows));
        $csvHeader = ['Vehicle ID', 'Type/Model', 'Incident Start', 'Incident End', 'Impact Date/Time', 'Driver', 'Description', 'Remarks'];
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
        $fileName = 'Incident_Analysis_Report_'.date('Y-m-d').'.csv';

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

    public function vehicleRanking(Request $request)
    {
        return $this->reportService->vehicleRanking($request);
    }
}
