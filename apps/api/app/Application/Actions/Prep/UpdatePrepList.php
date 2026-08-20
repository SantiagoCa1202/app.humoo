<?php

namespace App\Application\Actions\Prep;

use App\Models\Event;
use App\Models\PrepList;

class UpdatePrepList
{
    public function execute(
        PrepList $prepList,
        string $workspaceId,
        string $userId,
        array $attributes
    ): PrepList {
        $eventId = $attributes['event_id'] ?? $prepList->event_id;

        Event::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($eventId);

        $prepList->fill([
            'event_id' => $eventId,
            'metadata' => $attributes['metadata'] ?? $prepList->metadata,
            'name' => trim((string) ($attributes['name'] ?? $prepList->name)),
            'production_ends_at' => array_key_exists('production_ends_at', $attributes)
                ? $attributes['production_ends_at']
                : $prepList->production_ends_at,
            'production_starts_at' => array_key_exists('production_starts_at', $attributes)
                ? $attributes['production_starts_at']
                : $prepList->production_starts_at,
            'status' => $attributes['status'] ?? $prepList->status,
            'timezone' => array_key_exists('timezone', $attributes)
                ? $this->trimOrNull($attributes['timezone'])
                : $prepList->timezone,
            'updated_by' => $userId,
        ]);

        $prepList->save();

        return $prepList;
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
