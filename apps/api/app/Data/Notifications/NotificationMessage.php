<?php

namespace App\Data\Notifications;

final class NotificationMessage
{
    public function __construct(
        public string $workspaceId,
        public string $recipientUserId,
        public string $eventKey,
        public string $type,
        public string $priority,
        public string $title,
        public ?string $body = null,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?string $actionKey = null,
        public array $actionPayload = [],
        public array $payload = [],
        public ?string $deduplicationKey = null,
        public string $source = 'system',
    ) {
    }
}
