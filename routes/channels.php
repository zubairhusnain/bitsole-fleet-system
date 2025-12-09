<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('positions.{userId}', function ($user, int $userId) {
    // Authorize only the matching authenticated user to join their private channel
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('alerts.{userId}', function ($user, int $userId) {
    return (int) $user->id === (int) $userId;
});