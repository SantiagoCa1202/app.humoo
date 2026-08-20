<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'menus.view');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $menu->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($menu->workspace_id, 'menus.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'menus.create');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $menu->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($menu->workspace_id, 'menus.edit');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }

    public function restore(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }

    public function forceDelete(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }
}
