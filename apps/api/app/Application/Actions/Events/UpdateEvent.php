<?php

namespace App\Application\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    private PrepareEventAttributes $prepareEventAttributes;

    public function __construct(
        PrepareEventAttributes $prepareEventAttributes
    ) {
        $this->prepareEventAttributes = $prepareEventAttributes;
    }

    public function execute(
        Event $event,
        int $expectedVersion,
        array $attributes,
        ?string $userId = null
    ): ?Event {
        return DB::transaction(function () use (
            $attributes,
            $event,
            $expectedVersion,
            $userId
        ): ?Event {
            $payload = $this->prepareEventAttributes->execute(
                $event->workspace_id,
                $attributes,
                $event,
                $userId
            );

            $updated = Event::query()
                ->whereKey($event->getKey())
                ->where('workspace_id', $event->workspace_id)
                ->where('version', $expectedVersion)
                ->update([
                    ...$payload,
                    'updated_at' => now(),
                    'updated_by' => $userId,
                    'version' => $expectedVersion + 1,
                ]);

            if ($updated === 0) {
                return null;
            }

            return Event::query()
                ->whereKey($event->getKey())
                ->where('workspace_id', $event->workspace_id)
                ->first();
        });
    }
}
