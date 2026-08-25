<?php

namespace App\Application\Actions\Team;

use App\Models\WorkspaceMembership;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class UpdateWorkspaceMembership
{
    public function execute(WorkspaceMembership $membership, string $actorId, array $attributes): WorkspaceMembership
    {
        if ($membership->user_id === $actorId) throw ValidationException::withMessages(['membership' => ['You cannot modify your own membership from chat.']]);
        if (array_key_exists('role_id', $attributes) && $attributes['role_id'] !== null && !Role::query()->whereKey($attributes['role_id'])->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $membership->workspace_id))->exists()) {
            throw ValidationException::withMessages(['role_id' => ['The selected role is not available in this workspace.']]);
        }
        if (array_key_exists('status', $attributes) && !in_array($attributes['status'], ['active', 'suspended', 'removed'], true)) {
            throw ValidationException::withMessages(['status' => ['The selected membership status is invalid.']]);
        }
        $membership->forceFill(array_intersect_key($attributes, array_flip(['role_id', 'status'])))->save();
        return $membership->fresh(['user', 'role.permissions', 'workspace']);
    }
}
