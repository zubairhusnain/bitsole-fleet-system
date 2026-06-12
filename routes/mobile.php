<?php

use App\Http\Controllers\Mobile\MobileAuthController;
use App\Http\Controllers\Mobile\MobileDriverController;
use App\Http\Controllers\Mobile\MobileNotificationController;
use App\Http\Controllers\Mobile\MobileVehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes (Flutter app — Bearer token via Sanctum)
|--------------------------------------------------------------------------
*/

Route::prefix('mobile')->group(function () {
    Route::post('/auth/login', [MobileAuthController::class, 'login']);
    Route::post('/auth/register', [MobileAuthController::class, 'register']);
    Route::post('/auth/forgot-password', [MobileAuthController::class, 'forgotPassword']);

    Route::middleware(['auth:sanctum', \App\Http\Middleware\MobileModulePermission::class])->group(function () {
        Route::get('/auth/me', [MobileAuthController::class, 'me']);
        Route::post('/auth/logout', [MobileAuthController::class, 'logout']);
        Route::put('/auth/profile', [MobileAuthController::class, 'updateProfile']);

        Route::get('/live/positions', [MobileVehicleController::class, 'positions']);

        Route::get('/vehicles', [MobileVehicleController::class, 'index']);
        Route::get('/vehicles/models/options', [MobileVehicleController::class, 'modelOptions']);
        Route::post('/vehicles', [MobileVehicleController::class, 'store']);
        Route::get('/vehicles/{deviceId}', [MobileVehicleController::class, 'show']);
        Route::get('/vehicles/{deviceId}/detail', [MobileVehicleController::class, 'detail']);
        Route::get('/vehicles/{deviceId}/trips', [MobileVehicleController::class, 'trips']);
        Route::get('/vehicles/{deviceId}/driver', [MobileVehicleController::class, 'driver']);
        Route::get('/vehicles/{deviceId}/performance', [MobileVehicleController::class, 'performance']);
        Route::get('/vehicles/{deviceId}/rating', [MobileVehicleController::class, 'rating']);
        Route::get('/vehicles/{deviceId}/position', [MobileVehicleController::class, 'position']);
        Route::get('/vehicles/{deviceId}/geofences', [MobileVehicleController::class, 'geofences']);
        Route::put('/vehicles/{deviceId}', [MobileVehicleController::class, 'update']);
        Route::delete('/vehicles/{deviceId}', [MobileVehicleController::class, 'destroy']);
        Route::post('/vehicles/{deviceId}/alert-status', [MobileVehicleController::class, 'updateAlertStatus']);

        Route::get('/drivers', [MobileDriverController::class, 'index']);
        Route::get('/drivers/{driverId}', [MobileDriverController::class, 'show']);

        Route::get('/notifications/events', [MobileNotificationController::class, 'events']);
        Route::get('/notifications/unread-count', [MobileNotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-read', [MobileNotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [MobileNotificationController::class, 'destroy']);
    });
});
