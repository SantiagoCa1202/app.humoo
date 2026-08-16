<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $currentTokenId = (string) $request->user()
            ?->currentAccessToken()
            ?->getKey();

        $sessions = $request->user()
            ->sessions()
            ->with(['device', 'workspace'])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function ($session) use ($currentTokenId) {
                return [
                    ...$session->toArray(),
                    'is_current' => $session->token_id === $currentTokenId,
                ];
            })
            ->values();

        return response()->json([
            'data' => $sessions,
        ]);
    }
}
