<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\Models\ActionConfirmation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationContinuationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_resolves_the_provider_call_created_before_a_clarification(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Continuation lifecycle',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
            'metadata' => [
                'pending_provider_tool_outputs' => [[
                    'action_key' => 'tasks.assign',
                    'call_id' => 'call-pending-assignment',
                    'continuation_id' => 'clarification-member-1',
                    'output' => null,
                ]],
            ],
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $message = Message::query()->create([
            'content_text' => 'Asigna la tarea a Santiago.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $confirmation = ActionConfirmation::query()->create([
            'action_key' => 'tasks.assign',
            'draft_json' => [
                'provider_call_id' => 'call-pending-assignment',
            ],
            'expires_at' => now()->addMinutes(30),
            'message_id' => $message->id,
            'status' => 'pending',
            'token_hash' => hash('sha256', 'continuation-token'),
            'workspace_id' => $workspace->id,
        ]);

        $lifecycle = app(ConversationContinuationLifecycle::class);

        $this->assertSame(
            'call-pending-assignment',
            $lifecycle->pendingProviderToolCallId($conversation, 'clarification-member-1')
        );

        $revisionMessage = Message::query()->create([
            'content_text' => 'Cambia la asignaciÃ³n antes de confirmar.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);

        $this->assertTrue(
            $lifecycle->acknowledgeUserMessageBeforeConfirmation($confirmation, $revisionMessage)
        );
        $waitingOutput = $conversation->fresh()->metadata['pending_provider_tool_outputs'][0]['output'];
        $this->assertSame('revision_requested', $waitingOutput['data']['status']);
        $this->assertSame('pending', $confirmation->fresh()->status);

        $lifecycle->resolvePendingProviderToolCallForConfirmation($confirmation, [
            'workflow_status' => 'completed',
            'result_ref_json' => ['id' => 'task-1'],
        ]);

        $pending = $conversation->fresh()->metadata['pending_provider_tool_outputs'] ?? [];

        $this->assertSame('call-pending-assignment', $pending[0]['call_id']);
        $this->assertSame('completed', $pending[0]['output']['data']['status']);
        $this->assertSame('executed', $pending[0]['output']['data']['confirmation_state']);
        $this->assertSame(['id' => 'task-1'], $pending[0]['output']['data']['result']);
    }
}
