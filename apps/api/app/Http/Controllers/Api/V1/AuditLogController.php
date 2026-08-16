<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission(
                $workspace->id,
                'audit.view'
            ),
            403,
            'You do not have permission to view audit logs.'
        );

        $logs = AuditLog::query()
            ->with('actor')
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->cursorPaginate(50);

        return response()->json($logs);
    }
}
