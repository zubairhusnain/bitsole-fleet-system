<?php

namespace App\Services;

use App\Helpers\Curl;
use Illuminate\Http\Request;

class MaintenanceService
{
    use Curl;

    public function getAll($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        // Fetch all maintenance records
        return static::curl('/api/maintenance', 'GET', $sessionId, '', ['Content-Type: application/json']);
    }

    public function create($request, $data)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return static::curl('/api/maintenance', 'POST', $sessionId, json_encode($data), ['Content-Type: application/json']);
    }

    public function update($request, $id, $data)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data['id'] = $id;
        return static::curl('/api/maintenance/' . $id, 'PUT', $sessionId, json_encode($data), ['Content-Type: application/json']);
    }

    public function delete($request, $id)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        return static::curl('/api/maintenance/' . $id, 'DELETE', $sessionId, '', ['Content-Type: application/json']);
    }

    public function assignToDevice($request, $maintenanceId, $deviceId)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data = [
            'deviceId' => $deviceId,
            'maintenanceId' => $maintenanceId
        ];
        return static::curl('/api/permissions', 'POST', $sessionId, json_encode($data), ['Content-Type: application/json']);
    }

    public function removeAssignment($request, $maintenanceId, $deviceId)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data = [
            'deviceId' => $deviceId,
            'maintenanceId' => $maintenanceId
        ];
        return static::curl('/api/permissions', 'DELETE', $sessionId, json_encode($data), ['Content-Type: application/json']);
    }

    public function getAllDevices($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $response = static::curl('/api/devices', 'GET', $sessionId, '', ['Content-Type: application/json']);
        return json_decode($response->response, true) ?? [];
    }

    public function getDevicesForMaintenance($request, $maintenanceId)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $response = static::curl('/api/devices?maintenanceId=' . $maintenanceId, 'GET', $sessionId, '', ['Content-Type: application/json']);
        return json_decode($response->response, true) ?? [];
    }
}
