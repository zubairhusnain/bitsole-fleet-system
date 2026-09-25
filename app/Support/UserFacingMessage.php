<?php

namespace App\Support;

/**
 * Replace third-party product branding in user-visible message strings only.
 * Does not alter JSON keys, model attributes, or telemetry/protocol values.
 */
final class UserFacingMessage
{
    public static function sanitize(?string $message): string
    {
        if ($message === null || $message === '') {
            return (string) $message;
        }

        $replacements = [
            'Traccar error' => 'Tracking server error',
            'traccar error' => 'tracking server error',
            'Traccar version' => 'tracking server version',
            'Traccar' => 'tracking server',
            'traccar' => 'tracking server',
            'TRACCAR' => 'TRACKING SERVER',
        ];

        return strtr($message, $replacements);
    }

    /**
     * Sanitize only top-level API fields that are shown directly to users.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeApiResponse(array $data): array
    {
        if (isset($data['message']) && is_string($data['message'])) {
            $data['message'] = self::sanitize($data['message']);
        }

        if (isset($data['error']) && is_string($data['error'])) {
            $data['error'] = self::sanitize($data['error']);
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $field => $messages) {
                if (! is_array($messages)) {
                    continue;
                }
                $data['errors'][$field] = array_map(
                    fn ($m) => is_string($m) ? self::sanitize($m) : $m,
                    $messages
                );
            }
        }

        return $data;
    }
}
