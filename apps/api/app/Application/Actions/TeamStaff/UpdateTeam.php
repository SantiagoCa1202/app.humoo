<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeam
{
    private SyncTeamMembers $syncTeamMembers;

    public function __construct(SyncTeamMembers $syncTeamMembers)
    {
        $this->syncTeamMembers = $syncTeamMembers;
    }

    public function execute(
        Team $team,
        string $workspaceId,
        string $userId,
        array $payload
    ): Team {
        return DB::transaction(function () use ($team, $workspaceId, $userId, $payload): Team {
            $team->forceFill([
                'name' => trim((string) ($payload['name'] ?? $team->name)),
                'key' => $this->trimOrNull($payload['key'] ?? $team->key),
                'description' => $this->trimOrNull($payload['description'] ?? $team->description),
                'type' => $this->trimOrNull($payload['type'] ?? $team->type),
                'status' => $payload['status'] ?? $team->status,
                'lead_membership_id' => $payload['lead_membership_id'] ?? $team->lead_membership_id,
                'updated_by' => $userId,
            ])->save();

            if (array_key_exists('member_ids', $payload)) {
                return $this->syncTeamMembers->execute(
                    $team,
                    $workspaceId,
                    $payload['member_ids'] ?? [],
                    $payload['lead_membership_id'] ?? $team->lead_membership_id
                );
            }

            return $team->fresh([
                'leadMembership.role',
                'leadMembership.user',
                'members.role',
                'members.teams',
                'members.user',
            ]);
        });
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
