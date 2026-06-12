<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;

/**
 * Permission gate for /api/* fleet routes — mirrors web ModulePermission (/web/*).
 * Kept separate so web middleware and routes stay unchanged.
 */
class ApiModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $parts = explode('/', trim($request->path(), '/'));
        if (count($parts) < 2 || $parts[0] !== 'api') {
            return $next($request);
        }

        $action = match (strtoupper($request->method())) {
            'GET' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };

        $base = strtolower($parts[1] ?? '');
        $specialAllow = false;

        if ($base === 'settings' && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->isSelfUserAccess($parts, $user, $action)) {
            $specialAllow = true;
        }
        if ($action === 'read' && $base === 'live') {
            $specialAllow = true;
        }
        if ($base === 'monitoring' && in_array('alert-status', array_map('strtolower', $parts), true)) {
            if (Permissions::check($user, 'vehicles', 'update')
                || Permissions::check($user, 'monitoring.vehicles', $action)) {
                $specialAllow = true;
            }
        }
        if ($action === 'read' && $base === 'vehicles' && count($parts) >= 3) {
            for ($i = 2; $i < count($parts); $i++) {
                if (strtolower($parts[$i]) === 'options') {
                    if (Permissions::check($user, 'drivers', 'read')
                        || Permissions::check($user, 'drivers', 'create')
                        || Permissions::check($user, 'drivers', 'update')) {
                        $specialAllow = true;
                    }
                    break;
                }
                if (strtolower($parts[$i]) === 'models' && strtolower($parts[$i + 1] ?? '') === 'options') {
                    $specialAllow = true;
                    break;
                }
            }
        }

        if ($specialAllow) {
            return $next($request);
        }

        $key = $this->resolveModuleKey($parts, $base);

        if ($key && Permissions::check($user, $key, $action)) {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isDistributor()) {
            $k = strtolower((string) $key);
            if ($k === 'users' || $k === 'users.permissions' || str_starts_with($k, 'users')) {
                return $next($request);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }

    private function isSelfUserAccess(array $parts, $user, string $action): bool
    {
        if (!$user || !in_array($action, ['read', 'update'], true)) {
            return false;
        }
        $idx = null;
        foreach ($parts as $i => $p) {
            if (strtolower($p) === 'users') {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return false;
        }
        $idSeg = $parts[$idx + 1] ?? null;

        return $idSeg !== null && is_numeric($idSeg) && (int) $idSeg === (int) $user->id;
    }

    private function resolveModuleKey(array $parts, string $base): ?string
    {
        $allModules = array_keys(ModulePermission::modules());
        $canonical = null;
        foreach ($allModules as $mk) {
            $mkTokens = preg_split('/[\._-]+/', strtolower($mk), -1, PREG_SPLIT_NO_EMPTY);
            if (($mkTokens[0] ?? '') !== $base) {
                continue;
            }
            $isMatch = true;
            for ($i = 1; $i < count($mkTokens); $i++) {
                if (!isset($parts[$i + 1]) || strtolower($parts[$i + 1]) !== $mkTokens[$i]) {
                    $isMatch = false;
                    break;
                }
            }
            if (!$isMatch) {
                continue;
            }
            if ($canonical === null) {
                $canonical = $mk;
            } else {
                $canonTokens = preg_split('/[\._-]+/', strtolower($canonical), -1, PREG_SPLIT_NO_EMPTY);
                if (count($mkTokens) > count($canonTokens)) {
                    $canonical = $mk;
                }
            }
        }

        return $canonical ?: $base;
    }
}
