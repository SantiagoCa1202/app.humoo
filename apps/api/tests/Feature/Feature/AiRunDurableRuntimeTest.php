<?php

namespace Tests\Feature\Feature;

use App\AI\Conversations\OpenAIConversationService;
use App\AI\Errors\ErrorResponseMapper;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Orchestration\AIOrchestrator;
use App\AI\Runtime\AiRunLifecycle;
use App\AI\Streaming\ChatStreamPublisher;
use App\AI\Tools\ToolExecutor;
use App\Events\Realtime\ChatStreamed;
use App\Jobs\ContinueConfirmedConversation;
use App\Jobs\ProcessChatMessage;
use App\Models\AiExecutionPlan;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContextService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiRunDurableRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_lifecycle_is_versioned_and_rejects_invalid_terminal_transition(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $run = $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage);
        $lifecycle = app(AiRunLifecycle::class);

        $run = $lifecycle->transition($run, 'running', 'analyzing');
        $this->assertSame(1, $run->sequence);
        $run = $lifecycle->transition($run, 'waiting_confirmation', 'waiting_confirmation');
        $this->assertSame(2, $run->sequence);
        $run = $lifecycle->transition($run, 'running', 'creating_recipes', 3, 13);
        $this->assertSame(3, $run->progress_current);
        $this->assertSame(13, $run->progress_total);
        $run = $lifecycle->transition($run, 'completed', 'completed');
        $this->assertNotNull($run->completed_at);

        Event::assertDispatchedTimes(ChatStreamed::class, 4);
        $this->expectException(ValidationException::class);
        $lifecycle->transition($run, 'running', 'analyzing');
    }

    public function test_ai_run_realtime_snapshot_excludes_full_objective_payload(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $lifecycle = app(AiRunLifecycle::class);
        $run = $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage);
        $objectives = app(AiObjectiveLifecycle::class);
        $objective = $objectives->startOrResume(
            $conversation,
            $workspace,
            $user,
            $userMessage,
            str_repeat('Large objective description. ', 400),
        );
        $objective->forceFill([
            'blockers_json' => array_fill(0, 40, ['reason' => str_repeat('x', 500)]),
            'expected_results_json' => array_fill(0, 40, ['label' => str_repeat('x', 500), 'required' => true]),
            'required_facts_json' => array_fill(0, 40, ['label' => str_repeat('x', 500), 'status' => 'resolved']),
            'resolved_facts_json' => array_fill(0, 40, ['value' => str_repeat('x', 500)]),
        ])->save();
        $objectives->attachRun($objective, $run);

        $method = new \ReflectionMethod($lifecycle, 'realtimeSnapshot');
        $snapshot = $method->invoke($lifecycle, $run->fresh('objective'));

        $this->assertLessThan(10240, strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)));
        $this->assertArrayNotHasKey('description', $snapshot['objective']);
        $this->assertArrayNotHasKey('expected_results', $snapshot['objective']);
        $this->assertArrayNotHasKey('operations', $snapshot['objective']);
        $this->assertArrayNotHasKey('required_facts', $snapshot['objective']);
        $this->assertArrayNotHasKey('resolved_facts', $snapshot['objective']);
        $this->assertSame($objective->id, $snapshot['objective']['id']);
    }

    public function test_run_state_endpoint_restores_canonical_message_and_enforces_participation(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $run = $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage);
        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/chat/ai-runs?conversation_id={$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.runs.0.id', $run->id)
            ->assertJsonPath('data.runs.0.status', 'queued')
            ->assertJsonPath('data.messages.0.id', $assistantMessage->id);

        $outsider = User::factory()->create();
        $privateConversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $outsider->id,
            'title' => 'Private run',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/chat/ai-runs?conversation_id={$privateConversation->id}")
            ->assertNotFound();
    }

    public function test_reconciliation_terminalizes_a_completed_plan_run_without_reexecuting_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $lifecycle = app(AiRunLifecycle::class);
        $run = $lifecycle->transition(
            $lifecycle->transition(
                $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage),
                'running',
                'executing_tool',
            ),
            'waiting_confirmation',
            'waiting_confirmation',
        );
        $plan = AiExecutionPlan::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'ai_run_id' => $run->id,
            'created_by' => $user->id,
            'status' => 'completed',
            'item_count' => 1,
            'completed_count' => 1,
            'finished_at' => now(),
        ]);
        $run->forceFill(['execution_plan_id' => $plan->id])->save();
        $messageCount = Message::query()->where('conversation_id', $conversation->id)->count();

        $this->assertSame(1, $lifecycle->reconcileConversation($conversation, $workspace->id));
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(0, $lifecycle->reconcileConversation($conversation, $workspace->id));
        $this->assertSame($messageCount, Message::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_duplicate_delivery_of_a_terminal_run_exits_without_reexecution(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $lifecycle = app(AiRunLifecycle::class);
        $run = $lifecycle->transition(
            $lifecycle->transition(
                $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage),
                'running',
                'analyzing',
            ),
            'completed',
            'completed',
        );
        $conversation->forceFill(['metadata' => ['provider_recovery' => [
            'pending' => true, 'run_id' => (string) $run->id, 'retry_at' => now()->subMinute()->toIso8601String(),
        ]]])->save();

        (new ProcessChatMessage(
            (string) $conversation->id,
            (string) $workspace->id,
            (string) $user->id,
            (string) $userMessage->id,
            (string) $run->id,
        ))->handle(
            app(AIOrchestrator::class),
            $lifecycle,
            app(ChatStreamPublisher::class),
            app(WorkspaceContextService::class),
        );

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(1, AiRun::query()->where('input_message_id', $userMessage->id)->count());
        $this->assertSame(1, Message::query()->where('parent_message_id', $userMessage->id)->count());
    }

    public function test_permission_removed_before_execution_fails_run_without_calling_provider(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $run = $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage);
        $workspace->memberships()->where('user_id', $user->id)->delete();

        (new ProcessChatMessage(
            (string) $conversation->id,
            (string) $workspace->id,
            (string) $user->id,
            (string) $userMessage->id,
            (string) $run->id,
        ))->handle(
            app(AIOrchestrator::class),
            app(AiRunLifecycle::class),
            app(ChatStreamPublisher::class),
            app(WorkspaceContextService::class),
        );

        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('AI_PROCESSING_UNAVAILABLE', $run->fresh()->error_code);
        $this->assertSame('failed', $userMessage->fresh()->status);
    }

    public function test_chat_and_confirmation_jobs_share_the_conversation_lock(): void
    {
        $chat = new ProcessChatMessage('conversation-a', 'workspace', 'user', 'message', 'run');
        $continuation = new ContinueConfirmedConversation('confirmation', 'workspace', 'user', 'conversation-a');
        $this->assertSame($chat->middleware()[0]->getLockKey($chat), $continuation->middleware()[0]->getLockKey($continuation));
        $other = new ProcessChatMessage('conversation-b', 'workspace', 'user', 'message2', 'run2');
        $this->assertNotSame($chat->middleware()[0]->getLockKey($chat), $other->middleware()[0]->getLockKey($other));
    }

    public function test_uncertain_turn_waits_in_queue_before_resuming_its_run(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        [$workspace, $user, $conversation, $userMessage, $assistantMessage] = $this->context();
        $run = $this->makeRun($workspace, $user, $conversation, $userMessage, $assistantMessage);
        $run->forceFill(['status' => 'paused', 'error_code' => 'AI_TIMEOUT'])->save();
        app(OpenAIConversationService::class)->deferUncertainTurn($conversation, (string) $run->id);
        $job = (new ProcessChatMessage($conversation->id, $workspace->id, $user->id, $userMessage->id, $run->id))
            ->withFakeQueueInteractions();
        $job->handle(app(AIOrchestrator::class), app(AiRunLifecycle::class), app(ChatStreamPublisher::class), app(WorkspaceContextService::class));
        $job->assertReleased();
        $this->assertSame('paused', $run->fresh()->status);
        $this->assertSame(1, AiRun::where('input_message_id', $userMessage->id)->count());
    }

    private function context(): array
    {
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Durable chat',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        $userMessage = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'status' => 'pending',
            'content_text' => 'Create dinner.',
        ]);
        $assistantMessage = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'parent_message_id' => $userMessage->id,
            'sender_type' => 'assistant',
            'status' => 'pending',
        ]);

        return [$workspace, $user, $conversation, $userMessage, $assistantMessage];
    }

    private function makeRun(Workspace $workspace, User $user, Conversation $conversation, Message $userMessage, Message $assistantMessage): AiRun
    {
        return AiRun::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'actor_id' => $user->id,
            'message_id' => $assistantMessage->id,
            'input_message_id' => $userMessage->id,
            'model_key' => 'test-model',
            'status' => 'queued',
            'current_stage' => 'queued',
            'queued_at' => now(),
        ]);
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-ai-run',
        ])->assertOk()->json('token');
    }
}
