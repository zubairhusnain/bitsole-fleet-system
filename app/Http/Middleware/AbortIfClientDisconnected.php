<?php

namespace App\Http\Middleware;

use App\Support\ClientDisconnect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbortIfClientDisconnected
{
    /** @var list<string> */
    private const SKIP_SUFFIXES = [
        'device-options',
        'group-options',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        ClientDisconnect::bootstrap();

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        $path = trim($request->path(), '/');
        foreach (self::SKIP_SUFFIXES as $suffix) {
            if ($path === 'web/reports/'.$suffix || str_ends_with($path, '/'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
