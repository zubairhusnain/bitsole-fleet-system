<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\DriverAssignmentController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FuelController;
use App\Http\Controllers\LiveTrackingController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemActivityLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\ZoneController;
use App\Http\Middleware\ApiFleetNotificationAccess;
use App\Http\Middleware\ApiModulePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fleet API routes — mirror of web.php (/web/*) for mobile Sanctum clients.
| Prefix /api is applied automatically by Laravel.
|--------------------------------------------------------------------------
*/

$sanctumFleet = ['auth:sanctum', ApiModulePermission::class];
$sanctumAuth = 'auth:sanctum';

Route::middleware($sanctumFleet)->prefix('admin')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        return response()->json([
            'message' => 'Welcome to admin dashboard',
            'user' => $request->user(),
        ]);
    });

    Route::get('/profile', function (Request $request) {
        return response()->json([
            'profile' => $request->user(),
        ]);
    });
});

Route::middleware([$sanctumAuth])->prefix('system-logs')->group(function () {
    Route::get('/', [SystemActivityLogController::class, 'index']);
    Route::get('/filters-data', [SystemActivityLogController::class, 'getFiltersData']);
});

Route::middleware([$sanctumAuth])->prefix('backups')->group(function () {
    Route::get('/', [BackupController::class, 'index']);
    Route::get('/download', [BackupController::class, 'download']);
    Route::delete('/delete', [BackupController::class, 'delete']);
});

Route::middleware($sanctumFleet)->prefix('monitoring')->group(function () {
    Route::get('/vehicles', [MonitoringController::class, 'index']);
    Route::get('/vehicles/{id}', [MonitoringController::class, 'show']);
    Route::get('/vehicles/{id}/events', [MonitoringController::class, 'getDeviceEvents']);
    Route::post('/vehicles/events/{eventId}/acknowledge', [MonitoringController::class, 'acknowledgeEvent']);
    Route::post('/vehicles/{id}/alert-status', [MonitoringController::class, 'updateAlertStatus']);
    Route::get('/zones', [MonitoringController::class, 'zoneSummary']);
    Route::get('/zones/{id}', [MonitoringController::class, 'zoneDetail']);
});

Route::middleware($sanctumFleet)->prefix('vehicles')->group(function () {
    Route::prefix('maintenance')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index']);
        Route::get('/vehicle/options', [MaintenanceController::class, 'vehicleOptions']);
        Route::post('/', [MaintenanceController::class, 'store']);
        Route::put('/{id}', [MaintenanceController::class, 'update']);
        Route::delete('/{id}', [MaintenanceController::class, 'destroy']);
    });

    Route::get('/', [VehicleController::class, 'index']);
    Route::get('/options', [VehicleController::class, 'options']);
    Route::get('/models/options', [VehicleModelController::class, 'options']);
    Route::post('/', [VehicleController::class, 'store']);
    Route::get('/{deviceId}', [VehicleController::class, 'show']);
    Route::get('/{deviceId}/detail', [VehicleController::class, 'detail']);
    Route::get('/{deviceId}/device', [VehicleController::class, 'deviceRaw']);
    Route::get('/{deviceId}/position', [VehicleController::class, 'positionCurrent']);
    Route::get('/{deviceId}/trips', [VehicleController::class, 'trips']);
    Route::get('/{deviceId}/drivers', [VehicleController::class, 'driversList']);
    Route::get('/drivers/options', [VehicleController::class, 'driversOptions']);
    Route::get('/{deviceId}/geofences', [VehicleController::class, 'geofences']);
    Route::get('/geofences/options', [VehicleController::class, 'geofencesOptions']);
    Route::get('/{deviceId}/positions', [VehicleController::class, 'positions']);
    Route::get('/{deviceId}/logs', [VehicleController::class, 'logsRaw']);
    Route::get('/{deviceId}/driver', [VehicleController::class, 'driver']);
    Route::get('/{deviceId}/rating', [VehicleController::class, 'rating']);
    Route::get('/{deviceId}/performance', [VehicleController::class, 'performance']);
    Route::put('/{deviceId}', [VehicleController::class, 'update']);
    Route::patch('/{deviceId}/restore', [VehicleController::class, 'restore']);
    Route::delete('/{deviceId}', [VehicleController::class, 'destroy']);
    Route::post('/{deviceId}/drivers/assign', [VehicleController::class, 'assignDrivers']);
    Route::post('/{deviceId}/drivers/unassign', [VehicleController::class, 'unassignDrivers']);
    Route::post('/{deviceId}/zones/assign', [VehicleController::class, 'assignZones']);
    Route::post('/{deviceId}/zones/unassign', [VehicleController::class, 'unassignZones']);
    Route::get('/{deviceId}/notifications', [VehicleController::class, 'notificationsDevice']);
    Route::post('/{deviceId}/notifications/assign', [VehicleController::class, 'notificationsAssign']);
    Route::get('/{deviceId}/computed-attributes', [VehicleController::class, 'computedAttributes']);
    Route::delete('/{deviceId}/computed-attributes/{attributeId}', [VehicleController::class, 'removeComputedAttribute']);
});

