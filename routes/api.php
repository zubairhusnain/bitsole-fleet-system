<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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
