<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdateMemberRequest;
use App\Models\WorkspaceMembership;
use App\Services\AuditLogger;

class MemberController extends Controller
{
    public function update(
        UpdateMemberRequest $request,
        WorkspaceMembership $membership,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $membership->workspace_id === $workspace->id,
            404,
        );

        abort_if(
            $membership->user_id === $request->user()->id,
            422,
            'You cannot modify your own membership from this endpoint.',
        );

        $before = $membership->load([
            'user',
            'role.permissions',
        ])->toArray();

        $membership->forceFill([
            'role_id' => $request->has('role_id')
                ? $request->validated('role_id')
                : $membership->role_id,
            'status' => $request->validated('status', $membership->status),
        ])->save();

        $membership = $membership->fresh([
            'user',
            'role.permissions',
            'workspace',
        ]);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'membership.updated',
            $membership::class,
            $membership->id,
            $before,
            $membership->toArray()
        );

        return response()->json([
            'data' => $membership,
        ]);
    }

    public function destroy(
        UpdateMemberRequest $request,
        WorkspaceMembership $membership,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $membership->workspace_id === $workspace->id,
            404,
        );

        abort_if(
            $membership->user_id === $request->user()->id,
            422,
            'You cannot remove your own membership from this endpoint.',
        );

        $before = $membership->load([
            'user',
            'role.permissions',
        ])->toArray();

        $membership->forceFill([
            'status' => 'removed',
        ])->save();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'membership.removed',
            $membership::class,
            $membership->id,
            $before,
            $membership->fresh()->toArray()
        );

        return response()->noContent();
    }
}
