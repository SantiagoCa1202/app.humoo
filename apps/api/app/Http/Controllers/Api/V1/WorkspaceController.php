<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workspace;
use App\Services\WorkspaceContextService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Workspace::class);

        $memberships = $request->user()
            ->memberships()
            ->with([
                'workspace',
                'role.permissions',
            ])
            ->where('status', 'active')
            ->get();

        return response()->json([
            'data' => $memberships->map(
                fn ($membership): array => [
                    'id' => $membership->id,
                    'status' => $membership->status,
                    'joined_at' => $membership->joined_at,
                    'workspace' => $membership->workspace,
                    'role' => $membership->role,
                    'permissions' => $membership->role?->permissions
                        ? $membership->role->permissions
                            ->pluck('key')
                            ->values()
                            ->all()
                        : [],
                ]
            )->values(),
        ]);
    }

    public function current(
        Request $request,
        WorkspaceContextService $workspaceContext
    ) {
        $workspace = app('currentWorkspace');
        $membership = app('currentMembership');

        $this->authorize('view', $workspace);

        return response()->json([
            'data' => $workspaceContext->buildForMembership($membership),
        ]);
    }

    public function members(Request $request)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission(
                $workspace->id,
                'members.view'
            ),
            403,
            'You do not have permission to view members.'
        );

        $memberships = $workspace->memberships()
            ->with(['user', 'role.permissions'])
            ->whereIn('status', [
                'pending',
                'active',
                'suspended',
                'removed',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $memberships,
        ]);
    }

    public function roles(Request $request)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission(
                $workspace->id,
                'members.view'
            ),
            403,
            'You do not have permission to view roles.'
        );

        $roles = Role::query()
            ->with('permissions')
            ->where(function ($query) use ($workspace): void {
                $query->whereNull('workspace_id')
                    ->orWhere('workspace_id', $workspace->id);
            })
            ->orderByRaw('workspace_id is null desc')
            ->orderBy('key')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }
}
