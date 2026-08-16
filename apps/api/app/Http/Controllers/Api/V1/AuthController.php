<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\SessionTracker;
use App\Services\WorkspaceContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
}
