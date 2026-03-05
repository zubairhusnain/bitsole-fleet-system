<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CommandService;
use App\Models\Devices;
use Illuminate\Support\Facades\Auth;

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

        // Security Check: Ensure user has access to this device
        $user = Auth::user();
        $device = Devices::accessibleByUser($user)
            ->where('device_id', $request->device_id)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Unauthorized access to device'], 403);
        }

        $result = $this->commandService->sendCommand(
            $request->device_id,
            $request->type,
            $request->attributes ?? []
        );

        if ($result['success']) {
            // Log activity
            try {
                \App\Models\SystemActivityLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_role' => $user->role_label,
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
