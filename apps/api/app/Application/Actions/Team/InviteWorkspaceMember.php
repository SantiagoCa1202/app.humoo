<?php

namespace App\Application\Actions\Team;

use App\Services\InvitationService;
use App\Models\Workspace;

class InviteWorkspaceMember
{
    public function __construct(private InvitationService $invitationService) {}

    public function execute(Workspace $workspace, string $actorId, string $email, ?string $roleId = null): array
    {
        [$invitation] = $this->invitationService->create($workspace, $workspace->memberships()->where('user_id', $actorId)->first()?->user, $email, $roleId);
        return $invitation->toArray();
    }
}
