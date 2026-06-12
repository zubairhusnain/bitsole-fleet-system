<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Mobile API: fleet users only — admins/distributors use the web console.
 */
class ApiFleetNotificationAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($user->isAdmin() || $user->isDistributor()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
