<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = env('DEMO_READ_ONLY');
        $enabled = $raw === null ? true : filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return $next($request);
        }

        if (!$request->user()) {
            return $next($request);
        }

        $method = strtoupper((string) $request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $path = ltrim((string) $request->path(), '/');
        $allowedPrefixes = [
            'web/auth/login',
            'web/auth/register',
            'web/auth/logout',
            'web/auth/impersonate',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        $message = 'This is a demo account. You do not have permission to create/update/delete data. You can only read/view data.';
        $payload = [
            'message' => $message,
            'demo_read_only' => true,
            'code' => 'DEMO_READ_ONLY',
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 403);
        }

        return redirect()->back()->withErrors(['demo' => $message]);
    }
}
