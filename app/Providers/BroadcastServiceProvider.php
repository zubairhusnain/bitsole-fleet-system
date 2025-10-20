<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register the /broadcasting/auth route and other broadcasting endpoints
        Broadcast::routes();

        // Load channel authorization callbacks
        require base_path('routes/channels.php');
    }
}