Route::middleware($sanctumFleet)->prefix('drivers')->group(function () {
    Route::prefix('assignments')->group(function () {
        Route::get('/', [DriverAssignmentController::class, 'index']);
        Route::get('/history', [DriverAssignmentController::class, 'history']);
        Route::post('/', [DriverAssignmentController::class, 'store']);
        Route::put('/{id}', [DriverAssignmentController::class, 'update']);
        Route::delete('/{id}', [DriverAssignmentController::class, 'destroy']);
    });

    Route::get('/options', [VehicleController::class, 'options']);
    Route::get('/', [DriverController::class, 'index']);
    Route::get('/{driverId}', [DriverController::class, 'show']);
    Route::post('/', [DriverController::class, 'store']);
    Route::put('/{driverId}', [DriverController::class, 'update']);
    Route::patch('/{driverId}/restore', [DriverController::class, 'restore']);
    Route::delete('/{driverId}', [DriverController::class, 'destroy']);
});

Route::middleware($sanctumFleet)->get('/live/positions/broadcast', [LiveTrackingController::class, 'broadcast']);
Route::middleware($sanctumFleet)->get('/live/positions/current', [LiveTrackingController::class, 'current']);

Route::middleware($sanctumFleet)->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/options', [UserController::class, 'options']);
    Route::get('/device-options', [VehicleController::class, 'options']);
    Route::get('/{userId}', [UserController::class, 'show']);
    Route::get('/{userId}/permissions', [UserController::class, 'permissions']);
    Route::put('/{userId}/permissions', [UserController::class, 'updatePermissions']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{userId}', [UserController::class, 'update']);
    Route::patch('/{userId}/restore', [UserController::class, 'restore']);
    Route::delete('/{userId}', [UserController::class, 'destroy']);
});

Route::middleware($sanctumFleet)->prefix('settings')->group(function () {
    Route::get('/vehicle-models', [VehicleModelController::class, 'index']);
    Route::post('/vehicle-models', [VehicleModelController::class, 'store']);
    Route::put('/vehicle-models/{id}', [VehicleModelController::class, 'update']);
    Route::delete('/vehicle-models/{id}', [VehicleModelController::class, 'destroy']);
});

Route::middleware($sanctumFleet)->prefix('zones')->group(function () {
    Route::get('/', [ZoneController::class, 'index']);
    Route::get('/{zoneId}', [ZoneController::class, 'show']);
    Route::post('/', [ZoneController::class, 'store']);
    Route::put('/{zoneId}', [ZoneController::class, 'update']);
    Route::patch('/{zoneId}/restore', [ZoneController::class, 'restore']);
    Route::delete('/{zoneId}', [ZoneController::class, 'destroy']);
});

