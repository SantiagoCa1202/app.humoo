<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

class SessionTracker
{
    public function start(
        User $user,
        Request $request,
        NewAccessToken $token
    ): UserSession {
        $deviceName = (string) $request->input('device_name', 'humoo-api');
        $platform = $this->detectPlatform($request, $deviceName);

        $device = Device::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'name' => $deviceName,
            ],
            [
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
            ]
        );

        return UserSession::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'device_id' => $device->id,
            'token_id' => (string) $token->accessToken->getKey(),
            'last_seen_at' => now(),
        ]);
    }

    public function touch(
        Request $request,
        ?string $workspaceId = null
    ): ?UserSession {
        $session = $this->findCurrent($request);

        if (!$session) {
            return null;
        }

        $session->forceFill([
            'workspace_id' => $workspaceId ?? $session->workspace_id,
            'last_seen_at' => now(),
        ])->save();

        $session->device?->forceFill([
            'last_ip' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();

        return $session->fresh(['device', 'workspace']);
    }

    public function revokeCurrent(
        Request $request,
        ?string $workspaceId = null
    ): ?UserSession {
        $session = $this->touch($request, $workspaceId);

        if (!$session) {
            return null;
        }

        $session->forceFill([
            'workspace_id' => $workspaceId ?? $session->workspace_id,
            'revoked_at' => now(),
        ])->save();

        return $session;
    }

    private function findCurrent(Request $request): ?UserSession
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token) {
            return null;
        }

        return UserSession::query()
            ->where('user_id', $request->user()->id)
            ->where('token_id', (string) $token->getKey())
            ->whereNull('revoked_at')
            ->first();
    }

    private function detectPlatform(
        Request $request,
        string $deviceName
    ): string {
        $normalizedName = strtolower($deviceName);

        foreach (['web', 'ios', 'android'] as $platform) {
            if (str_contains($normalizedName, $platform)) {
                return $platform;
            }
        }

        $userAgent = strtolower((string) $request->userAgent());

        if (str_contains($userAgent, 'android')) {
            return 'android';
        }

        if (
            str_contains($userAgent, 'iphone') ||
            str_contains($userAgent, 'ipad') ||
            str_contains($userAgent, 'cfnetwork')
        ) {
            return 'ios';
        }

        if (str_contains($userAgent, 'mozilla')) {
            return 'web';
        }

        return 'api';
    }
}
