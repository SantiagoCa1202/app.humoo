<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Team;
use Illuminate\Support\Carbon;

class SyncTeamMembers
{
    public function execute(
        Team $team,
        string $workspaceId,
        array $memberIds,
        ?string $leadMembershipId = null
    ): Team {
        $memberIds = array_values(array_unique(array_filter($memberIds)));

        if ($leadMembershipId && !in_array($leadMembershipId, $memberIds, true)) {
            $memberIds[] = $leadMembershipId;
        }

        $now = Carbon::now();
        $syncPayload = [];

        foreach ($memberIds as $memberId) {
            $syncPayload[$memberId] = [
                'workspace_id' => $workspaceId,
                'is_lead' => $leadMembershipId === $memberId,
                'joined_at' => $now,
                'left_at' => null,
                'status' => 'active',
            ];
        }

        $team->members()->sync($syncPayload);
        $team->forceFill([
            'lead_membership_id' => $leadMembershipId,
        ])->save();

        return $team->fresh([
            'leadMembership.role',
            'leadMembership.user',
            'members.role',
            'members.teams',
            'members.user',
        ]);
    }
}
