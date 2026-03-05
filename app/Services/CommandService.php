<?php

namespace App\Services;

use App\Helpers\Curl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;

class CommandService
{
    use Curl;

    /**
     * Send a command to a device via Tracking API
     *
     * @param int $deviceId The device ID (tc_devices.id)
     * @param string $type Command type (engineStop, engineResume, custom, etc.)
     * @param array $attributes Additional attributes (e.g. { "data": "reset" } for custom commands)
     * @return array
     */
    public function sendCommand($deviceId, $type, $attributes = [])
    {
        // Follow ReportService pattern: use user session cookie
        $user = Auth::user();
        $sessionId = $user->traccarSession ?? session('cookie');

        // Map friendly types to command types if needed
        $commandType = $type;

        // Construct payload
        $payload = json_encode([
            'deviceId' => (int)$deviceId,
            'type' => $commandType,
            'attributes' => (object)$attributes
        ]);

        try {
            // Using Curl helper as requested
            // static::curl($task, $method, $cookie, $data, $header)
            // Task should start with /api/...

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            $response = static::curl('/api/commands/send', 'POST', $sessionId, $payload, $headers);

            // Check response code (Curl helper returns object with responseCode, response, error)
            if ($response->responseCode >= 200 && $response->responseCode < 300) {
                return [
                    'success' => true,
                    'data' => json_decode($response->response, true)
                ];
            }

            Log::error("Command Failed: " . ($response->response ?? 'No response body'));
            return [
                'success' => false,
                'message' => 'Command failed: ' . $response->responseCode . ' - ' . ($response->response ?? 'Unknown error')
            ];

        } catch (\Exception $e) {
            Log::error("Command Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get saved commands for a device (optional, if using saved commands feature)
     */
    public function getSavedCommands($deviceId)
    {
        // This could be fetched from /api/commands?deviceId=X
        // or a local table if we implement saved templates
        return [];
    }
}
