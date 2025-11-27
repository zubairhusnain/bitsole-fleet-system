<?php

use App\Models\TcUser;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleModelController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
Route::get('/testing', function () {

            try {
            // Raw SQL query to select all columns from the 'tc_users' table
            // We prepend the schema name 'omayer' to ensure the database
            // looks in the correct location for the table.
            $users = TcUser::all();
            dd($users);
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
});

// Auth-protected admin APIs (session-based)
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/admin')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        return response()->json([
            'message' => 'Welcome to admin dashboard',
            'user' => $request->user(),
        ]);
    });

    Route::get('/stats', function () {
        return response()->json([
            'stats' => [
                'users' => \App\Models\User::count(),
                'time' => now()->toDateTimeString(),
            ],
        ]);
    });

    Route::get('/profile', function (Request $request) {
        return response()->json([
            'profile' => $request->user(),
        ]);
    });
});

// Auth-protected Vehicles CRUD
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/vehicles')->group(function () {
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

});

// NEW: Auth-protected Drivers CRUD & assignment
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->prefix('/web/drivers')->group(function () {
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

// NEW: Auth-protected Geofence listing from Traccar DB (testing/util)
Route::middleware(['auth', \App\Http\Middleware\ModulePermission::class])->get('/web/traccar/geofences', [\App\Http\Controllers\ZoneController::class, 'geofencesDb']);
