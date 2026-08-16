<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\UserSession;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\InvitationService;
use App\Services\SessionTracker;
use App\Services\WorkspaceContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        SessionTracker $sessionTracker
    )
    {
        $data = $request->validated();

        $user = User::where(
            'email',
            $data['email']
        )->first();

        if (
            !$user ||
            $user->status !== 'active' ||
            !$user->password ||
            !Hash::check(
                $data['password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Invalid credentials.'
                ],
            ]);
        }

        $token = $user->createToken($data['device_name']);
        $sessionTracker->start($user, $request, $token);

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }

    public function register(
        RegisterRequest $request,
        SessionTracker $sessionTracker,
        InvitationService $invitationService
    ) {
        $data = $request->validated();

        $user = !empty($data['invitation_token'])
            ? $invitationService->acceptForRegistration($data)['user']
            : User::query()->create([
                'name' => trim("{$data['first_name']} {$data['last_name']}"),
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'] ?: config('app.locale'),
                'timezone' => $data['timezone'] ?: 'UTC',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

        $token = $user->createToken($data['device_name']);
        $sessionTracker->start($user, $request, $token);

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        $response = [
            'message' => 'If the account exists, a password reset has been prepared.',
        ];

        if (!$user) {
            return response()->json($response);
        }

        $token = Password::broker()->createToken($user);
        $user->notify(new ResetPasswordNotification($token));

        if ($this->shouldExposePreviewTokens()) {
            $response['data'] = [
                'email' => $user->email,
                'reset_token_preview' => $token,
                'reset_url_preview' => sprintf(
                    '%s/reset-password?email=%s&token=%s',
                    rtrim((string) config('app.frontend_url', config('app.url')), '/'),
                    urlencode($user->email),
                    urlencode($token),
                ),
            ];
        }

        return response()->json($response);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                UserSession::query()
                    ->where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function me(
        Request $request,
        SessionTracker $sessionTracker,
        WorkspaceContextService $workspaceContext
    )
    {
        $workspaceId = $request->header('X-Workspace-ID');

        $memberships = $request->user()
            ->memberships()
            ->with([
                'workspace',
                'role.permissions',
            ])
            ->where('status', 'active')
            ->get();

        $currentMembership = $workspaceId
            ? $memberships->firstWhere('workspace_id', $workspaceId)
            : $memberships->first();

        if ($workspaceId) {
            abort_unless(
                $currentMembership,
                403,
                'You do not belong to this workspace.'
            );
        }

        $permissions = $currentMembership?->role
            ? $currentMembership->role->permissions
                ->pluck('key')
                ->values()
                ->all()
            : [];
        $context = $currentMembership
            ? $workspaceContext->buildForMembership($currentMembership)
            : null;

        $sessionTracker->touch(
            $request,
            $currentMembership?->workspace_id
        );

        return response()->json([
            'data' => [
                'user' => $request->user(),
                'memberships' => $memberships,
                'current_workspace' => $currentMembership?->workspace,
                'current_membership' => $currentMembership,
                'permissions' => $permissions,
                'current_plan' => $context['plan'] ?? null,
                'current_subscription' => $context['subscription'] ?? null,
                'entitlements' => $context['entitlements'] ?? [],
            ],
        ]);
    }

    public function logout(
        Request $request,
        SessionTracker $sessionTracker
    )
    {
        $sessionTracker->revokeCurrent(
            $request,
            $request->header('X-Workspace-ID')
        );

        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->noContent();
    }

    private function shouldExposePreviewTokens(): bool
    {
        return app()->isLocal() || app()->environment('testing');
    }
}
