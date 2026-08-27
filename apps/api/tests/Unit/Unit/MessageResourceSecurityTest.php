<?php

namespace Tests\Unit\Unit;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Tests\TestCase;

class MessageResourceSecurityTest extends TestCase
{
    public function test_chat_message_resource_does_not_expose_persisted_internal_error_messages(): void
    {
        $message = new Message([
            'error_code' => 'INTERNAL_ERROR',
            'error_message' => 'SQLSTATE[42S02]: select * from intent_patterns',
            'metadata' => [],
            'sender_type' => 'assistant',
            'status' => 'failed',
        ]);

        $payload = (new MessageResource($message))->toArray(request());

        $this->assertSame('INTERNAL_ERROR', $payload['error_code']);
        $this->assertNull($payload['error_message']);
    }
}
