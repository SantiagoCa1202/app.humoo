<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
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

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'actor_id' => ['nullable', 'string'],
            'action' => ['nullable', 'string', 'max:120'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'entity_id' => ['nullable', 'string'],
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('actor')
            ->where('workspace_id', $workspace->id)
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($validated['actor_id'] ?? null, fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($validated['entity_type'] ?? null, fn ($query, $entityType) => $query->where('entity_type', $entityType))
            ->when($validated['entity_id'] ?? null, fn ($query, $entityId) => $query->where('entity_id', $entityId))
            ->orderByDesc('created_at')
            ->cursorPaginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => AuditLogResource::collection(collect($logs->items())),
            'path' => $logs->path(),
            'per_page' => $logs->perPage(),
            'next_cursor' => $logs->nextCursor()?->encode(),
            'next_page_url' => $logs->nextPageUrl(),
            'prev_cursor' => $logs->previousCursor()?->encode(),
            'prev_page_url' => $logs->previousPageUrl(),
        ]);
    }
}
