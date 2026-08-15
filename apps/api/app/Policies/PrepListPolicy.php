<?php

namespace App\Policies;

use App\Models\PrepList;
use App\Models\User;

class PrepListPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'prep_lists.view'
        );
    }

    public function view(User $user, PrepList $prepList): bool
    {
        $workspace = app('currentWorkspace');

        return $prepList->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'prep_lists.view'
            );
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'prep_lists.create'
        );
    }

    public function update(User $user, PrepList $prepList): bool
    {
        $workspace = app('currentWorkspace');

        return $prepList->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'prep_lists.edit'
            );
    }

    public function delete(User $user, PrepList $prepList): bool
    {
        return $this->update($user, $prepList);
    }

    public function restore(User $user, PrepList $prepList): bool
    {
        return $this->update($user, $prepList);
    }

    public function forceDelete(User $user, PrepList $prepList): bool
    {
        return false;
    }
}
