<?php

use App\Models\TcUser;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SystemActivityLogController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
Route::get('/testing', function () {

            try {
            // Raw SQL query to select all columns from the 'tc_users' table
            // We prepend the schema name 'omayer' to ensure the database
            // looks in the correct location for the table.
            $users = TcUser::all();
            // dd($users);
            // Return the fetched data as a JSON response
            return response()->json([
                'status' => 'success',
                'count' => count($users),
                'data' => $users
            ]);

        } catch (\Exception $e) {
            // Handle any database connection or query errors
            return response()->json([
                'status' => 'error',
                'message' => 'Database Query Error: ' . $e->getMessage()
            ], 500);
        }
    return view('welcome');
});

// Simple example web routes
Route::get('/ping', function () {
    return response('pong', 200);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Redirect root to login (SPA will render LoginView at /login)
Route::redirect('/', '/login');

// Serve the Vue SPA at root for all non-API/non-system paths
// Exclude /web/* so those endpoints are handled by controllers
Route::view('/{any}', 'welcome')
    ->where('any', '^(?!web|storage|up|ping|health|testing).*$');
// Provide CSRF token for SPA clients (fallback when meta/cookie not available)
Route::get('/web/csrf-token', function () {
    return response()->json(['csrfToken' => csrf_token()]);
});

// SPA Auth endpoints (session-based)
Route::prefix('/web/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    // Temporarily disable CSRF for local dev login to unblock testing
    Route::post('/login', [AuthController::class, 'login'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::middleware(['auth'])->group(function () {
        Route::post('/impersonate/stop', [AuthController::class, 'stopImpersonate'])
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
        Route::post('/impersonate/{userId}', [AuthController::class, 'impersonate'])
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    });
});

// Auth-protected admin APIs (session-based)
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/admin')->group(function () {
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


// System Activity Logs (Fleet Manager/Admin)
Route::middleware(['auth'])->prefix('/web/system-logs')->group(function () {
    Route::get('/', 'App\Http\Controllers\SystemActivityLogController@index');
    Route::get('/filters-data', 'App\Http\Controllers\SystemActivityLogController@getFiltersData');
});

// Backups (Admin only)
Route::middleware(['auth'])->prefix('/web/backups')->group(function () {
    Route::get('/', [\App\Http\Controllers\BackupController::class, 'index']);
    Route::get('/download', [\App\Http\Controllers\BackupController::class, 'download']);
    Route::delete('/delete', [\App\Http\Controllers\BackupController::class, 'delete']);
});

// Monitoring Routes
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/monitoring')->group(function () {
    Route::get('/vehicles', [\App\Http\Controllers\MonitoringController::class, 'index']);
    Route::get('/vehicles/{id}', [\App\Http\Controllers\MonitoringController::class, 'show']);
    Route::get('/vehicles/{id}/events', [\App\Http\Controllers\MonitoringController::class, 'getDeviceEvents']);
    // Include 'vehicles' in the path so ModulePermission maps to 'monitoring.vehicles'
    Route::post('/vehicles/events/{eventId}/acknowledge', [\App\Http\Controllers\MonitoringController::class, 'acknowledgeEvent']);
    Route::post('/vehicles/{id}/alert-status', [\App\Http\Controllers\MonitoringController::class, 'updateAlertStatus']);
    Route::get('/zones', [\App\Http\Controllers\MonitoringController::class, 'zoneSummary']);
    Route::get('/zones/{id}', [\App\Http\Controllers\MonitoringController::class, 'zoneDetail']);
});

// Auth-protected Vehicles CRUD
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/vehicles')->group(function () {
    // Maintenance CRUD (Nested)
    // IMPORTANT: Must be defined BEFORE /{deviceId} to avoid conflict
    Route::prefix('maintenance')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index']);
        Route::get('/vehicle/options', [MaintenanceController::class, 'vehicleOptions']);
        Route::post('/', [MaintenanceController::class, 'store']);
        Route::put('/{id}', [MaintenanceController::class, 'update']);
        Route::delete('/{id}', [MaintenanceController::class, 'destroy']);
    });

    Route::get('/', [\App\Http\Controllers\VehicleController::class, 'index']);
    Route::get('/options', [\App\Http\Controllers\VehicleController::class, 'options']);
    // Tracker models options for vehicle form (not admin-only)
    Route::get('/models/options', [VehicleModelController::class, 'options']);
    Route::post('/', [\App\Http\Controllers\VehicleController::class, 'store']);
    Route::get('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'show']);
    // Device detail: single payload with latest position and trips
    Route::get('/{deviceId}/detail', [\App\Http\Controllers\VehicleController::class, 'detail']);
    // Split endpoints: device-only, latest position, trips-only, drivers list
    Route::get('/{deviceId}/device', [\App\Http\Controllers\VehicleController::class, 'deviceRaw']);
    Route::get('/{deviceId}/position', [\App\Http\Controllers\VehicleController::class, 'positionCurrent']);
    Route::get('/{deviceId}/trips', [\App\Http\Controllers\VehicleController::class, 'trips']);
    Route::get('/{deviceId}/drivers', [\App\Http\Controllers\VehicleController::class, 'driversList']);
    // Drivers/Zones options under vehicles namespace
    Route::get('/drivers/options', [\App\Http\Controllers\VehicleController::class, 'driversOptions']);
    // Geofences assigned to this vehicle
    Route::get('/{deviceId}/geofences', [\App\Http\Controllers\VehicleController::class, 'geofences']);
    Route::get('/geofences/options', [\App\Http\Controllers\VehicleController::class, 'geofencesOptions']);
    // Positions for map/waypoints (time-window support)
    Route::get('/{deviceId}/positions', [\App\Http\Controllers\VehicleController::class, 'positions']);
    // Raw device logs for Codec8 decoding
    Route::get('/{deviceId}/logs', [\App\Http\Controllers\VehicleController::class, 'logsRaw']);
    // Driver assigned to this vehicle
    Route::get('/{deviceId}/driver', [\App\Http\Controllers\VehicleController::class, 'driver']);
    // Rating metrics derived from reports
    Route::get('/{deviceId}/rating', [\App\Http\Controllers\VehicleController::class, 'rating']);
    // Consolidated performance summary for dashboard (summary/events/maintenance)
    Route::get('/{deviceId}/performance', [\App\Http\Controllers\VehicleController::class, 'performance']);
    Route::put('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'update']);
    // Restore a soft-deleted (blocked) vehicle
    Route::patch('/{deviceId}/restore', [\App\Http\Controllers\VehicleController::class, 'restore']);
    Route::delete('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'destroy']);

    // Assign/unassign drivers
    Route::post('/{deviceId}/drivers/assign', [\App\Http\Controllers\VehicleController::class, 'assignDrivers']);
    Route::post('/{deviceId}/drivers/unassign', [\App\Http\Controllers\VehicleController::class, 'unassignDrivers']);
    // Assign/unassign zones (geofences)
    Route::post('/{deviceId}/zones/assign', [\App\Http\Controllers\VehicleController::class, 'assignZones']);
    Route::post('/{deviceId}/zones/unassign', [\App\Http\Controllers\VehicleController::class, 'unassignZones']);
    // Notifications under vehicles namespace
    Route::get('/{deviceId}/notifications', [\App\Http\Controllers\VehicleController::class, 'notificationsDevice']);
    Route::post('/{deviceId}/notifications/assign', [\App\Http\Controllers\VehicleController::class, 'notificationsAssign']);

    // Computed Attributes (Developer only)
    Route::get('/{deviceId}/computed-attributes', [\App\Http\Controllers\VehicleController::class, 'computedAttributes']);
    Route::delete('/{deviceId}/computed-attributes/{attributeId}', [\App\Http\Controllers\VehicleController::class, 'removeComputedAttribute']);

});



// NEW: Auth-protected Drivers CRUD & assignment
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/drivers')->group(function () {
    // Driver Assignments
    Route::prefix('assignments')->group(function () {
        Route::get('/', [\App\Http\Controllers\DriverAssignmentController::class, 'index']);
        Route::get('/history', [\App\Http\Controllers\DriverAssignmentController::class, 'history']);
        Route::post('/', [\App\Http\Controllers\DriverAssignmentController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\DriverAssignmentController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\DriverAssignmentController::class, 'destroy']);
    });

    Route::get('/options', [\App\Http\Controllers\VehicleController::class, 'options']);
    Route::get('/', [\App\Http\Controllers\DriverController::class, 'index']);
    Route::get('/{driverId}', [\App\Http\Controllers\DriverController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\DriverController::class, 'store']);
    Route::put('/{driverId}', [\App\Http\Controllers\DriverController::class, 'update']);
    // Restore a soft-deleted (blocked) driver
    Route::patch('/{driverId}/restore', [\App\Http\Controllers\DriverController::class, 'restore']);
    Route::delete('/{driverId}', [\App\Http\Controllers\DriverController::class, 'destroy']);
});

