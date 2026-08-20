<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $members = $this->relationLoaded('members')
            ? $this->members
            : collect();

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'lead_membership_id' => $this->lead_membership_id,
            'lead_member' => $this->relationLoaded('leadMembership') && $this->leadMembership
                ? (new WorkspaceMemberReferenceResource($this->leadMembership))->resolve()
                : null,
            'members' => $members->map(function ($membership): array {
                $resource = new WorkspaceMemberReferenceResource($membership);
                $payload = $resource->resolve();
                $payload['team_role'] = $membership->pivot?->role;
                $payload['is_team_lead'] = (bool) ($membership->pivot?->is_lead ?? false);
                $payload['team_member_status'] = $membership->pivot?->status;
                $payload['team_joined_at'] = $membership->pivot?->joined_at?->toIso8601String();

                return $payload;
            })->values()->all(),
            'member_count' => $members->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
