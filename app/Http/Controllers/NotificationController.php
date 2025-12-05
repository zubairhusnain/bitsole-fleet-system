<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $payload = $this->notificationService->allnotification($request);
        return response()->json($payload);
    }

    public function device(Request $request, int $deviceId)
    {
        $req = new Request(array_merge($request->all(), ['device_detail_id' => $deviceId]));
        $payload = $this->notificationService->deviceNotification($req);
        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $errors = $this->notificationService->addNotification($request);
        $ok = empty($errors);
        return response()->json(['ok' => $ok, 'errors' => $errors]);
    }

    public function assign(Request $request)
    {
        $deviceId = (int) $request->input('deviceId');
        $notificationId = (int) $request->input('notificationId');
        if (!$deviceId || !$notificationId) {
            return response()->json(['message' => 'deviceId and notificationId are required'], 422);
        }
        $resp = $this->permissionService->assignNotification($request, $deviceId, $notificationId);
        return response()->json(['response' => $resp]);
    }
}

