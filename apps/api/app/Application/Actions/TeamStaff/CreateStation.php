<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Station;

class CreateStation
{
    public function execute(string $workspaceId, string $userId, array $payload): Station
    {
        return Station::query()->create([
            'workspace_id' => $workspaceId,
            'name' => trim((string) $payload['name']),
            'key' => $this->trimOrNull($payload['key'] ?? null),
            'description' => $this->trimOrNull($payload['description'] ?? null),
            'team_id' => $payload['team_id'] ?? null,
            'type' => $this->trimOrNull($payload['type'] ?? null),
            'capacity' => $payload['capacity'] ?? null,
            'position' => $payload['position'] ?? 0,
            'status' => $payload['status'] ?? 'active',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
