<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Traccar timestamps are UTC wall-clock values in timestamp-without-timezone columns.
 */
class UtcDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = $value instanceof Carbon
            ? $value->copy()->utc()
            : Carbon::parse($value, 'UTC');

        return $carbon->format('Y-m-d H:i:s');
    }
}
