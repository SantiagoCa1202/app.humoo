<?php

namespace Tests\Feature\Feature;

use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Objectives\ObjectiveValidator;
use App\AI\Exceptions\AiRuntimeException;
use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\Client;
use App\Models\Conversation;
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

final class ObjectiveWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_module_repair_reactivates_descendants_and_verifies_without_duplicates(): void
    {
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::where('workspace_id', $workspace->id)->where('user_id', $user->id)->firstOrFail();
        $conversation = Conversation::create(['workspace_id' => $workspace->id, 'created_by' => $user->id,
            'scope_type' => 'general', 'visibility' => 'private', 'status' => 'active', 'title' => 'Cross module recovery']);
        $message = Message::create(['workspace_id' => $workspace->id, 'conversation_id' => $conversation->id,
            'sender_id' => $user->id, 'sender_type' => 'user', 'status' => 'completed', 'locale' => 'en',
            'content_text' => 'Create a client, then two dependent production tasks.']);
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $run = \App\Models\AiRun::create(['workspace_id' => $workspace->id, 'conversation_id' => $conversation->id,
            'actor_id' => $user->id, 'message_id' => $message->id, 'input_message_id' => $message->id,
            'model_key' => 'test-model', 'status' => 'running', 'current_stage' => 'executing_tool', 'started_at' => now()]);
        $lifecycle->attachRun($objective, $run);
        $context = ['conversation' => $conversation, 'workspace' => $workspace, 'membership' => $membership,
            'ai_run_id' => $run->id,
            'user' => $user, 'user_message' => $message, 'source_message' => $message, 'objective_id' => $objective->id,
            'locale' => 'en', 'tool_loop' => true, 'entity_refs' => [], 'correlation_id' => 'cross-module-recovery'];
        app()->instance('currentWorkspace', $workspace);
        app()->instance('currentMembership', $membership);
        $executor = app(ToolExecutor::class);
        $executor->request($context, ['action_id' => 'objectives.define', 'input' => [
            'required_facts' => [], 'expected_results' => array_map(fn ($key) => [
                'result_key' => $key, 'label' => $key, 'required' => true,
            ], ['client', 'task', 'dependent']),
        ]]);
        $preview = $executor->request($context, ['action_id' => 'execution_plans.create', 'input' => [
            'title' => 'Cross module workflow', 'block_size' => 1,
            'steps' => [
                ['step_key' => 'client', 'action_key' => 'clients.create', 'covers_result_keys' => ['client'], 'input' => ['name' => 'Workflow client']],
                ['step_key' => 'task', 'action_key' => 'tasks.create', 'covers_result_keys' => ['task'],
                    'input' => ['title' => 'Workflow production first', 'priority' => 'INVALID', 'description' => ['$from' => 'client.id']]],
                ['step_key' => 'dependent', 'action_key' => 'tasks.create', 'covers_result_keys' => ['dependent'],
                    'input' => ['title' => 'Workflow production second', 'priority' => 'normal', 'description' => ['$from' => 'task.id']]],
            ],
            'completion_steps' => [
                ['step_key' => 'verify_client', 'action_key' => 'clients.detail', 'input' => ['client_id' => ['$from' => 'client.id']],
                    'covers_result_keys' => ['client'], 'assertions' => [
                        ['path' => ['items', 0, 'id'], 'operator' => 'equals', 'value' => ['$from' => 'client.id']],
                    ]],
                ['step_key' => 'verify_tasks', 'action_key' => 'tasks.list', 'input' => ['search' => 'Workflow production'], 'after' => ['dependent'],
                    'covers_result_keys' => ['task', 'dependent'], 'assertions' => [
                        ['path' => ['items'], 'operator' => 'count_equals', 'value' => 3],
                        ['path' => ['items', '*', 'id'], 'operator' => 'contains', 'value' => ['$from' => 'task.id']],
                        ['path' => ['items', '*', 'id'], 'operator' => 'contains', 'value' => ['$from' => 'dependent.id']],
                    ]],
            ],
        ]]);
        $confirmation = ActionConfirmation::findOrFail($preview['confirmation']['id']);
        $plan = AiExecutionPlan::where('confirmation_id', $confirmation->id)->firstOrFail();
        $executor->confirm($confirmation, $context);
        $job = new ExecuteAiExecutionPlan($plan->id, $workspace->id, $user->id);
        for ($i = 0; $i < 4 && $plan->fresh()->status !== 'partial'; $i++) {
            $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        }
        $this->assertSame('partial', $plan->fresh()->status);
        $this->assertSame('completed', $plan->items()->where('step_key', 'client')->value('status'));
        $this->assertSame('DEPENDENCY_UNAVAILABLE', $plan->items()->where('step_key', 'dependent')->value('error_code'));
        $repair = $executor->request($context, ['action_id' => 'execution_plans.revise', 'input' => [
            'execution_plan_id' => $plan->id, 'items' => [[
                'item_id' => $plan->items()->where('step_key', 'task')->value('id'),
                'input' => ['title' => 'Workflow production first', 'priority' => 'normal'],
            ]],
        ]]);
        $this->assertArrayHasKey('confirmation', $repair);
        $this->assertSame('waiting', $plan->items()->where('step_key', 'dependent')->value('status'));
        $executor->confirm(ActionConfirmation::findOrFail($repair['confirmation']['id']), $context);
        for ($i = 0; $i < 4 && $plan->fresh()->status !== 'completed'; $i++) {
            $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        }
        $this->assertSame('completed', $plan->fresh()->status);
        $verification = app(ObjectiveValidator::class)->validate($objective->fresh());
        $this->assertFalse($verification['valid']);
        $this->assertContains('verify_tasks', $verification['failed_operations']);
        $this->assertSame('needs_review', $run->fresh()->status);
        app(\App\AI\Runtime\AiRunLifecycle::class)->reconcileConversation($conversation, $workspace->id);
        $this->assertSame('needs_review', $run->fresh()->status);
        $reads = collect($executor->executionPlanSnapshot($plan->fresh())['completion_steps']);
        $correctedRead = $reads->firstWhere('step_key', 'verify_tasks');
        unset($correctedRead['error_code'], $correctedRead['status'], $correctedRead['validation_fields']);
        $correctedRead['assertions'][0]['value'] = 2;
        $readRepair = $executor->request($context, ['action_id' => 'execution_plans.revise', 'input' => [
            'execution_plan_id' => $plan->id, 'items' => [], 'completion_steps' => [$correctedRead],
        ]]);
        $this->assertArrayHasKey('confirmation', $readRepair);
        $executor->confirm(ActionConfirmation::findOrFail($readRepair['confirmation']['id']), $context);
        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        $verification = app(ObjectiveValidator::class)->validate($objective->fresh());
        $this->assertTrue($verification['valid'], json_encode($verification));
        $job->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));
        $this->assertSame(1, Client::where('workspace_id', $workspace->id)->where('name', 'Workflow client')->count());
        $this->assertSame(2, Task::where('workspace_id', $workspace->id)->where('title', 'like', 'Workflow production%')->count());
    }

    public function test_scoped_objective_rejects_a_direct_domain_write_before_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
            'title' => 'Scoped write boundary',
        ]);
        $message = Message::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'locale' => 'en',
            'content_text' => 'Create a client and a task for it.',
        ]);
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation,
            $workspace,
            $user,
            $message,
            $message->content_text,
        );
        $context = [
            'conversation' => $conversation,
            'workspace' => $workspace,
            'user' => $user,
            'user_message' => $message,
            'source_message' => $message,
            'objective_id' => $objective->id,
            'locale' => 'en',
            'tool_loop' => true,
            'entity_refs' => [],
            'correlation_id' => 'scoped-write-boundary',
        ];
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $executor->request($context, ['action_id' => 'objectives.define', 'input' => [
            'required_facts' => [],
            'expected_results' => [
                ['result_key' => 'client', 'label' => 'Client', 'required' => true],
                ['result_key' => 'task', 'label' => 'Task', 'required' => true],
            ],
        ]]);

        $confirmationCount = ActionConfirmation::query()->count();
        try {
            $executor->request($context, [
                'action_id' => 'clients.create',
                'input' => ['name' => 'Must be planned'],
            ]);
            $this->fail('A scoped objective accepted a direct domain write.');
        } catch (AiRuntimeException $exception) {
            $this->assertSame('SCOPED_OBJECTIVE_REQUIRES_PLAN', $exception->internalCode());
            $this->assertTrue($exception->retryable());
        }

        $this->assertSame($confirmationCount, ActionConfirmation::query()->count());
        $this->assertDatabaseMissing('clients', [
            'workspace_id' => $workspace->id,
            'name' => 'Must be planned',
        ]);
    }
}
