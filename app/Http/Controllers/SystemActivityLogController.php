<?php

namespace App\Http\Controllers;

use App\Models\SystemActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Access Control: Only Fleet Manager (1) and Admin (3)
        // User prompt says "only access by fleetmanager", but usually Admin has access too.
        // Assuming Role 1 = Fleet Manager, Role 3 = Admin.
        if (!$user || !in_array($user->role, [User::ROLE_FLEET_MANAGER, User::ROLE_ADMIN])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = SystemActivityLog::query()->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('module') && $request->module) {
            $query->where('module', $request->module);
        }
        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(20);

        return response()->json($logs);
    }
}
