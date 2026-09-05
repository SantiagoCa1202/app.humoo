<?php

namespace Tests\Feature\Feature;

use App\Events\Realtime\ChatStreamed;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiRuntimeTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_run_conversation_message_and_confirmation_are_not_exposed_or_mutated(): void
    {
        [$primaryWorkspace, $secondaryWorkspace, $user, $conversation, $userMessage, $assistantMessage] = $this->tenantContext();
        $run = $this->makeRun($secondaryWorkspace, $user, $conversation, $userMessage, $assistantMessage);
        $rawConfirmationToken = 'foreign-confirmation-token';
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'message_id' => $assistantMessage->id,
            'action_key' => 'tasks.create',
            'token_hash' => hash('sha256', $rawConfirmationToken),
            'draft_json' => ['title' => 'Foreign workspace task'],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'idempotency_key' => 'foreign-idempotency-key',
        ]);
        $token = $this->login('owner@humoo.local', 'password');

        $runResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $primaryWorkspace->id)
            ->getJson("/api/v1/chat/ai-runs/{$run->id}")
            ->assertNotFound();

        $conversationResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $primaryWorkspace->id)
            ->getJson("/api/v1/chat?conversation_id={$conversation->id}")
            ->assertNotFound();

        $confirmationResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $primaryWorkspace->id)
            ->postJson("/api/v1/confirmations/{$rawConfirmationToken}/confirm", [
                'idempotency_key' => 'foreign-idempotency-key',
            ])
            ->assertNotFound();

        $this->assertStringNotContainsString('foreign-assistant-secret', $runResponse->getContent());
        $this->assertStringNotContainsString('foreign-assistant-secret', $conversationResponse->getContent());
        $this->assertStringNotContainsString('foreign-assistant-secret', $confirmationResponse->getContent());
        $this->assertSame('pending', $confirmation->fresh()->status);
        $this->assertNull($confirmation->fresh()->confirmed_at);
        $this->assertNull($confirmation->fresh()->executed_at);
    }

    public function test_execution_plan_job_with_wrong_workspace_cannot_touch_foreign_plan_or_messages(): void
    {
        Event::fake([ChatStreamed::class]);
        [$primaryWorkspace, $secondaryWorkspace, $user, $conversation] = $this->tenantContext();
        $plan = AiExecutionPlan::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'title' => 'Foreign queued plan',
            'status' => 'queued',
            'item_count' => 0,
            'completed_count' => 0,
            'failed_count' => 0,
            'needs_review_count' => 0,
            'block_size' => 1,
        ]);
        $messageCount = Message::query()->where('conversation_id', $conversation->id)->count();

        $this->app->call([
            new ExecuteAiExecutionPlan(
                (string) $plan->id,
                (string) $primaryWorkspace->id,
                (string) $user->id,
            ),
            'handle',
        ]);

        $plan->refresh();
        $this->assertSame('queued', $plan->status);
        $this->assertSame(0, $plan->completed_count);
        $this->assertSame(0, $plan->failed_count);
        $this->assertNull($plan->started_at);
        $this->assertNull($plan->finished_at);
        $this->assertNull($plan->progress_message_id);
        $this->assertSame($messageCount, Message::query()->where('conversation_id', $conversation->id)->count());
        Event::assertNotDispatched(ChatStreamed::class);
    }

    private function tenantContext(): array
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $primaryWorkspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $secondaryWorkspace = Workspace::query()->create([
            'name' => 'Humoo Isolated Kitchen',
            'slug' => 'humoo-isolated-kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $ownerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'owner')
            ->firstOrFail();
        WorkspaceMembership::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $conversation = Conversation::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'created_by' => $user->id,
            'title' => 'Foreign private conversation',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        ConversationParticipant::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        $userMessage = Message::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'status' => 'completed',
            'content_text' => 'Foreign request',
        ]);
        $assistantMessage = Message::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'conversation_id' => $conversation->id,
            'parent_message_id' => $userMessage->id,
            'sender_type' => 'assistant',
            'status' => 'completed',
            'content_text' => 'foreign-assistant-secret',
        ]);

        return [$primaryWorkspace, $secondaryWorkspace, $user, $conversation, $userMessage, $assistantMessage];
    }

    private function makeRun(
        Workspace $workspace,
        User $user,
        Conversation $conversation,
        Message $userMessage,
        Message $assistantMessage,
    ): AiRun {
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
            'device_name' => 'phpunit-ai-runtime-tenant-isolation',
        ])->assertOk()->json('token');
    }
}
