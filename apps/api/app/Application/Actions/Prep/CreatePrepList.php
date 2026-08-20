<?php

namespace App\Application\Actions\Prep;

use App\Models\Event;
use App\Models\PrepList;

class CreatePrepList
{
    public function execute(
        string $workspaceId,
        string $userId,
        array $attributes
    ): PrepList {
        $event = Event::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($attributes['event_id']);

        return PrepList::query()->create([
            'workspace_id' => $workspaceId,
            'event_id' => $event->id,
            'name' => trim((string) $attributes['name']),
            'production_starts_at' => $attributes['production_starts_at'] ?? $event->production_starts_at ?? null,
            'production_ends_at' => $attributes['production_ends_at'] ?? $event->production_ends_at ?? $event->starts_at,
            'timezone' => $this->trimOrNull($attributes['timezone'] ?? $event->timezone),
            'status' => $attributes['status'] ?? 'draft',
            'metadata' => $attributes['metadata'] ?? null,
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
