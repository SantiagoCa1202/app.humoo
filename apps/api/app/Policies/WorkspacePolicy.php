<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activeMemberships()->exists();
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $user->memberships()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission(
            $workspace->id,
            'members.manage'
        );
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return false;
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return false;
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return false;
    }
}
