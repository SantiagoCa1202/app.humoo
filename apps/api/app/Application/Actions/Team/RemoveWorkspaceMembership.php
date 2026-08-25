<?php

namespace App\Application\Actions\Team;

use App\Models\WorkspaceMembership;
use Illuminate\Validation\ValidationException;

class RemoveWorkspaceMembership
{
    public function execute(WorkspaceMembership $membership, string $actorId): WorkspaceMembership
    {
        if ($membership->user_id === $actorId) throw ValidationException::withMessages(['membership' => ['You cannot remove your own membership from chat.']]);
        $membership->forceFill(['status' => 'removed'])->save();
        return $membership->fresh(['user', 'role']);
    }
}
