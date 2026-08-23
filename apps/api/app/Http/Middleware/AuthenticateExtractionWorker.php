<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExtractionWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('extraction.worker_token', '');
        $providedToken = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $workerId = trim((string) $request->header('X-Worker-ID'));
        $configuredWorkerId = trim((string) config('extraction.worker_id', ''));

        if ($configuredToken === '' || $workerId === '' || !hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Worker authentication failed.'], 401);
        }

        if ($configuredWorkerId !== '' && !hash_equals($configuredWorkerId, $workerId)) {
            return response()->json(['message' => 'Worker is not registered.'], 403);
        }

        $request->attributes->set('extraction_worker_id', $workerId);

        return $next($request);
    }
}
