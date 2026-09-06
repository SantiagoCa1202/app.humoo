<?php

namespace Tests\Feature\Feature;

use App\AI\Exceptions\AiProviderTimeoutException;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Objectives\ObjectiveValidator;
use App\AI\Runtime\DurableRecoveryNotice;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiObjective;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class AiObjectiveDurabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_objective_resumes_and_completed_objective_is_not_reused(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text, 'corr-one');
        $reply = $this->message($workspace, $user, $conversation, 'Continue the same objective');

        $this->assertSame(
            $objective->id,
            $lifecycle->startOrResume($conversation, $workspace, $user, $reply, $reply->content_text, 'corr-two')->id,
        );

        $objective->operations()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'done',
            'action_key' => 'tasks.create',
            'kind' => 'write',
            'status' => 'completed',
            'is_required' => true,
            'result_ref_json' => ['id' => 'task-done'],
        ]);
        $objective->forceFill(['operation_count' => 1])->save();
        app(ObjectiveValidator::class)->finalize($objective->fresh());
        $next = $this->message($workspace, $user, $conversation, 'Start another objective');
        $newObjective = $lifecycle->startOrResume($conversation->fresh(), $workspace, $user, $next, $next->content_text, 'corr-three');

        $this->assertNotSame($objective->id, $newObjective->id);
        $this->assertSame($newObjective->id, data_get($conversation->fresh()->metadata, 'active_ai_objective_id'));
    }

    public function test_manifest_persists_facts_operations_verification_and_digest(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation, $workspace, $user, $message, $message->content_text, 'corr-manifest',
        );
        $plan = $this->plan($workspace, $user, $conversation, $objective);
        $steps = [$this->step('create_task', 'tasks.create')];
        $completion = [$this->step('verify_tasks', 'tasks.list', 'verification')];

        $objective = app(AiObjectiveLifecycle::class)->prepareManifest($objective, $plan, [
            'required_facts' => [[
                'fact_key' => 'workspace_scope',
                'label' => 'Workspace scope',
                'status' => 'resolved',
                'value' => $workspace->id,
            ]],
            'expected_results' => [[
                'result_key' => 'create_task',
                'label' => 'Task exists',
                'required' => true,
            ]],
            'verification_rules' => [[
                'rule_key' => 'task_visible',
                'operation_key' => 'verify_tasks',
                'required' => true,
            ]],
        ], $steps, $completion);

        $this->assertSame('ready_for_confirmation', $objective->status);
        $this->assertSame(2, $objective->operation_count);
        $this->assertCount(2, $objective->operations);
        $this->assertSame(['workspace_scope'], collect($objective->resolved_facts_json)->pluck('fact_key')->all());
        $this->assertSame(64, strlen((string) $objective->approval_digest));
        $this->assertSame($objective->id, $plan->fresh()->objective_id);
    }

    public function test_manifest_with_missing_fact_waits_without_executing_writes(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $taskCount = \App\Models\Task::query()->count();
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation, $workspace, $user, $message, $message->content_text,
        );
        $plan = $this->plan($workspace, $user, $conversation, $objective);
        $objective = app(AiObjectiveLifecycle::class)->prepareManifest($objective, $plan, [
            'required_facts' => [[
                'fact_key' => 'menu_id',
                'label' => 'Menu',
                'status' => 'ambiguous',
                'value' => null,
            ]],
        ], [$this->step('create_task', 'tasks.create')], []);

        $this->assertSame('waiting_user', $objective->status);
        $this->assertSame(1, $objective->blocked_count);
        $this->assertSame('pending', $objective->operations()->value('status'));
        $this->assertSame($taskCount, \App\Models\Task::query()->count());
    }

    public function test_manifest_revision_cannot_drop_unfinished_required_operations(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $firstPlan = $this->plan($workspace, $user, $conversation, $objective);
        $objective = $lifecycle->prepareManifest(
            $objective,
            $firstPlan,
            [],
            [$this->step('required_task', 'tasks.create')],
            [],
        );
        $secondPlan = $this->plan($workspace, $user, $conversation, $objective);

        $this->expectException(ValidationException::class);
        $lifecycle->prepareManifest($objective, $secondPlan, [], [$this->step('replacement', 'tasks.create')], []);
    }

    public function test_two_batch_operations_can_cover_more_than_fifty_conceptual_results(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $resultKeys = collect(range(1, 52))->map(fn (int $index): string => 'result_'.$index);
        $steps = collect([1, 2])->map(function (int $batch) use ($resultKeys): array {
            $covered = $resultKeys->slice(($batch - 1) * 26, 26)->values()->all();

            return [
                'step_key' => 'batch_'.$batch,
                'action_key' => 'menus.items.batch_update',
                'label' => 'Batch '.$batch,
                'covers_result_keys' => $covered,
                'depends_on' => [],
                'input' => ['updates' => []],
                'input_bindings' => [],
                'is_required' => true,
            ];
        })->all();
        $expected = $resultKeys->map(fn (string $key): array => [
            'result_key' => $key,
            'label' => $key,
            'required' => true,
        ])->all();

        $objective = $lifecycle->prepareManifest(
            $objective,
            $this->plan($workspace, $user, $conversation, $objective),
            ['expected_results' => $expected],
            $steps,
            [],
        );

        $this->assertSame(2, $objective->operation_count);
        $this->assertCount(52, $objective->expected_results_json);
        $this->assertSame(52, $objective->operations->sum(fn ($operation): int => count($operation->expected_result_keys_json)));
    }

    public function test_confirmation_must_match_manifest_revision_and_digest(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $objective = $lifecycle->prepareManifest(
            $objective,
            $this->plan($workspace, $user, $conversation, $objective),
            [],
            [$this->step('required_task', 'tasks.create')],
            [],
        );
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $workspace->id,
            'message_id' => $message->id,
            'objective_id' => $objective->id,
            'manifest_revision' => $objective->revision,
            'approval_digest' => str_repeat('0', 64),
            'action_key' => 'execution_plans.create',
            'token_hash' => hash('sha256', 'objective-confirmation'),
            'draft_json' => [],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'idempotency_key' => 'objective-confirmation',
        ]);

        $this->expectException(ValidationException::class);
        $lifecycle->approve($objective, $confirmation);
    }

    public function test_backend_refuses_completed_status_without_durable_result_evidence(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $objective = $lifecycle->prepareManifest(
            $objective,
            $this->plan($workspace, $user, $conversation, $objective),
            [],
            [$this->step('required_task', 'tasks.create')],
            [],
        );
        $objective->operations()->where('operation_key', 'required_task')->update([
            'result_ref_json' => null,
            'status' => 'completed',
        ]);

        $verification = app(ObjectiveValidator::class)->validate($objective->fresh());

        $this->assertFalse($verification['valid']);
        $this->assertSame('needs_review', $verification['canonical_status']);
        $this->assertSame(['required_task'], $verification['missing_evidence']);
        $this->assertContains('completed_operations_have_evidence', $verification['failed_invariants']);
    }

    public function test_plan_from_another_workspace_cannot_link_to_objective(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $otherWorkspace = $this->otherWorkspace();
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation, $workspace, $user, $message, $message->content_text,
        );
        $foreignPlan = $this->plan($otherWorkspace, $user, $conversation, $objective);

        $this->expectException(NotFoundHttpException::class);
        app(AiObjectiveLifecycle::class)->prepareManifest(
            $objective,
            $foreignPlan,
            [],
            [$this->step('foreign_task', 'tasks.create')],
            [],
        );
    }

    public function test_confirmation_from_another_workspace_cannot_approve_manifest(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $objective = $lifecycle->prepareManifest(
            $objective,
            $this->plan($workspace, $user, $conversation, $objective),
            [],
            [$this->step('required_task', 'tasks.create')],
            [],
        );
        $otherWorkspace = $this->otherWorkspace();
        $foreignConversation = Conversation::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $user->id,
            'title' => 'Foreign objective confirmation',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        $foreignMessage = $this->message($otherWorkspace, $user, $foreignConversation, 'Confirm foreign manifest');
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'message_id' => $foreignMessage->id,
            'objective_id' => $objective->id,
            'manifest_revision' => $objective->revision,
            'approval_digest' => $objective->approval_digest,
            'action_key' => 'execution_plans.create',
            'token_hash' => hash('sha256', 'foreign-objective-confirmation'),
            'draft_json' => [],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'idempotency_key' => 'foreign-objective-confirmation',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $lifecycle->approve($objective, $confirmation);
    }

    public function test_cancelling_an_objective_cancels_only_pending_state_and_clears_the_active_pointer(): void
    {
        [$workspace, $user, $conversation, $message] = $this->context();
        $lifecycle = app(AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($conversation, $workspace, $user, $message, $message->content_text);
        $plan = $this->plan($workspace, $user, $conversation, $objective);
        $objective = $lifecycle->prepareManifest(
            $objective,
            $plan,
            [],
            [
                $this->step('already_done', 'tasks.create'),
                $this->step('still_pending', 'tasks.create'),
            ],
            [],
        );
        $objective->operations()->where('operation_key', 'already_done')->update([
            'completed_at' => now(),
            'result_ref_json' => ['id' => 'existing-task'],
            'status' => 'completed',
        ]);
        $plan->forceFill(['status' => 'pending_confirmation'])->save();
        $completedItem = $plan->items()->create([
            'position' => 0,
            'action_key' => 'tasks.create',
            'status' => 'completed',
            'completed_at' => now(),
            'result_ref_json' => ['id' => 'existing-task'],
        ]);
        $pendingItem = $plan->items()->create([
            'position' => 1,
            'action_key' => 'tasks.create',
            'status' => 'previewed',
        ]);
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $workspace->id,
            'message_id' => $message->id,
            'objective_id' => $objective->id,
            'manifest_revision' => $objective->revision,
            'approval_digest' => $objective->approval_digest,
            'action_key' => 'execution_plans.create',
            'token_hash' => hash('sha256', 'cancel-objective-confirmation'),
            'draft_json' => [],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'idempotency_key' => 'cancel-objective-confirmation',
        ]);
        $conversation->forceFill(['metadata' => [
            ...(is_array($conversation->metadata) ? $conversation->metadata : []),
            'active_ai_objective_id' => (string) $objective->id,
            'pending_clarifications' => [[
                'actor_id' => $user->id,
                'status' => 'pending',
                'workspace_id' => $workspace->id,
            ]],
            'pending_continuations' => [['status' => 'pending']],
        ]])->save();

        $cancelled = $lifecycle->cancelActive($conversation->fresh(), $workspace, $user, 'User cancelled');

        $this->assertSame('cancelled', $cancelled?->status);
        $this->assertSame('completed', $objective->operations()->where('operation_key', 'already_done')->value('status'));
        $this->assertSame('cancelled', $objective->operations()->where('operation_key', 'still_pending')->value('status'));
        $this->assertSame('completed', $completedItem->fresh()->status);
        $this->assertSame('cancelled', $pendingItem->fresh()->status);
        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertSame('cancelled', $confirmation->fresh()->status);
        $this->assertSame($user->id, $confirmation->fresh()->cancelled_by);
        $this->assertNull(data_get($conversation->fresh()->metadata, 'active_ai_objective_id'));
        $this->assertSame('cancelled', data_get($conversation->fresh()->metadata, 'pending_clarifications.0.status'));
        $this->assertSame('cancelled', data_get($conversation->fresh()->metadata, 'pending_continuations.0.status'));
    }

    public function test_deadline_persists_recovery_notice_and_preserves_objective_progress(): void
    {
        Event::fake();
        [$workspace, $user, $conversation, $message] = $this->context();
        $assistant = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'status' => 'pending',
            'locale' => 'en',
            'parent_message_id' => $message->id,
        ]);
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation, $workspace, $user, $message, $message->content_text, 'corr-timeout',
        );
        $objective->operations()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'completed_task',
            'action_key' => 'tasks.create',
            'kind' => 'write',
            'status' => 'completed',
            'is_required' => true,
        ]);
        $objective->operations()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'pending_task',
            'action_key' => 'tasks.create',
            'kind' => 'write',
            'status' => 'pending',
            'is_required' => true,
        ]);
        $objective = app(AiObjectiveLifecycle::class)->refreshCounts($objective, 'running');
        $run = AiRun::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'objective_id' => $objective->id,
            'actor_id' => $user->id,
            'message_id' => $assistant->id,
            'input_message_id' => $message->id,
            'model_key' => 'test-model',
            'status' => 'running',
            'current_stage' => 'executing_tool',
            'queued_at' => now(),
            'started_at' => now(),
            'metadata' => ['correlation_id' => 'corr-timeout'],
        ]);

        $notice = app(DurableRecoveryNotice::class)->record(
            $run,
            new AiProviderTimeoutException('provider details must stay private'),
            'en',
            'RUN_DEADLINE_EXCEEDED',
        );

        $this->assertSame('paused', $run->fresh()->status);
        $this->assertSame('paused', $objective->fresh()->status);
        $this->assertSame(1, $objective->fresh()->completed_count);
        $this->assertSame('failed', $notice?->status);
        $this->assertSame('error.recovery', $notice?->blocks()->value('component_key'));
        $this->assertSame('RUN_DEADLINE_EXCEEDED', data_get($notice?->blocks()->value('payload_json'), 'data.error_code'));
        $this->assertTrue((bool) data_get($notice?->blocks()->value('payload_json'), 'data.preserved_progress'));
        $this->assertStringNotContainsString('provider details', (string) $notice?->content_text);
    }

    private function context(): array
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Durable objective',
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

        return [$workspace, $user, $conversation, $this->message($workspace, $user, $conversation, 'Create required workspace data')];
    }

    private function message(Workspace $workspace, User $user, Conversation $conversation, string $content): Message
    {
        return Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'status' => 'completed',
            'locale' => 'en',
            'content_text' => $content,
        ]);
    }

    private function otherWorkspace(): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Other durable workspace',
            'slug' => 'other-durable-workspace',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ]);
    }

    private function plan(Workspace $workspace, User $user, Conversation $conversation, AiObjective $objective): AiExecutionPlan
    {
        return AiExecutionPlan::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'objective_id' => $objective->id,
            'created_by' => $user->id,
            'title' => 'Durable plan',
            'objective' => $objective->description,
            'status' => 'draft',
            'item_count' => 1,
            'block_size' => 1,
        ]);
    }

    private function step(string $key, string $action, string $kind = 'write'): array
    {
        return [
            'step_key' => $key,
            'action_key' => $action,
            'label' => $key,
            'depends_on' => [],
            'input' => $action === 'tasks.create' ? [
                'title' => $key,
                'description' => null,
                'priority' => 'normal',
                'status' => 'todo',
                'type' => 'general',
            ] : [],
            'input_bindings' => [],
            'is_required' => true,
            'kind' => $kind,
        ];
    }
}
