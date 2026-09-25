<?php

use App\Http\Controllers\Mobile\MobileAuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile auth (Sanctum bearer tokens) — mirrors /web/auth structure.
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login']);
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/forgot-password', [MobileAuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::put('/profile', [MobileAuthController::class, 'updateProfile']);
    });
});

require __DIR__.'/api_fleet.php';

Route::get('/status', function () {
    return response()->json([
        'app' => config('app.name'),
        'env' => config('app.env'),
        'status' => 'ok',
    ]);
});

Route::get('/time', function () {
    return response()->json(['server_time' => now()->toDateTimeString()]);
});

Route::get('/db-check', function () {
    try {
        DB::select('select 1');
        return response()->json(['database' => 'connected']);
    } catch (\Throwable $e) {
        return response()->json([
            'database' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
