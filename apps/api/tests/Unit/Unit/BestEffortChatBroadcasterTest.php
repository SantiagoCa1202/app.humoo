<?php

namespace Tests\Unit\Unit;

use App\AI\Streaming\BestEffortChatBroadcaster;
use App\Events\Realtime\ChatStreamed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BestEffortChatBroadcasterTest extends TestCase
{
    public function test_broadcast_failure_is_logged_and_never_escapes(): void
    {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(ChatStreamed::class))
            ->andThrow(new RuntimeException('Provider payload limit.'));
        Log::spy();

        $delivered = (new BestEffortChatBroadcaster($events))->publish(
            'conversation-id',
            'message-id',
            'ai_run.updated',
            ['aiRun' => ['status' => 'running']],
        );

        $this->assertFalse($delivered);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('chat.realtime.delivery_failed', Mockery::on(
                fn (array $context): bool => $context['type'] === 'ai_run.updated'
                    && $context['exception_class'] === 'RuntimeException'
                    && $context['payload_bytes'] > 0,
            ));
    }
}
