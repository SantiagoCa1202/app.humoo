<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Jobs\ContinueConfirmedConversation;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiRun;
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
use Illuminate\Validation\ValidationException;
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
        $run = AiRun::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'actor_id' => $actor->id,
            'message_id' => $message->id,
            'input_message_id' => $message->id,
            'model_key' => 'test-model',
            'status' => 'running',
            'current_stage' => 'executing_tool',
            'queued_at' => now(),
            'started_at' => now(),
        ]);
        $context = [
            'ai_run_id' => $run->id,
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
        $lifecycle = app(\App\AI\Objectives\AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $actor, $message, $message->content_text);
        $lifecycle->attachRun($objective, $run);
        $context['objective_id'] = $objective->id;
        $executor->request($context, ['action_id' => 'objectives.define', 'input' => [
            'required_facts' => [],
            'expected_results' => [
                ['result_key' => 'first_task', 'label' => 'First task', 'required' => true],
                ['result_key' => 'second_task', 'label' => 'Second task', 'required' => true],
            ],
        ]]);

        try {
            $executor->request($context, [
                'action_id' => 'execution_plans.create',
                'input' => [
                    'completion_steps' => [],
                    'steps' => [
                        $this->taskStep('invalid_source', 'Invalid binding source'),
                        [
                            ...$this->taskStep('invalid_target', 'Invalid binding target'),
                            'input' => [
                                'description' => ['$from' => 'invalid_source.entity_refs.0.id'],
                                'priority' => 'normal',
                                'status' => 'todo',
                                'title' => 'Invalid binding target',
                                'type' => 'general',
                            ],
                        ],
                    ],
                ],
            ]);
            $this->fail('Provider envelope paths must not be accepted as persisted workflow result paths.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('steps.1.input', $exception->errors());
        }

        $preview = $executor->request($context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 1,
                'objective' => 'Create two ordered tasks',
                'steps' => [
                    $this->taskStep('first_task', 'First workflow task'),
                    [
                        ...$this->taskStep('second_task', 'Second workflow task'),
                        'after' => [],
                        'input' => [
                            'description' => ['$from' => 'first_task.id'],
                            'priority' => 'normal',
                            'status' => 'todo',
                            'title' => 'Second workflow task',
                            'type' => 'general',
                        ],
                    ],
                ],
                'completion_steps' => [[
                    'action_key' => 'tasks.list',
                    'after' => ['second_task'],
                    'input' => ['search' => 'workflow task'],
                    'label' => 'Show the completed workflow tasks',
                    'step_key' => 'show_tasks',
                    'covers_result_keys' => ['first_task', 'second_task'],
                    'assertions' => [
                        ['path' => ['items'], 'operator' => 'count_equals', 'value' => 2],
                        ['path' => ['items', '*', 'id'], 'operator' => 'contains', 'value' => ['$from' => 'first_task.id']],
                        ['path' => ['items', '*', 'id'], 'operator' => 'contains', 'value' => ['$from' => 'second_task.id']],
                    ],
                ]],
                'title' => 'Ordered task workflow',
            ],
        ]);

        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();
        $this->assertSame($run->id, $plan->ai_run_id);
        $this->assertSame($plan->id, $run->fresh()->execution_plan_id);
        $this->assertSame('pending_confirmation', $plan->status);
        $this->assertSame('01j00000000000000000000008', $plan->metadata_json['correlation_id']);
        $this->assertSame($actor->id, $plan->metadata_json['actor_id']);
        $this->assertSame($message->id, $plan->metadata_json['source_message_id']);
        $this->assertSame(
            'ready',
            $plan->items()->where('step_key', 'first_task')->value('status'),
            (string) $plan->items()->where('step_key', 'first_task')->value('error_message'),
        );
        $this->assertSame('waiting', $plan->items()->where('step_key', 'second_task')->value('status'));
        $this->assertSame(['first_task'], $plan->items()->where('step_key', 'second_task')->value('depends_on_json'));
        $this->assertEquals([[
            'source_step_key' => 'first_task',
            'source_path' => ['id'],
            'target_path' => ['description'],
        ]], $plan->items()->where('step_key', 'second_task')->value('input_bindings_json'));
        $this->assertSame('blocked_by_workflow', data_get(
            $executor->executionPlanSnapshot($plan->fresh()),
            'completion_steps.0.status',
        ));

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
        $pendingAfterConfirmation = $conversation->fresh()->metadata['pending_provider_tool_outputs'] ?? [];
        $this->assertCount(1, $pendingAfterConfirmation);
        $this->assertNull($pendingAfterConfirmation[0]['output']);
        $this->assertNotNull($plan->fresh()->progress_message_id);

        app()->forgetInstance('currentWorkspace');
        app()->forgetInstance('currentMembership');

        // Simulate a worker crash after claiming the first step but before its
        // domain transaction began. The next worker must safely reclaim it.
        $plan->items()->where('step_key', 'first_task')->update([
            'attempts' => 1,
            'started_at' => now()->subMinutes(5),
            'status' => 'running',
        ]);
        $plan->forceFill(['status' => 'running'])->save();

        $job = new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id);
        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        $this->assertSame('running', $plan->fresh()->status);
        $this->assertSame('completed', $plan->items()->where('step_key', 'first_task')->value('status'));
        $this->assertSame(2, $plan->items()->where('step_key', 'first_task')->value('attempts'));
        $this->assertSame('waiting', $plan->items()->where('step_key', 'second_task')->value('status'));
        Queue::assertNotPushed(ContinueConfirmedConversation::class);
        $this->assertNull(data_get(
            $conversation->fresh()->metadata,
            'pending_provider_tool_outputs.0.output',
        ));

        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertSame('completed', $run->fresh()->status);
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
        $this->assertSame('completed', data_get(
            $executor->executionPlanSnapshot($plan->fresh()),
            'completion_steps.0.status',
        ));
        Queue::assertPushed(ContinueConfirmedConversation::class, fn (ContinueConfirmedConversation $continuation): bool => $continuation->confirmationId === $confirmation->id
            && $continuation->conversationId === $conversation->id
        );
        $resolvedOutput = data_get(
            $conversation->fresh()->metadata,
            'pending_provider_tool_outputs.0.output',
        );
        $this->assertIsArray($resolvedOutput);
        $this->assertSame('completed', data_get($resolvedOutput, 'data.status'));
        $this->assertSame('completed', data_get(
            $resolvedOutput,
            'data.result.completion_steps.0.status',
        ));
        $this->assertNotNull(data_get($plan->fresh()->metadata_json, 'provider_continuation_dispatched_at'));

        // A duplicated queue delivery after completion is a no-op.
        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        Queue::assertPushed(ContinueConfirmedConversation::class, 1);
        $this->assertSame(2, Task::query()->where('workspace_id', $workspace->id)
            ->whereIn('title', ['First workflow task', 'Second workflow task'])
            ->count());

        $broadcastSnapshotMethod = new \ReflectionMethod($job, 'executionPlanBroadcastSnapshot');
        $largeSnapshot = [
            ...$executor->executionPlanSnapshot($plan->fresh()),
            'completion_steps' => array_fill(0, 10, [
                'action_key' => 'tasks.detail',
                'input' => ['large' => str_repeat('x', 4000)],
                'status' => 'ready_for_ai',
                'step_key' => 'read-task',
            ]),
            'steps' => array_fill(0, 50, [
                'action_key' => 'tasks.create',
                'id' => '01j00000000000000000000009',
                'result_ref' => ['large' => str_repeat('x', 4000)],
                'status' => 'completed',
                'step_key' => 'create-task',
            ]),
        ];
        $broadcastSnapshot = $broadcastSnapshotMethod->invoke($job, $largeSnapshot);
        $this->assertLessThan(10240, strlen(json_encode($broadcastSnapshot, JSON_THROW_ON_ERROR)));
        $this->assertArrayNotHasKey('result_ref', $broadcastSnapshot['steps'][0]);
        $this->assertArrayNotHasKey('input', $broadcastSnapshot['completion_steps'][0]);
    }

    /** @return array<string, mixed> */
    private function taskStep(string $stepKey, string $title): array
    {
        return [
            'action_key' => 'tasks.create',
            'after' => [],
            'input' => [
                'priority' => 'normal',
                'status' => 'todo',
                'title' => $title,
                'type' => 'general',
            ],
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
