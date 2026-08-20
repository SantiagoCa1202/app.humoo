<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    private SyncTeamMembers $syncTeamMembers;

    public function __construct(SyncTeamMembers $syncTeamMembers)
    {
        $this->syncTeamMembers = $syncTeamMembers;
    }

    public function execute(string $workspaceId, string $userId, array $payload): Team
    {
        return DB::transaction(function () use ($workspaceId, $userId, $payload): Team {
            $team = Team::query()->create([
                'workspace_id' => $workspaceId,
                'name' => trim((string) $payload['name']),
                'key' => $this->trimOrNull($payload['key'] ?? null),
                'description' => $this->trimOrNull($payload['description'] ?? null),
                'type' => $this->trimOrNull($payload['type'] ?? null),
                'status' => $payload['status'] ?? 'active',
                'lead_membership_id' => $payload['lead_membership_id'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $this->syncTeamMembers->execute(
                $team,
                $workspaceId,
                $payload['member_ids'] ?? [],
                $payload['lead_membership_id'] ?? null
            );
        });
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