// Live Tracking: trigger a broadcast of current positions
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->get('/web/live/positions/broadcast', [\App\Http\Controllers\LiveTrackingController::class, 'broadcast']);
// Live Tracking: HTTP fallback to fetch current positions (auth-protected)
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->get('/web/live/positions/current', [\App\Http\Controllers\LiveTrackingController::class, 'current']);

// NEW: Auth-protected Users CRUD
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/options', [UserController::class, 'options']);
    Route::get('/device-options', [\App\Http\Controllers\VehicleController::class, 'options']); // Reused options for users
    Route::get('/{userId}', [UserController::class, 'show']);
    Route::get('/{userId}/permissions', [UserController::class, 'permissions']);
    Route::put('/{userId}/permissions', [UserController::class, 'updatePermissions']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{userId}', [UserController::class, 'update']);
    // Restore a soft-deleted (blocked) user
    Route::patch('/{userId}/restore', [UserController::class, 'restore']);
    Route::delete('/{userId}', [UserController::class, 'destroy']);
});

// Admin-only Settings: Vehicle Models IOIDs
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/settings')->group(function () {
    Route::get('/vehicle-models', [VehicleModelController::class, 'index']);
    Route::post('/vehicle-models', [VehicleModelController::class, 'store']);
    Route::put('/vehicle-models/{id}', [VehicleModelController::class, 'update']);
    Route::delete('/vehicle-models/{id}', [VehicleModelController::class, 'destroy']);
});

