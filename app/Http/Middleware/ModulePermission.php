<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Permissions;
use App\Models\UserPermission;

class ModulePermission
{
    public static function modules(): array
    {
        return [
            'drivers' => 'Driver Management',
            'vehicles' => 'Vehicle Management',
            'vehicles.maintenance' => 'Vehicle Maintenance',
            'vehicles.overview' => 'Vehicle Overview',
            'zones' => 'Zone Management',
            'users' => 'User Management',
            'users.permissions' => 'User Permission',
            // 'admin' => 'Admin',
            // 'reports' => 'Reports & Analytics',
            // 'alerts' => 'Alerts & Notifications',
            // 'fuel' => 'Fuel Management',
            // 'settings' => 'Settings',
            // 'tasks' => 'Tasks',
            // 'telemetry' => 'Telemetry Tools',
        ];
    }
    public static function relatedModules(): array
    {
        return [];
    }
    /**
     * Dynamically detect "self" user resource access, without hardcoding positions.
     * Allows a signed-in user to read or update their own user record regardless of module permission.
     */
    private static function isSelfUserAccess(array $parts, $user, string $action): bool
    {
        if (!$user) return false;
        if (!in_array($action, ['read', 'update'], true)) return false;
        // Find the first occurrence of "users" and check the next segment for a numeric id equal to the current user.
        $idx = null;
        foreach ($parts as $i => $p) {
            if (strtolower($p) === 'users') { $idx = $i; break; }
        }
        if ($idx === null) return false;
        $idSeg = $parts[$idx + 1] ?? null;
        if ($idSeg === null) return false;
        return is_numeric($idSeg) && ((int)$idSeg === (int)$user->id);
    }
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthorized'], 401); }
        $path = trim($request->path(), '/');
        $parts = explode('/', $path);
        $key = null;
        $action = match (strtoupper($request->method())) {
            'GET' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };
        if (count($parts) >= 2 && $parts[0] === 'web') {
            $base = strtolower($parts[1] ?? '');
            $specialAllow = false;
            // Admin-only settings
            if ($base === 'settings' && !$user->isAdmin()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            if (static::isSelfUserAccess($parts, $user, $action)) { $specialAllow = true; }
            if ($action === 'read' && $base === 'live') { $specialAllow = true; }
            if ($action === 'read' && $base === 'vehicles' && count($parts) >= 3) {
                for ($i = 2; $i < count($parts); $i++) {
                    if (strtolower($parts[$i]) === 'options') {
                        if (Permissions::check($user, 'drivers', 'read') || Permissions::check($user, 'drivers', 'create') || Permissions::check($user, 'drivers', 'update')) { $specialAllow = true; }
                        break;
                    }
                    if (strtolower($parts[$i]) === 'models' && strtolower($parts[$i+1] ?? '') === 'options') {
                        $specialAllow = true;
                        break;
                    }
                }
            }
            if ($specialAllow) { return $next($request); }
            $allModules = array_keys(static::modules());
            $canonical = null;
            foreach ($allModules as $mk) {
                $mkTokens = preg_split('/[\._-]+/', strtolower($mk), -1, PREG_SPLIT_NO_EMPTY);
                if (($mkTokens[0] ?? '') === $base) {
                    if ($canonical === null) { $canonical = $mk; }
                    else {
                        $canonTokens = preg_split('/[\._-]+/', strtolower($canonical), -1, PREG_SPLIT_NO_EMPTY);
                        if (count($mkTokens) < count($canonTokens)) { $canonical = $mk; }
                    }
                }
            }
            $key = $canonical ?: $base;
        }
        if ($key && Permissions::check($user, $key, $action)) { return $next($request); }
        if ($user->isAdmin() || $user->isDistributor()) {
            $k = strtolower((string)$key);
            if ($k === 'users' || $k === 'users.permissions' || str_starts_with($k, 'users')) { return $next($request); }
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return response()->json(['message' => 'Forbidden'], 403);
    }
}
