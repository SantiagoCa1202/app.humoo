<?php

namespace App\Http\Resources;

use App\Models\WorkspaceMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WorkspaceMembership $membership */
        $membership = $this->resource;
        $role = $membership->relationLoaded('role') ? $membership->role : null;
        $user = $membership->relationLoaded('user') ? $membership->user : null;
        $teams = $membership->relationLoaded('teams') ? $membership->teams : collect();

        return [
            'id' => $membership->id,
            'workspace_id' => $membership->workspace_id,
            'user_id' => $membership->user_id,
            'role_id' => $membership->role_id,
            'status' => $membership->status,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'name' => $user?->name,
            'email' => $user?->email,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'role' => $role ? [
                'id' => $role->id,
                'key' => $role->key,
                'name' => $role->name,
            ] : null,
            'teams' => $teams->map(function ($team): array {
                return [
                    'id' => $team->id,
                    'key' => $team->key,
                    'name' => $team->name,
                    'status' => $team->status,
                ];
            })->values()->all(),
        ];
    }
}
