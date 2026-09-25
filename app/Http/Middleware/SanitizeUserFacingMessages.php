<?php

namespace App\Http\Middleware;

use App\Support\UserFacingMessage;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeUserFacingMessages
{
    /**
     * Only rewrite user-visible message/error text on JSON responses.
     * Does not modify data payloads, attribute names, or nested records.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        if (! is_array($data)) {
            return $response;
        }

        $response->setData(UserFacingMessage::sanitizeApiResponse($data));

        return $response;
    }
}
