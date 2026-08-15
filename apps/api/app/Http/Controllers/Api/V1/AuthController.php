<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where(
            'email',
            $data['email']
        )->first();

        if (
            !$user ||
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

        $token = $user
            ->createToken($data['device_name'])
            ->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
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
            : null;

        $permissions = $currentMembership?->role
            ? $currentMembership->role->permissions
                ->pluck('key')
                ->values()
                ->all()
            : [];

        return response()->json([
            'data' => [
                'user' => $request->user(),
                'memberships' => $memberships,
                'current_workspace' => $currentMembership?->workspace,
                'current_membership' => $currentMembership,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->noContent();
    }
}
