<?php

namespace App\Helpers;

use Carbon\Carbon;

class Helpers
{
    /**
     * Convert a UTC/ISO timestamp to application timezone.
     * Returns a formatted datetime string (Y-m-d H:i:s).
     *
     * @param string|null $timestamp
     * @param int|null $userId Optional user id if per-user timezone is added later
     * @return string|null
     */
    public static function servertime(?string $timestamp, ?int $userId = null): ?string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return $timestamp;
        }

        try {
            $tz = config('app.timezone') ?: 'UTC';
            return Carbon::parse($timestamp)->timezone($tz)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            // Fallback to raw value if parsing fails
            return $timestamp;
        }
    }
}