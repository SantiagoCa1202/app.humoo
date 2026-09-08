<?php

namespace App\AI\Streaming;

use App\Events\Realtime\ChatStreamed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Realtime delivery is an optional projection of durable chat state. A
 * broadcaster outage must never roll back or interrupt persisted AI work.
 */
final class BestEffortChatBroadcaster
{
    public function __construct(private Dispatcher $events) {}

    /** @param array<string, mixed> $payload */
    public function publish(
        string $conversationId,
        string $messageId,
        string $type,
        array $payload = [],
    ): bool {
        try {
            $this->events->dispatch(new ChatStreamed(
                $conversationId,
                $messageId,
                $type,
                $payload,
            ));

            return true;
        } catch (Throwable $exception) {
            Log::warning('chat.realtime.delivery_failed', [
                'conversation_id' => $conversationId,
                'exception_class' => class_basename($exception),
                'message_id' => $messageId,
                'payload_bytes' => strlen(json_encode($payload) ?: ''),
                'type' => $type,
            ]);

            return false;
        }
    }
}
