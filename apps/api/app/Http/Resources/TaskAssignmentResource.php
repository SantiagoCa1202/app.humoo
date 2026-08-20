<?php

namespace App\Http\Resources;

use App\Models\TaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TaskAssignment $assignment */
        $assignment = $this->resource;
        $membership = $assignment->relationLoaded('membership') ? $assignment->membership : null;
        $user = $membership && $membership->relationLoaded('user') ? $membership->user : null;
        $role = $membership && $membership->relationLoaded('role') ? $membership->role : null;

        return [
            'id' => $assignment->id,
            'workspace_id' => $assignment->workspace_id,
            'task_id' => $assignment->task_id,
            'membership_id' => $assignment->membership_id,
            'status' => $assignment->status,
            'is_primary' => $assignment->is_primary,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'accepted_at' => $assignment->accepted_at?->toIso8601String(),
            'completed_at' => $assignment->completed_at?->toIso8601String(),
            'role_label' => $role?->name ?? $role?->key,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ];
    }
}
