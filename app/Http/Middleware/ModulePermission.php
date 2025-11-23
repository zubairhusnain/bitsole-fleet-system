<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Permissions;
use App\Models\UserPermission;

class ModulePermission
{
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
            $segTokens = [];
            for ($i = 2; $i < count($parts); $i++) {
                $p = strtolower($parts[$i]);
                if ($p === '' || ctype_digit($p)) { continue; }
                $tokens = preg_split('/[^a-z0-9]+/i', $p, -1, PREG_SPLIT_NO_EMPTY);
                $segTokens = array_merge($segTokens, $tokens);
            }
            $userKeys = $request->session()->get('user_module_keys');
            if (!is_array($userKeys)) {
                try {
                    $userKeys = \App\Models\UserPermission::query()
                        ->where('user_id', $user->id)
                        ->pluck('module_key')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $request->session()->put('user_module_keys', $userKeys);
                } catch (\Throwable $e) {
                    $userKeys = [];
                }
            }
            $candidates = [$base];
            foreach ($segTokens as $t1) {
                $candidates[] = $base . '.' . $t1;
            }
            for ($i = 0; $i < count($segTokens) - 1; $i++) {
                $candidates[] = $base . '.' . $segTokens[$i] . '.' . $segTokens[$i+1];
            }
            $bestKey = null;
            $bestScore = -1;
            foreach ($userKeys as $mk) {
                $mkTokens = preg_split('/[\._-]+/', strtolower($mk), -1, PREG_SPLIT_NO_EMPTY);
                if (!in_array($base, $mkTokens, true)) { continue; }
                $score = 0;
                foreach ($mkTokens as $t) {
                    if ($t === $base) { continue; }
                    if (in_array($t, $segTokens, true)) { $score++; }
                }
                if ($score > $bestScore || ($score === $bestScore && count($mkTokens) > count(preg_split('/[\._-]+/', strtolower($bestKey ?? ''), -1, PREG_SPLIT_NO_EMPTY)))) {
                    $bestScore = $score;
                    $bestKey = $mk;
                }
            }
            if ($bestKey) {
                $key = $bestKey;
            } else {
                // For read requests, permit access via any child module (e.g., vehicles.overview) under this base
                if ($action === 'read') {
                    foreach ($userKeys as $mk) {
                        if (str_starts_with($mk, $base . '.')) { $key = $mk; break; }
                    }
                }
                if (!$key) {
                    $key = in_array($base, $userKeys, true) ? $base : null;
                }
            }
        }
        if ($key && Permissions::check($user, $key, $action)) { return $next($request); }
        if ($key) { return response()->json(['message' => 'Forbidden'], 403); }
        if ($user->isAdmin() || $user->isDistributor()) { return $next($request); }
        return response()->json(['message' => 'Forbidden'], 403);
    }
}
