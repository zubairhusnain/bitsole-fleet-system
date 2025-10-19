<?php

use App\Models\TcUser;
use App\Http\Controllers\AuthController;
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
Route::middleware('auth')->prefix('/web/admin')->group(function () {
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
Route::middleware('auth')->prefix('/web/vehicles')->group(function () {
    Route::get('/', [\App\Http\Controllers\VehicleController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\VehicleController::class, 'store']);
    Route::get('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'show']);
    Route::put('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'update']);
    Route::delete('/{deviceId}', [\App\Http\Controllers\VehicleController::class, 'destroy']);
});

// Live Tracking: trigger a broadcast of current positions
Route::middleware('auth')->get('/web/live/positions/broadcast', [\App\Http\Controllers\LiveTrackingController::class, 'broadcast']);
// Live Tracking: HTTP fallback to fetch current positions
Route::get('/web/live/positions/current', [\App\Http\Controllers\LiveTrackingController::class, 'current']);
