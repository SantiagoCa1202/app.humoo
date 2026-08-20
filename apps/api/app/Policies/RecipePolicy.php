<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'recipes.view'
        );
    }

    public function view(User $user, Recipe $recipe): bool
    {
        $workspace = app('currentWorkspace');

        return $recipe->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'recipes.view'
            );
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'recipes.create'
        );
    }

    public function update(User $user, Recipe $recipe): bool
    {
        $workspace = app('currentWorkspace');

        return $recipe->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'recipes.edit'
            );
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $this->update($user, $recipe);
    }
}
