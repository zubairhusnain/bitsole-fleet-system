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
            'type' => 'required|string',
            'attributes' => 'nullable|array'
        ]);

        // Fetch device for logging (Access control handled by middleware/scope if applicable)
        // User suggested removing explicit permission check here
        $device = Devices::where('device_id', $request->device_id)->first();

        if (!$device) {
             return response()->json(['message' => 'Device not found'], 404);
        }

        // Send command via service
        $result = $this->commandService->sendCommand(
            $request->device_id,
            $request->type,
            $request->attributes ?? []
        );

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
                    'description' => "Sent {$request->type} to device {$device->name}",
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
    public function types()
    {
        return response()->json([
            ['value' => 'engineStop', 'label' => 'Stop Engine (Immobilize)', 'danger' => true],
            ['value' => 'engineResume', 'label' => 'Resume Engine', 'danger' => false],
            ['value' => 'positionSingle', 'label' => 'Get Single Position', 'danger' => false],
            ['value' => 'custom', 'label' => 'Custom Command', 'danger' => true],
            ['value' => 'rebootDevice', 'label' => 'Reboot Device', 'danger' => true],
        ]);
    }
}
