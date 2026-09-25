<?php

namespace App\Support;

use App\Exceptions\ClientDisconnectedException;

class ClientDisconnect
{
    public static function bootstrap(): void
    {
        ignore_user_abort(false);
    }

    public static function throwIfDisconnected(): void
    {
        if (connection_aborted()) {
            throw new ClientDisconnectedException;
        }
    }
}
