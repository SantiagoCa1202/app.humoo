<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $workspaceId = $request->header('X-Workspace-ID');

        abort_unless(
            $workspaceId,
            400,
            'Workspace is required.'
        );

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless(
            $membership,
            403,
            'You do not belong to this workspace.'
        );

        app()->instance(
            'currentWorkspace',
            $membership->workspace
        );

        app()->instance(
            'currentMembership',
            $membership
        );

        return $next($request);
    }
}
