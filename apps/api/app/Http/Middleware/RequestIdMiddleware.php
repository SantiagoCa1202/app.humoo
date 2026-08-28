<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedId = $request->header('X-Request-Id');
        $requestId = is_string($requestedId) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $requestedId) === 1
            ? $requestedId
            : (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        if (str_contains($contentType, 'application/json') && !str_contains($contentType, 'charset=')) {
            $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        }

        return $response;
    }
}
