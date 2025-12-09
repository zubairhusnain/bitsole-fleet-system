<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class AlertsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $alerts;
    protected int $userId;

    public function __construct(User $user)
    {
        $this->userId = (int) $user->id;
        // Use AlertService to retrieve live alerts
        $this->alerts = app(\App\Services\AlertService::class)->getLiveAlerts($user, [
            'limit' => 10, // Fetch recent 10 alerts to avoid payload too large
            // 'unreadOnly' => true // Optional: if we only want unread, but LiveTracking sends all positions
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('alerts.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'alerts.updated';
    }
}
