<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Events\PositionsUpdated;
use App\Models\TcDevice;

class LiveTrackingController extends Controller
{
    public function broadcast(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        // Emit positions for the authenticated user; client listens via private WebSocket channel
        $user = $request->user();
        event(new \App\Events\PositionsUpdated($user));
        return response()->json(['ok' => true]);
    }

    public function current(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $role = (int) ($user->role ?? \App\Models\User::ROLE_ADMIN);

        // Scope via local Devices mapping, eager load tcDevice + position
        $query = \App\Models\Devices::query()->with(['tcDevice.position']);

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        } else {
            if ($role === \App\Models\User::ROLE_DISTRIBUTOR) {
                $query->where('user_id', $user->id)
                      ->where('distributor_id', $user->id);
            } elseif ($role !== \App\Models\User::ROLE_ADMIN) {
                $distId = $user->distributor_id ?? $user->id;
                $query->where('distributor_id', $distId)
                ->where('user_id', $user->id);
            }
            // Admin sees all
        }

        $devices = $query->get();

        $positions = $devices->map(function ($device) {
            $tc = $device->tcDevice;
            if (!$tc) { return null; }
            $pos = $tc->position;

            $posAttributes = [];
            if ($pos && isset($pos->attributes)) {
                $posAttributes = is_array($pos->attributes)
                    ? $pos->attributes
                    : (json_decode($pos->attributes, true) ?? []);
            }
            return [
                'id' => $tc->id,
                'name' => $tc->name ?? ('Device #' . $tc->id),
                'latitude' => $pos->latitude ?? null,
                'longitude' => $pos->longitude ?? null,
                'speed' => $pos->speed ?? null,
                'address' => $pos->address ?? null,
                'ignition' => $posAttributes['ignition'] ?? null,
                // Historically, we exposed position motion under the "status" key
                'status' => $posAttributes['motion'] ?? null,
                'positionId' => $tc->positionid ?? null,
                // Additional fields for UI rendering
                'lastUpdate' => $tc->lastUpdate ?? null,
                'uniqueId' => ($tc->uniqueId ?? $tc->uniqueid ?? null),
                // Device-level attributes (JSON string or object) for UI rendering
                'attributes' => $tc->attributes ?? null,
            ];
        })->filter(function ($i) {
            return $i && $i['latitude'] !== null && $i['longitude'] !== null;
        })->values()->all();

        return response()->json(['positions' => $positions]);
    }
}
