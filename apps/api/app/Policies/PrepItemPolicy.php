<?php

namespace App\Policies;

use App\Models\PrepItem;
use App\Models\User;

class PrepItemPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'prep_lists.view'
        );
    }

    public function view(User $user, PrepItem $prepItem): bool
    {
        $workspace = app('currentWorkspace');

        return $prepItem->workspace_id === $workspace->id
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

    public function update(User $user, PrepItem $prepItem): bool
    {
        $workspace = app('currentWorkspace');

        return $prepItem->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'prep_lists.edit'
            );
    }

    public function delete(User $user, PrepItem $prepItem): bool
    {
        return $this->update($user, $prepItem);
    }

    public function restore(User $user, PrepItem $prepItem): bool
    {
        return $this->update($user, $prepItem);
    }

    public function forceDelete(User $user, PrepItem $prepItem): bool
    {
        return false;
    }
}
