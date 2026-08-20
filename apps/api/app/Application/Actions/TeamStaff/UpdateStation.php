<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Station;

class UpdateStation
{
    public function execute(Station $station, string $userId, array $payload): Station
    {
        $station->forceFill([
            'name' => trim((string) ($payload['name'] ?? $station->name)),
            'key' => $this->trimOrNull($payload['key'] ?? $station->key),
            'description' => $this->trimOrNull($payload['description'] ?? $station->description),
            'team_id' => $payload['team_id'] ?? $station->team_id,
            'type' => $this->trimOrNull($payload['type'] ?? $station->type),
            'capacity' => array_key_exists('capacity', $payload) ? $payload['capacity'] : $station->capacity,
            'position' => array_key_exists('position', $payload) ? $payload['position'] : $station->position,
            'status' => $payload['status'] ?? $station->status,
            'updated_by' => $userId,
        ])->save();

        return $station->fresh(['team']);
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
