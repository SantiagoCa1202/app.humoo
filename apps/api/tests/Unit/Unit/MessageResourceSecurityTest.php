<?php

namespace Tests\Unit\Unit;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\MessageBlock;
use Illuminate\Support\Collection;
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

    public function test_chat_message_resource_hides_plain_text_when_a_remote_component_exists(): void
    {
        $message = new Message([
            'content_text' => 'The recipe is ready.',
            'metadata' => [],
            'sender_type' => 'assistant',
            'status' => 'completed',
        ]);
        $message->setRelation('blocks', new Collection([
            new MessageBlock([
                'block_type' => 'text',
                'payload_json' => ['text' => 'The recipe is ready.'],
                'position' => 0,
            ]),
            new MessageBlock([
                'block_type' => 'component',
                'component_key' => 'recipes.list',
                'payload_json' => ['data' => ['recipes' => []]],
                'position' => 1,
                'schema_version' => 1,
            ]),
        ]));

        $payload = (new MessageResource($message))->toArray(request());

        $this->assertNull($payload['content_text']);
        $this->assertCount(1, $payload['blocks']);
        $this->assertSame('component', $payload['blocks'][0]['type']);
    }
}
