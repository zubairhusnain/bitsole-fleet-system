<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Events\PositionsUpdated;
use Carbon\Carbon;

class LiveTrackingController extends Controller
{
    public function broadcast(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        // Emit positions for the authenticated user; client listens via private WebSocket channel
        $user = $request->user();
        if (!$user->canRead('live-tracking')) { return response()->json(['message' => 'Forbidden'], 403); }
        event(new \App\Events\PositionsUpdated($user));
        return response()->json(['ok' => true]);
    }

    public function current(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canRead('live-tracking')) { return response()->json(['positions' => []]); }
        $mine = $request->boolean('mine');
        $deviceIdParam = $request->input('deviceId');

        // Fetch live devices directly from Traccar via DeviceService and map positions
        $positions = app(\App\Services\DeviceService::class)->getLiveDevices($user, [
            'mine' => $mine,
            'source' => 'current',
        ]);

        // Optional: filter by a specific deviceId when provided
        if ($deviceIdParam !== null && is_numeric($deviceIdParam)) {
            $did = (int) $deviceIdParam;
            $positions = array_values(array_filter($positions, function ($p) use ($did) {
                return (int) ($p['id'] ?? 0) === $did;
            }));
        }

        return response()->json(['positions' => $positions]);
    }
}
