<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function destroy(
        Request $request,
        UserSession $session
    )
    {
        abort_unless(
            $session->user_id === $request->user()->id,
            404,
        );

        if ($session->token_id) {
            DB::table('personal_access_tokens')
                ->where('id', $session->token_id)
                ->delete();
        }

        $session->forceFill([
            'revoked_at' => $session->revoked_at ?? now(),
        ])->save();

        return response()->noContent();
    }
}
