<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommandService;
use App\Models\Devices;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemActivityLog;

class CommandController extends Controller
{
    protected $commandService;

    public function __construct(CommandService $commandService)
    {
        $this->commandService = $commandService;
    }

    /**
     * Send a command to a specific device
     */
    public function send(Request $request)
    {
        $request->validate([
            'device_id' => 'required|integer',
            'command_id' => 'nullable|integer', // For saved command
            'type' => 'required_without:command_id|string',
            'attributes' => 'nullable|array'
        ]);

        // Fetch device for logging (Access control handled by middleware/scope if applicable)
        $device = Devices::where('device_id', $request->device_id)->first();

        if (!$device) {
             return response()->json(['message' => 'Device not found'], 404);
        }

        $data = [];
        if ($request->filled('command_id')) {
            $data['id'] = $request->command_id;
            $desc = "Sent saved command #{$request->command_id}";
        } else {
            $data['type'] = $request->type;
            $data['attributes'] = $request->attributes ?? [];
            $desc = "Sent {$request->type} command";
        }

        // Send command via service
        $result = $this->commandService->sendCommand($request->device_id, $data);

        if ($result['success']) {
            // Log activity
            $user = Auth::user();
            try {
                SystemActivityLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_role' => $user->role_label ?? 'User',
                    'action' => 'command_sent',
                    'module' => 'commands',
                    'description' => "$desc to device {$device->name}",
                    'ip_address' => $request->ip()
                ]);
            } catch (\Exception $e) {
                // Ignore logging errors
            }

            return response()->json(['message' => 'Command sent successfully', 'details' => $result['data']]);
        }

        return response()->json(['message' => $result['message']], 500);
    }

    /**
     * Get available command types
     */
    public function types(Request $request)
    {
        $deviceId = $request->input('device_id');
        // Fetch ALL supported command types from Traccar (pass null to ignore device filter)
        // User requested to see all supported types, not just the limited set reported by protocol.
        $types = $this->commandService->getCommandTypes(null);

        // Ensure 'custom' is present if not returned by API (it's often hidden but supported)
        $hasCustom = false;
        foreach ($types as $t) {
            $val = is_array($t) ? ($t['type'] ?? '') : $t;
            if ($val === 'custom') { $hasCustom = true; break; }
        }
        if (!$hasCustom) {
            $types[] = ['type' => 'custom'];
        }

        // Format for frontend
        $formatted = array_map(function($t) {
            // Traccar returns objects like { "type": "custom" } or strings?
            // Usually returns list of objects with type property
            $type = is_array($t) ? ($t['type'] ?? '') : $t;
            return [
                'type' => $type,
                'description' => ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $type)), // CamelCase to spaced
                'danger' => in_array($type, ['engineStop', 'custom', 'rebootDevice'])
            ];
        }, $types);
        
        // Sort alphabetically by description for better UX
        usort($formatted, function($a, $b) {
            return strcmp($a['description'], $b['description']);
        });

        return response()->json($formatted);
    }

    /**
     * Get saved commands for a device
     */
    public function saved(Request $request)
    {
        $deviceId = $request->input('device_id');
        if (!$deviceId) return response()->json([]);

        $commands = $this->commandService->getSavedCommands($deviceId);
        return response()->json($commands);
    }
}
