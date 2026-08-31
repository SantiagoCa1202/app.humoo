<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatStreamed implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $type,
        public array $payload = [],
        public ?string $occurredAt = null,
    ) {
        $this->occurredAt ??= now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.stream';
    }

    public function broadcastWith(): array
    {
        return [
            ...$this->payload,
            'conversationId' => $this->conversationId,
            'messageId' => $this->messageId,
            'occurredAt' => $this->occurredAt,
            'type' => $this->type,
        ];
    }
}
