<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepItemAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->relationLoaded('membership') ? $this->membership : null;

        return [
            'id' => $this->id,
            'membership_id' => $this->membership_id,
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notes' => $this->notes,
            'assigned_by' => $this->relationLoaded('assignedBy') && $this->assignedBy
                ? (new UserReferenceResource($this->assignedBy))->resolve()
                : null,
            'membership' => $membership
                ? (new WorkspaceMemberReferenceResource($membership))->resolve()
                : null,
            'user' => $membership && $membership->relationLoaded('user') && $membership->user
                ? [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                ]
                : null,
            'role_label' => $membership && $membership->relationLoaded('role') && $membership->role
                ? $membership->role->name
                : null,
        ];
    }
}
