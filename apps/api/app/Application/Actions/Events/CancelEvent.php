<?php

namespace App\Application\Actions\Events;

use App\Models\Event;

class CancelEvent
{
    public function __construct(private UpdateEvent $updateEvent)
    {
    }

    public function execute(Event $event, ?string $userId = null): ?Event
    {
        return $this->updateEvent->execute(
            $event,
            (int) $event->version,
            ['status' => 'cancelled'],
            $userId
        );
    }
}