// NEW: Auth-protected Zones CRUD
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/zones')->group(function () {
    Route::get('/', [\App\Http\Controllers\ZoneController::class, 'index']);
    Route::get('/{zoneId}', [\App\Http\Controllers\ZoneController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\ZoneController::class, 'store']);
    Route::put('/{zoneId}', [\App\Http\Controllers\ZoneController::class, 'update']);
    // Restore a soft-deleted (blocked) zone
    Route::patch('/{zoneId}/restore', [\App\Http\Controllers\ZoneController::class, 'restore']);
    Route::delete('/{zoneId}', [\App\Http\Controllers\ZoneController::class, 'destroy']);
});

// Auth-protected Fuel Management
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/fuel')->group(function () {
    Route::get('/', [\App\Http\Controllers\FuelController::class, 'index']);
    Route::get('/summary', [\App\Http\Controllers\FuelController::class, 'summary']);
    Route::get('/vehicles', [\App\Http\Controllers\FuelController::class, 'vehicleOptions']);
    Route::post('/', [\App\Http\Controllers\FuelController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\FuelController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\FuelController::class, 'update']);
    Route::patch('/{id}/restore', [\App\Http\Controllers\FuelController::class, 'restore']);
    Route::delete('/{id}', [\App\Http\Controllers\FuelController::class, 'destroy']);
});

// NEW: Auth-protected Geofence listing from Tracking DB (testing/util)
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->get('/web/tracking/geofences', [\App\Http\Controllers\ZoneController::class, 'geofencesDb']);

