<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        $workspace = app('currentWorkspace');

        return $contact->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'contacts.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        $workspace = app('currentWorkspace');

        return $contact->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'contacts.edit');
    }

    public function delete(User $user, Contact $contact): bool
    {
        $workspace = app('currentWorkspace');

        return $contact->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'contacts.delete');
    }
}
