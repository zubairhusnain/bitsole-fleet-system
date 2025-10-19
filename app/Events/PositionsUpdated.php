<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\TcDevice;
use App\Models\Devices;
use App\Models\User;

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
