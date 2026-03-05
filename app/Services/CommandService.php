<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TcDevice;

class CommandService
{
    protected $baseUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        // Use existing Traccar config or defaults
        $this->baseUrl = config('traccar.base_url', 'http://localhost:8082');
        $this->username = config('traccar.username', 'admin');
        $this->password = config('traccar.password', 'admin');
    }

    /**
     * Send a command to a device via Traccar API
     *
     * @param int $deviceId The Traccar device ID (tc_devices.id)
     * @param string $type Command type (engineStop, engineResume, custom, etc.)
     * @param array $attributes Additional attributes (e.g. { "data": "reset" } for custom commands)
     * @return array
     */
    public function sendCommand($deviceId, $type, $attributes = [])
    {
        // Map friendly types to Traccar command types if needed
        $traccarType = $type;
        
        // Construct payload
        $payload = [
            'deviceId' => (int)$deviceId,
            'type' => $traccarType,
            'attributes' => (object)$attributes
        ];

        try {
            // Use basic auth as per standard Traccar API usage in this system context
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/api/commands/send", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error("Traccar Command Failed: " . $response->body());
            return [
                'success' => false,
                'message' => 'Command failed: ' . $response->status() . ' - ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Traccar Command Exception: " . $e->getMessage());
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
        // This could be fetched from Traccar /api/commands?deviceId=X 
        // or a local table if we implement saved templates
        return []; 
    }
}
