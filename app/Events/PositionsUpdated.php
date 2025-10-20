<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Devices;
use App\Models\User;
use Carbon\Carbon;

class PositionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $positions;
    protected int $userId;

    public function __construct(User $user)
    {
        $this->userId = (int) $user->id;
        $role = (int) ($user->role ?? User::ROLE_ADMIN);

        // Scope via local Devices mapping, eager load tcDevice + position
        $query = Devices::query()->with(['tcDevice.position']);
        if ($role === User::ROLE_DISTRIBUTOR) {
            $query->where('user_id', $user->id)
                  ->where('distributor_id', $user->id);
        } elseif ($role !== User::ROLE_ADMIN) {
            $distId = $user->distributor_id ?? $user->id;
            $query->where('distributor_id', $distId)
                  ->where('user_id', $user->id);
        }

        $devices = $query->get();

        $this->positions = $devices->map(function ($device) {
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

            // Derive activity (motion/ignition); status should mirror tc_devices.status
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

            // Normalize tc_devices.status to online/offline/unknown to match Traccar
            $statusRaw = strtolower(trim((string) ($tc->status ?? '')));
            $deviceStatus = in_array($statusRaw, ['online','offline','unknown']) ? $statusRaw : 'unknown';

            return [
                'id' => $tc->id,
                'name' => $tc->name ?? ('Device #' . $tc->id),
                'latitude' => $pos->latitude ?? null,
                'longitude' => $pos->longitude ?? null,
                'speed' => $pos->speed ?? null,
                'address' => $pos->address ?? null,
                'ignition' => $ignition,
                'status' => $deviceStatus,
                'activity' => $activity,
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
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('positions.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'positions.updated';
    }
}
