<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolExecutionContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Tests\TestCase;

class ToolExecutionContextTest extends TestCase
{
    public function test_it_preserves_chat_scope_for_tool_execution(): void
    {
        $workspace = new Workspace;
        $workspace->setAttribute('id', '01j00000000000000000000001');
        $user = new User;
        $user->setAttribute('id', '01j00000000000000000000002');
        $membership = new WorkspaceMembership;
        $membership->setAttribute('id', '01j00000000000000000000003');
        $conversation = new Conversation;
        $conversation->setAttribute('id', '01j00000000000000000000004');
        $message = new Message;
        $message->setAttribute('id', '01j00000000000000000000005');

        $context = new ToolExecutionContext(
            workspace: $workspace,
            user: $user,
            membership: $membership,
            conversation: $conversation,
            locale: 'es',
            timezone: 'America/New_York',
            message: $message,
        );

        $payload = $context->toArray(['source_message' => $message]);

        $this->assertSame($conversation, $payload['conversation']);
        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame($workspace, $payload['workspace']);
        $this->assertSame($user, $payload['user']);
        $this->assertSame($membership, $payload['membership']);
        $this->assertSame('es', $payload['locale']);
        $this->assertSame('America/New_York', $payload['timezone']);
        $this->assertSame($message, $payload['user_message']);
    }
}
