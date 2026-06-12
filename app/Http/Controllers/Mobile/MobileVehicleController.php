<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LiveTrackingController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleModelController;
use Illuminate\Http\Request;

class MobileVehicleController extends Controller
{
    public function positions(Request $request)
    {
        return app(LiveTrackingController::class)->current($request);
    }

    public function index(Request $request)
    {
        return app(VehicleController::class)->index($request);
    }

    public function modelOptions(Request $request)
    {
        return app(VehicleModelController::class)->options($request);
    }

    public function show(Request $request, $deviceId)
    {
        return app(VehicleController::class)->show($request, $deviceId);
    }

    public function detail(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->detail($request, $deviceId);
    }

    public function trips(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->trips($request, $deviceId);
    }

    public function driver(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->driver($request, $deviceId);
    }

    public function performance(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->performance($request, $deviceId);
    }

    public function rating(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->rating($request, $deviceId);
    }

    public function position(Request $request, $deviceId)
    {
        return app(VehicleController::class)->positionCurrent($request, $deviceId);
    }

    public function geofences(Request $request, int $deviceId)
    {
        return app(VehicleController::class)->geofences($request, $deviceId);
    }

    public function store(Request $request)
    {
        return app(VehicleController::class)->store($request);
    }

    public function update(Request $request, $deviceId)
    {
        return app(VehicleController::class)->update($request, $deviceId);
    }

    public function destroy(Request $request, $deviceId)
    {
        return app(VehicleController::class)->destroy($request, $deviceId);
    }

    public function updateAlertStatus(Request $request, $deviceId)
    {
        return app(MonitoringController::class)->updateAlertStatus($request, $deviceId);
    }
}
