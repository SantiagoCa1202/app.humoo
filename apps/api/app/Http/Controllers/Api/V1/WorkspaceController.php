<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\Role;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\WorkspaceContextService;
use App\Services\WorkspaceProvisioner;
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
                fn ($membership): array => $this->serializeWorkspaceAccess($membership)
            )->values(),
        ]);
    }

    public function store(
        CreateWorkspaceRequest $request,
        WorkspaceProvisioner $workspaceProvisioner,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('create', Workspace::class);

        $membership = $workspaceProvisioner->createForUser(
            $request->user(),
            $request->validated()
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $membership->workspace_id,
            $request->user()->id,
            'workspace.created',
            Workspace::class,
            $membership->workspace_id,
            null,
            $membership->workspace?->toArray()
        );

        return response()->json([
            'data' => $this->serializeWorkspaceAccess($membership),
        ], 201);
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

    public function update(
        UpdateWorkspaceRequest $request,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        $this->authorize('update', $workspace);

        $before = $workspace->toArray();

        $workspace->forceFill($request->validated())->save();

        $workspace = $workspace->fresh();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'workspace.updated',
            $workspace::class,
            $workspace->id,
            $before,
            $workspace->toArray()
        );

        return response()->json([
            'data' => $workspace,
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

    private function serializeWorkspaceAccess($membership): array
    {
        return [
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
        ];
    }
}
