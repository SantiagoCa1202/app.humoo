<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
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

        $preview = $executor->request($context, [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 10,
                'objective' => 'Create two Mediterranean recipes',
                'steps' => [
                    $this->incompleteRecipeStep('falafel', 'Plan Falafel'),
                    $this->recipeStep('souvlaki', 'Plan Souvlaki'),
                ],
                'title' => 'Mediterranean recipe plan',
            ],
        ]);

        $originalPlan = AiExecutionPlan::query()->firstOrFail();
        $originalConfirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $this->assertSame('pending_confirmation', $originalPlan->status);
        $this->assertSame(2, $originalPlan->items()->count());
        $this->assertSame(1, $originalPlan->needs_review_count);
        $this->assertSame('needs_review', $originalPlan->items()->where('step_key', 'falafel')->value('status'));
        $this->assertSame('ready', $originalPlan->items()->where('step_key', 'souvlaki')->value('status'));
        $this->assertSame(1, ActionConfirmation::query()->where('is_execution_plan_item', true)->count());
        $this->assertFalse((bool) $originalConfirmation->is_execution_plan_item);

        $revisedPreview = $executor->request([
            ...$context,
            'pending_confirmation_revision_id' => $originalConfirmation->id,
        ], [
            'action_id' => 'execution_plans.create',
            'input' => [
                'block_size' => 10,
                'objective' => 'Create two revised Mediterranean recipes',
                'steps' => [
                    $this->recipeStep('falafel', 'Revised Falafel'),
                    $this->recipeStep('souvlaki', 'Revised Souvlaki'),
                ],
                'title' => 'Revised Mediterranean recipe plan',
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($revisedPreview['confirmation']['id']);
        $plan = AiExecutionPlan::query()->where('confirmation_id', $confirmation->id)->firstOrFail();
        $this->assertSame('cancelled', $originalPlan->fresh()->status);
        $this->assertSame('cancelled', $originalConfirmation->fresh()->status);
        $this->assertSame(2, $originalPlan->items()->where('status', 'cancelled')->count());
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
            'depends_on' => [],
            'input' => $this->recipeDraft($name),
            'input_bindings' => [],
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