Route::middleware('auth')->get('/web/tracking/assign-computed-attributes', function (\Illuminate\Http\Request $request) {
    $summary = app(\App\Services\PermissionService::class)->assignComputedAttributesToAllDevices($request);
    return response()->json($summary);
});

// Auth-protected Notifications APIs (publicly accessible to all roles)
Route::middleware(['auth'])->prefix('/web/notifications')->group(function () {
    Route::get('/broadcast', [\App\Http\Controllers\NotificationController::class, 'broadcast']);
    Route::get('/events', [\App\Http\Controllers\NotificationController::class, 'events']);
    Route::get('/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::post('/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead']);
    Route::get('/my-device-ids', [\App\Http\Controllers\NotificationController::class, 'myDeviceIds']);
    Route::delete('/events/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy']);
    Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/device/{deviceId}', [\App\Http\Controllers\NotificationController::class, 'device']);
    Route::post('/', [\App\Http\Controllers\NotificationController::class, 'store']);
    Route::post('/assign', [\App\Http\Controllers\NotificationController::class, 'assign']);
});

// Reports
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/reports')->group(function () {
    Route::get('/trip-summary', [\App\Http\Controllers\ReportController::class, 'tripSummary']);
    Route::get('/daily-trips', [\App\Http\Controllers\ReportController::class, 'dailyTrips']);
    Route::get('/daily-summary', [\App\Http\Controllers\ReportController::class, 'dailySummary']);
    Route::get('/monthly-summary', [\App\Http\Controllers\ReportController::class, 'monthlySummary']);
    Route::get('/fuel-detailed', [\App\Http\Controllers\ReportController::class, 'fuelDetailed']);
    Route::get('/asset-activity', [\App\Http\Controllers\ReportController::class, 'assetActivity']);
    Route::get('/vehicle-activity', [\App\Http\Controllers\ReportController::class, 'vehicleActivity']);
    Route::get('/idling', [\App\Http\Controllers\ReportController::class, 'idling']);
    Route::get('/utilisation', [\App\Http\Controllers\ReportController::class, 'utilisation']);
    Route::get('/utilisation-db', [\App\Http\Controllers\ReportController::class, 'utilisationDb']);
    Route::get('/daily-breakdown-map', [\App\Http\Controllers\ReportController::class, 'dailyBreakdownMap']);
    Route::get('/vehicle-status', [\App\Http\Controllers\ReportController::class, 'vehicleStatus']);
    Route::get('/vehicle-status/export-pdf', [\App\Http\Controllers\ReportController::class, 'exportVehicleStatusPdf']);

    Route::get('/device-options', [\App\Http\Controllers\ReportController::class, 'deviceOptions']);
    Route::get('/group-options', [\App\Http\Controllers\ReportController::class, 'groupOptions']);
    // Incident Analysis
    Route::get('/incidents', [\App\Http\Controllers\ReportController::class, 'incidents']);
    Route::post('/incidents', [\App\Http\Controllers\ReportController::class, 'storeIncident']);
    Route::get('/incidents/export-pdf', [\App\Http\Controllers\ReportController::class, 'exportIncidentsPdf']);
    Route::get('/incidents/export-excel', [\App\Http\Controllers\ReportController::class, 'exportIncidentsExcel']);
    Route::get('/vehicle-ranking', [\App\Http\Controllers\ReportController::class, 'vehicleRanking']);

    Route::get('/effective-fuel', [\App\Http\Controllers\ReportController::class, 'effectiveFuel']);
    Route::get('/route-playback', [\App\Http\Controllers\ReportController::class, 'routePlayback']);
});

// Command Console
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/commands')->group(function () {
    Route::post('/send', [\App\Http\Controllers\CommandController::class, 'send']);
    Route::get('/types', [\App\Http\Controllers\CommandController::class, 'types']);
    Route::get('/device-options', [\App\Http\Controllers\ReportController::class, 'deviceOptions']);
});
