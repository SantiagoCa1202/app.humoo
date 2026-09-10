<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Recipe;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\WorkspaceContextService;
use App\AI\Tools\ToolRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecipeExecutionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_recipe_plan_executes_every_item_once_in_a_queue_block(): void
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
            'title' => 'Recipe execution plan',
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
            'content_text' => 'Crea estas dos recetas.',
            'conversation_id' => $conversation->id,
            'locale' => 'en',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $context = [
            'ai_run_id' => $this->makeRun($workspace, $actor, $conversation, $message)->id,
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000006',
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

        $preview = $this->createScopedPlan($executor, $context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 10,
                'objective' => 'Create two Mediterranean recipes',
                'steps' => [
                    $this->incompleteRecipeStep('falafel', 'Plan Falafel'),
                    $this->incompleteRecipeStep('souvlaki', 'Plan Souvlaki'),
                ],
                'title' => 'Mediterranean recipe plan',
            ],
        ]);

        $originalPlan = AiExecutionPlan::query()->firstOrFail();
        $this->assertSame('clarification_required', $preview['status']);
        $this->assertArrayNotHasKey('confirmation', $preview);
        $this->assertCount(2, $preview['clarification']['missing_fields']);
        $this->assertSame('draft', $originalPlan->status);
        $this->assertSame(2, $originalPlan->items()->count());
        $this->assertSame(2, $originalPlan->needs_review_count);
        $this->assertSame('needs_review', $originalPlan->items()->where('step_key', 'falafel')->value('status'));
        $this->assertSame('needs_review', $originalPlan->items()->where('step_key', 'souvlaki')->value('status'));
        $this->assertSame(0, ActionConfirmation::query()->where('is_execution_plan_item', true)->count());

        $repairItem = $originalPlan->items()->where('step_key', 'falafel')->firstOrFail();
        $secondRepairItem = $originalPlan->items()->where('step_key', 'souvlaki')->firstOrFail();
        $revisedPreview = $executor->request($context, [
            'action_id' => 'execution_plans.revise',
            'input' => [
                'execution_plan_id' => $originalPlan->id,
                'items' => [[
                    'item_id' => $repairItem->id,
                    'input' => $this->recipeDraft('Revised Falafel'),
                ], [
                    'item_id' => $secondRepairItem->id,
                    'input' => $this->recipeDraft('Revised Souvlaki'),
                ]],
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($revisedPreview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();
        $this->assertSame($originalPlan->id, $plan->id);
        $this->assertSame('pending_confirmation', $plan->status);

        $executor->confirm($confirmation, $context);
        $this->assertSame('queued', $plan->fresh()->status);
        Queue::assertPushed(ExecuteAiExecutionPlan::class, fn (ExecuteAiExecutionPlan $job): bool => $job->executionPlanId === $plan->id);

        app()->forgetInstance('currentWorkspace');
        app()->forgetInstance('currentMembership');

        (new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id))
            ->handle(
                $executor,
                app(AssistantMessageWriter::class),
                app(WorkspaceContextService::class),
            );

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertSame(2, $plan->fresh()->completed_count);
        $this->assertSame(0, $plan->fresh()->failed_count);
        $this->assertSame(2, Recipe::query()->where('workspace_id', $workspace->id)
            ->whereIn('name', ['Revised Falafel', 'Revised Souvlaki'])
            ->count());
        $this->assertSame(2, $plan->items()->where('status', 'completed')->count());
        $this->assertFalse(app()->bound('currentWorkspace'));
        $this->assertFalse(app()->bound('currentMembership'));
        $this->assertFalse(app(ToolRegistry::class)->resolve('execution_plans.latest')['target_entity_required']);
    }

    public function test_partial_plan_revises_only_the_repaired_steps_without_repeating_completed_work(): void
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
            'title' => 'Recover partial recipe plan',
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
            'content_text' => 'Create two recipes and recover only incomplete work.',
            'conversation_id' => $conversation->id,
            'locale' => 'en',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $context = [
            'ai_run_id' => $this->makeRun($workspace, $actor, $conversation, $message)->id,
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000009',
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

        $preview = $this->createScopedPlan($executor, $context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 10,
                'objective' => 'Recover only the unresolved recipe',
                'steps' => [
                    $this->recipeStep('completed_recipe', 'Completed exactly once'),
                    $this->recipeStep('repair_recipe', 'Repair only this recipe'),
                ],
                'title' => 'Partial recipe recovery',
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();

        $executor->confirm($confirmation, $context);
        $plan->items()->where('step_key', 'repair_recipe')->update([
            'error_code' => 'CONFLICT',
            'error_message' => 'The operation requires a safe review.',
            'status' => 'needs_review',
        ]);
        (new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id))
            ->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('partial', $plan->fresh()->status);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed exactly once')->count());

        $repairItem = $plan->items()->where('step_key', 'repair_recipe')->firstOrFail();
        $partialRevision = $executor->request($context, [
            'action_id' => 'execution_plans.revise',
            'input' => [
                'execution_plan_id' => $plan->id,
                'items' => [[
                    'item_id' => $repairItem->id,
                    'input' => $this->incompleteRecipeStep('repair_recipe', 'Still incomplete')['input'],
                ]],
            ],
        ]);
        $this->assertSame('clarification_required', $partialRevision['status']);
        $this->assertArrayNotHasKey('confirmation', $partialRevision);
        $this->assertSame('partial', $plan->fresh()->status);

        $repairItem->refresh();
        $revisedPreview = $executor->request($context, [
            'action_id' => 'execution_plans.revise',
            'input' => [
                'execution_plan_id' => $plan->id,
                'items' => [[
                    'item_id' => $repairItem->id,
                    'input' => $this->recipeDraft('Recovered without duplication'),
                ]],
            ],
        ]);
        $revisedConfirmation = ActionConfirmation::query()->findOrFail($revisedPreview['confirmation']['id']);

        $this->assertSame($plan->id, AiExecutionPlan::query()->where('confirmation_id', $revisedConfirmation->id)->value('id'));
        $this->assertSame('pending_confirmation', $plan->fresh()->status);
        $this->assertSame(3, $plan->fresh()->revision);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed exactly once')->count());

        $executor->confirm($revisedConfirmation, $context);
        $this->assertSame('queued', $plan->fresh()->status);
        Queue::assertPushed(ExecuteAiExecutionPlan::class, fn (ExecuteAiExecutionPlan $job): bool => $job->executionPlanId === $plan->id);

        (new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id))
            ->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed exactly once')->count());
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Recovered without duplication')->count());
        $this->assertSame(2, $plan->items()->where('status', 'completed')->count());
    }

    public function test_remote_component_retry_keeps_persisted_plan_items_on_the_ai_first_contract(): void
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
            'title' => 'AI-first execution plan retry',
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
            'content_text' => 'Create both recipes and retry only unresolved work.',
            'conversation_id' => $conversation->id,
            'locale' => 'en',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $context = [
            'ai_run_id' => $this->makeRun($workspace, $actor, $conversation, $message)->id,
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000010',
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

        $preview = $this->createScopedPlan($executor, $context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 10,
                'objective' => 'Verify AI-first retry invariants',
                'steps' => [
                    $this->recipeStep('completed_recipe', 'Completed before retry'),
                    $this->recipeStep('retry_recipe', 'Initially unresolved recipe'),
                ],
                'title' => 'AI-first retry regression',
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();

        $executor->confirm($confirmation, $context);
        $plan->items()->where('step_key', 'retry_recipe')->update([
            'error_code' => 'CONFLICT',
            'error_message' => 'The operation requires a safe review.',
            'status' => 'needs_review',
        ]);
        (new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id))
            ->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('partial', $plan->fresh()->status);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed before retry')->count());

        $retryItem = $plan->items()->where('step_key', 'retry_recipe')->firstOrFail();
        $remoteComponentContext = $context;
        unset($remoteComponentContext['tool_loop']);

        $retryResult = $executor->retryExecutionPlanItems(
            $remoteComponentContext,
            $plan->id,
            [$retryItem->id],
        );

        $this->assertSame('queued', $retryResult['status'], (string) $retryItem->fresh()->error_message);
        $this->assertSame('queued', $retryItem->fresh()->status);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed before retry')->count());

        (new ExecuteAiExecutionPlan($plan->id, $workspace->id, $actor->id))
            ->handle($executor, app(AssistantMessageWriter::class), app(WorkspaceContextService::class));

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertSame(2, $plan->fresh()->completed_count);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Completed before retry')->count());
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Initially unresolved recipe')->count());
    }

    private function createScopedPlan(ToolExecutor $executor, array &$context, array $payload): array
    {
        $lifecycle = app(\App\AI\Objectives\AiObjectiveLifecycle::class);
        $objective = $lifecycle->startOrResume($context['conversation'], $context['workspace'], $context['user'], $context['user_message'], $context['user_message']->content_text);
        $context['objective_id'] = $objective->id;
        $lifecycle->attachRun($objective, AiRun::findOrFail($context['ai_run_id']));
        $steps = $payload['input']['steps'];
        $executor->request($context, ['action_id' => 'objectives.define', 'input' => [
            'required_facts' => [],
            'expected_results' => array_map(fn (array $step): array => ['result_key' => $step['step_key'], 'label' => $step['step_key'], 'required' => true], $steps),
        ]]);
        $payload['input']['completion_steps'] = array_map(fn (array $step): array => [
            'step_key' => 'verify_'.$step['step_key'], 'action_key' => 'recipes.detail',
            'input' => ['recipe_id' => ['$from' => $step['step_key'].'.id']],
            'covers_result_keys' => [$step['step_key']],
            'assertions' => [['path' => ['items', 0, 'id'], 'operator' => 'equals', 'value' => ['$from' => $step['step_key'].'.id']]],
        ], $steps);

        return $executor->request($context, $payload);
    }

    private function makeRun(Workspace $workspace, User $actor, Conversation $conversation, Message $message): AiRun
    {
        return AiRun::query()->create([
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
    }

    /** @return array<string, mixed> */
    private function recipeDraft(string $name): array
    {
        return [
            'description' => $name.' description',
            'ingredients' => [[
                'alternatives' => [],
                'group' => null,
                'ingredient_name' => 'Chickpeas',
                'notes' => null,
                'optional' => false,
                'preparation' => null,
                'quantity' => 4,
                'quantity_max' => null,
                'quantity_min' => null,
                'quantity_text' => null,
                'unit_key' => 'oz',
            ]],
            'name' => $name,
            'source' => 'structured_ai',
            'steps' => [[
                'duration_minutes' => null,
                'instruction' => 'Cook until ready.',
                'title' => 'Cook',
            ]],
            'yield' => [
                'label' => '1 portion',
                'quantity' => 1,
                'quantity_max' => null,
                'quantity_min' => null,
                'unit_key' => 'portion',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function recipeStep(string $stepKey, string $name): array
    {
        return [
            'action_key' => 'recipes.create',
            'after' => [],
            'input' => $this->recipeDraft($name),
            'is_required' => true,
            'label' => $name,
            'step_key' => $stepKey,
        ];
    }

    /** @return array<string, mixed> */
    private function incompleteRecipeStep(string $stepKey, string $name): array
    {
        $step = $this->recipeStep($stepKey, $name);
        unset($step['input']['ingredients'][0]['quantity'], $step['input']['ingredients'][0]['unit_key']);

        return $step;
    }
}