Route::middleware($sanctumFleet)->prefix('fuel')->group(function () {
    Route::get('/', [FuelController::class, 'index']);
    Route::get('/summary', [FuelController::class, 'summary']);
    Route::get('/vehicles', [FuelController::class, 'vehicleOptions']);
    Route::post('/', [FuelController::class, 'store']);
    Route::get('/{id}', [FuelController::class, 'show']);
    Route::put('/{id}', [FuelController::class, 'update']);
    Route::patch('/{id}/restore', [FuelController::class, 'restore']);
    Route::delete('/{id}', [FuelController::class, 'destroy']);
});

Route::middleware($sanctumFleet)->get('/tracking/geofences', [ZoneController::class, 'geofencesDb']);

Route::middleware($sanctumAuth)->get('/tracking/assign-computed-attributes', function (Request $request) {
    $summary = app(\App\Services\PermissionService::class)->assignComputedAttributesToAllDevices($request);

    return response()->json($summary);
});

Route::middleware([$sanctumAuth, ApiFleetNotificationAccess::class])->prefix('notifications')->group(function () {
    Route::get('/broadcast', [NotificationController::class, 'broadcast']);
    Route::get('/events', [NotificationController::class, 'events']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/mark-read', [NotificationController::class, 'markAllRead']);
    Route::get('/my-device-ids', [NotificationController::class, 'myDeviceIds']);
    Route::delete('/events/{id}', [NotificationController::class, 'destroy']);
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/device/{deviceId}', [NotificationController::class, 'device']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('/assign', [NotificationController::class, 'assign']);
});

Route::middleware($sanctumFleet)->prefix('reports')->group(function () {
    Route::get('/trip-summary', [ReportController::class, 'tripSummary']);
    Route::get('/daily-trips', [ReportController::class, 'dailyTrips']);
    Route::get('/daily-summary', [ReportController::class, 'dailySummary']);
    Route::get('/monthly-summary', [ReportController::class, 'monthlySummary']);
    Route::get('/fuel-detailed', [ReportController::class, 'fuelDetailed']);
    Route::get('/asset-activity', [ReportController::class, 'assetActivity']);
    Route::get('/vehicle-activity', [ReportController::class, 'vehicleActivity']);
    Route::get('/idling', [ReportController::class, 'idling']);
    Route::get('/utilisation', [ReportController::class, 'utilisation']);
    Route::get('/utilisation-db', [ReportController::class, 'utilisationDb']);
    Route::get('/daily-breakdown-map', [ReportController::class, 'dailyBreakdownMap']);
    Route::get('/vehicle-status', [ReportController::class, 'vehicleStatus']);
    Route::get('/vehicle-status/export-pdf', [ReportController::class, 'exportVehicleStatusPdf']);
    Route::get('/device-options', [ReportController::class, 'deviceOptions']);
    Route::get('/group-options', [ReportController::class, 'groupOptions']);
    Route::get('/incidents', [ReportController::class, 'incidents']);
    Route::post('/incidents', [ReportController::class, 'storeIncident']);
    Route::get('/incidents/export-pdf', [ReportController::class, 'exportIncidentsPdf']);
    Route::get('/incidents/export-excel', [ReportController::class, 'exportIncidentsExcel']);
    Route::get('/vehicle-ranking', [ReportController::class, 'vehicleRanking']);
    Route::get('/effective-fuel', [ReportController::class, 'effectiveFuel']);
    Route::get('/route-playback', [ReportController::class, 'routePlayback']);
});

Route::middleware($sanctumFleet)->prefix('commands')->group(function () {
    Route::post('/send', [CommandController::class, 'send']);
    Route::get('/types', [CommandController::class, 'types']);
    Route::get('/saved', [CommandController::class, 'saved']);
    Route::get('/device-options', [ReportController::class, 'deviceOptions']);
});
