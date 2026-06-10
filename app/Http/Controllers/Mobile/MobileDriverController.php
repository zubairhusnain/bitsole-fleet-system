<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DriverController;
use Illuminate\Http\Request;

class MobileDriverController extends Controller
{
    public function index(Request $request)
    {
        return app(DriverController::class)->index($request);
    }

    public function show(Request $request, int $driverId)
    {
        return app(DriverController::class)->show($request, $driverId);
    }
}
