<?php

namespace App\Support;

use Carbon\Carbon;

class TraccarTime
{
    /** Parse a Traccar fixtime / eventtime value as UTC. */
    public static function parse(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value, 'UTC');
        }

        return now('UTC');
    }
}
