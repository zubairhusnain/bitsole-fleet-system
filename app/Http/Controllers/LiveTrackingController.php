<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Events\PositionsUpdated;
use App\Models\TcDevice;
use Carbon\Carbon;

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

            $serverTimeRaw = $pos->serverTime ?? null;
            $serverTime = $serverTimeRaw ? Carbon::parse($serverTimeRaw) : null;
            $now = Carbon::now();
            $online = $serverTime ? $serverTime->gte($now->copy()->subHour()) : false;

            $motion = isset($posAttributes['motion']) ? (int) $posAttributes['motion'] : null;
            $ignition = $posAttributes['ignition'] ?? null;

            $activity = 'noData';
            if ($serverTime) {
                if ($serverTime->lt($now->copy()->subHour())) {
                    $activity = 'inActive';
                } else {
                    if ($motion === 1) {
                        $activity = 'moving';
                    } elseif ($ignition === 1 && $motion === 0) {
                        $activity = 'idle';
                    } elseif ($motion === 0 && $ignition === 0) {
                        $activity = 'stopped';
                    } else {
                        $activity = 'noData';
                    }
                }
            }

            return [
                'id' => $tc->id,
                'name' => $tc->name ?? ('Device #' . $tc->id),
                'latitude' => $pos->latitude ?? null,
                'longitude' => $pos->longitude ?? null,
                'speed' => $pos->speed ?? null,
                'address' => $pos->address ?? null,
                'ignition' => $ignition,
                'status' => $activity,
                'motion' => $motion,
                'online' => $online,
                'positionId' => $tc->positionid ?? null,
                'lastUpdate' => $tc->lastUpdate ?? null,
                'uniqueId' => ($tc->uniqueId ?? $tc->uniqueid ?? null),
                'attributes' => $tc->attributes ?? null,
                'serverTime' => $serverTimeRaw,
            ];
        })->filter(function ($i) {
            return $i && $i['latitude'] !== null && $i['longitude'] !== null;
        })->values()->all();

        return response()->json(['positions' => $positions]);
    }
}
