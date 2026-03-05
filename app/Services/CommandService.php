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
     * @param int $deviceId The device ID
     * @param array $data Command data (type+attributes OR id)
     * @return array
     */
    public function sendCommand($deviceId, $data)
    {
        $user = Auth::user();
        $sessionId = $user->traccarSession ?? session('cookie');

        // Prepare payload
        $payloadData = ['deviceId' => (int)$deviceId];

        if (isset($data['id'])) {
            // Sending a saved command
            $payloadData['id'] = (int)$data['id'];
        } else {
            // Sending a new command
            $payloadData['type'] = $data['type'] ?? 'custom';
            $payloadData['attributes'] = (object)($data['attributes'] ?? []);
        }

        $payload = json_encode($payloadData);

        try {
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            $response = static::curl('/api/commands/send', 'POST', $sessionId, $payload, $headers);

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
     * Get available command types for a device
     */
    public function getCommandTypes($deviceId)
    {
        $user = Auth::user();
        $sessionId = $user->traccarSession ?? session('cookie');

        try {
            $headers = ['Accept: application/json'];
            // Traccar endpoint: /api/commands/types?deviceId={id}
            $endpoint = '/api/commands/types' . ($deviceId ? '?deviceId=' . $deviceId : '');

            $response = static::curl($endpoint, 'GET', $sessionId, '', $headers);

            if ($response->responseCode >= 200 && $response->responseCode < 300) {
                return json_decode($response->response, true) ?? [];
            }
            return [];
        } catch (\Exception $e) {
            Log::error("GetCommandTypes Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get saved commands for a device
     */
    public function getSavedCommands($deviceId)
    {
        $user = Auth::user();
        $sessionId = $user->traccarSession ?? session('cookie');

        try {
            $headers = ['Accept: application/json'];
            // Traccar endpoint: /api/commands?deviceId={id}
            $endpoint = '/api/commands' . ($deviceId ? '?deviceId=' . $deviceId : '');

            $response = static::curl($endpoint, 'GET', $sessionId, '', $headers);

            if ($response->responseCode >= 200 && $response->responseCode < 300) {
                return json_decode($response->response, true) ?? [];
            }
            return [];
        } catch (\Exception $e) {
            Log::error("GetSavedCommands Exception: " . $e->getMessage());
            return [];
        }
    }
}
