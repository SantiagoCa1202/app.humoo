<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$user || !$token) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $session = UserSession::query()
            ->where('user_id', $user->id)
            ->where('token_id', (string) $token->getKey())
            ->whereNull('revoked_at')
            ->first();

        if (!$session) {
            $token->delete();

            return response()->json([
                'message' => 'Session has been revoked.',
            ], 401);
        }

        return $next($request);
    }
}
