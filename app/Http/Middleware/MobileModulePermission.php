<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;

/**
 * Permission gate for /api/mobile/* routes — mirrors web ModulePermission mapping.
 */
class MobileModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $parts = explode('/', trim($request->path(), '/'));
        if (count($parts) < 3 || $parts[0] !== 'api' || $parts[1] !== 'mobile') {
            return $next($request);
        }

        $action = match (strtoupper($request->method())) {
            'GET' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };

        $base = strtolower($parts[2] ?? '');

        // Auth and profile routes — any authenticated user
        if (in_array($base, ['auth', 'profile'], true)) {
            return $next($request);
        }

        // Live positions — readable by fleet roles with vehicle or monitoring access
        if ($base === 'live' && $action === 'read') {
            if (Permissions::check($user, 'vehicles', 'read')
                || Permissions::check($user, 'monitoring.vehicles', 'read')
                || $user->isAdmin() || $user->isDistributor()) {
                return $next($request);
            }
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Notifications — any authenticated fleet user (not admin/distributor empty set on web)
        if ($base === 'notifications') {
            if ($user->isAdmin() || $user->isDistributor()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            return $next($request);
        }

        $moduleMap = [
            'vehicles' => 'vehicles',
            'drivers' => 'drivers',
            'monitoring' => 'monitoring.vehicles',
        ];

        $moduleKey = $moduleMap[$base] ?? null;
        if ($moduleKey && Permissions::check($user, $moduleKey, $action)) {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isDistributor()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
