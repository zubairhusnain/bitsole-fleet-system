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

        // Access Control: Fleet Manager (1) ONLY
        if (!$user || $user->role !== User::ROLE_FLEET_MANAGER) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = SystemActivityLog::query()->orderBy('created_at', 'desc');

        // Data Isolation for Fleet Manager
        $managedUserIds = User::where('manager_id', $user->id)->pluck('id')->toArray();
        $allowedUserIds = array_merge([$user->id], $managedUserIds);
        $query->whereIn('user_id', $allowedUserIds);

        // Filters
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(20);

        return response()->json($logs);
    }

    public function getFiltersData()
    {
        $user = Auth::user();
        if (!$user || $user->role !== User::ROLE_FLEET_MANAGER) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $baseQuery = SystemActivityLog::query();

        // Data Isolation logic
        $managedUserIds = User::where('manager_id', $user->id)->pluck('id')->toArray();
        $allowedUserIds = array_merge([$user->id], $managedUserIds);
        $baseQuery->whereIn('user_id', $allowedUserIds);

        // Get unique modules from logs within scope
        $modules = (clone $baseQuery)->distinct()->pluck('module')->filter()->values();

        // Get users within scope who have performed actions
        $userIds = (clone $baseQuery)->distinct()->pluck('user_id');
        $users = User::whereIn('id', $userIds)->get(['id', 'name']);

        return response()->json([
            'modules' => $modules,
            'users' => $users
        ]);
    }
}
