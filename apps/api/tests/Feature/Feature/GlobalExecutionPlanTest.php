<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Jobs\ContinueConfirmedConversation;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\WorkspaceContextService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GlobalExecutionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_plan_executes_dependency_order_without_a_follow_up_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Global execution plan',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $actor->id,
            'workspace_id' => $workspace->id,
        ]);
        $message = Message::query()->create([
            'content_text' => 'Crea estas dos tareas en orden.',
            'conversation_id' => $conversation->id,
            'locale' => 'en',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000008',
            'entity_refs' => [],
            'locale' => 'en',
            'membership' => $membership,
            'source_message' => $message,
            'tool_loop' => true,
            'user' => $actor,
            'user_message' => $message,
            'workspace' => $workspace,
        ];
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);

        $preview = $executor->request($context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 1,
                'objective' => 'Create two ordered tasks',
                'steps' => [
                    $this->taskStep('first_task', 'First workflow task'),
                    $this->taskStep('second_task', 'Second workflow task', ['first_task'], [[
                        'source_path' => ['id'],
                        'source_step_key' => 'first_task',
                        'target_path' => ['description'],
                    ]]),
                ],
                'title' => 'Ordered task workflow',
            ],
        ]);

        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();
        $this->assertSame('pending_confirmation', $plan->status);
        $this->assertSame(
            'ready',
            $plan->items()->where('step_key', 'first_task')->value('status'),
            (string) $plan->items()->where('step_key', 'first_task')->value('error_message'),
        );
        $this->assertSame('waiting', $plan->items()->where('step_key', 'second_task')->value('status'));

        $conversation->forceFill(['metadata' => [
            'pending_provider_tool_outputs' => [[
                'action_key' => 'execution_plans.create',
                'call_id' => 'call-global-execution-plan',
                'continuation_id' => $confirmation->id,
                'output' => null,
            ]],
        ]])->save();
        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/confirmations/{$preview['confirmation']['token']}/confirm", [
                'idempotency_key' => $confirmation->idempotency_key,
            ])
            ->assertOk()
            ->assertJsonPath('data.confirmation.status', 'executed')
            ->assertJsonMissing(['data' => ['continuation']]);
        $this->assertSame('queued', $plan->fresh()->status);
        Queue::assertPushed(ExecuteAiExecutionPlan::class, fn (ExecuteAiExecutionPlan $job): bool => $job->executionPlanId === $plan->id);
        Queue::assertNotPushed(ContinueConfirmedConversation::class);
        $this->assertSame([], $conversation->fresh()->metadata['pending_provider_tool_outputs'] ?? []);
        $this->assertNotNull($plan->fresh()->progress_message_id);

        app()->forgetInstance('currentWorkspace');
        app()->forgetInstance('currentMembership');

        $job = new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id);
        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        $this->assertSame('running', $plan->fresh()->status);
        $this->assertSame('completed', $plan->items()->where('step_key', 'first_task')->value('status'));
        $this->assertSame('waiting', $plan->items()->where('step_key', 'second_task')->value('status'));

        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertSame(2, Task::query()->where('workspace_id', $workspace->id)
            ->whereIn('title', ['First workflow task', 'Second workflow task'])
            ->count());
        $firstTaskId = Task::query()->where('workspace_id', $workspace->id)
            ->where('title', 'First workflow task')
            ->value('id');
        $this->assertSame(
            $firstTaskId,
            Task::query()->where('workspace_id', $workspace->id)
                ->where('title', 'Second workflow task')
                ->value('description'),
        );
        $this->assertSame(2, $plan->items()->where('status', 'completed')->count());
        $this->assertSame(0, $plan->fresh()->needs_review_count);
    }

    /** @param array<int, string> $dependsOn @param array<int, array<string, mixed>> $bindings @return array<string, mixed> */
    private function taskStep(string $stepKey, string $title, array $dependsOn = [], array $bindings = []): array
    {
        return [
            'action_key' => 'tasks.create',
            'depends_on' => $dependsOn,
            'input' => [
                'priority' => 'normal',
                'status' => 'todo',
                'title' => $title,
                'type' => 'general',
            ],
            'input_bindings' => $bindings,
            'is_required' => true,
            'label' => $title,
            'step_key' => $stepKey,
        ];
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'device_name' => 'phpunit-global-execution-plan',
            'email' => $email,
            'password' => $password,
        ])->assertOk()->json('token');
    }
}
