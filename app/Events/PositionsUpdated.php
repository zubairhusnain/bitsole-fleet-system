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
        // Use the canonical DeviceService to retrieve live devices (same as LiveTrackingController)
        $positions = app(\App\Services\DeviceService::class)->getLiveDevices($user, [
            'source' => 'broadcast',
        ]);
        // Ensure we only broadcast items that have coordinates
        $this->positions = collect($positions)->filter(function ($p) {
            return isset($p['latitude'], $p['longitude']) && $p['latitude'] !== null && $p['longitude'] !== null;
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
