<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Recipe;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeCapabilityDraftStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_additions_create_draft_state_and_first_clarification_without_persisting(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Recipe lifecycle',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $sourceMessage = new Message;
        $sourceMessage->setAttribute('id', '01j00000000000000000000005');
        $sourceMessage->setAttribute('workspace_id', $workspace->id);
        app()->instance('currentWorkspace', $workspace);

        $draft = [
            'name' => 'Baguette Italiano',
            'description' => null,
            'yield' => ['quantity' => null, 'quantity_min' => 2, 'quantity_max' => 3, 'unit_key' => 'portion', 'label' => 'porciones'],
            'ingredients' => collect(range(1, 17))->map(fn (int $number): array => [
                'ingredient_name' => 'ingredient '.$number,
                'quantity' => 1,
                'unit_key' => 'each',
                'optional' => false,
            ])->concat([
                ['ingredient_name' => 'mayonesa', 'quantity' => null, 'unit_key' => null, 'notes' => 'capa muy fina', 'optional' => false],
                ['ingredient_name' => 'parmesano rallado', 'quantity' => null, 'unit_key' => null, 'optional' => false],
                ['ingredient_name' => 'orégano', 'quantity' => null, 'unit_key' => null, 'notes' => 'sobre la lechuga', 'optional' => false],
            ])->all(),
            'steps' => collect(range(1, 9))->map(fn (int $number): array => ['instruction' => 'Step '.$number])->all(),
            'source' => 'structured_ai',
        ];

        $result = app(ToolExecutor::class)->request([
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000004',
            'locale' => 'es',
            'user' => $user,
            'workspace' => $workspace,
            'user_message' => (object) ['content_text' => 'Crea la receta Baguette Italiano'],
            'source_message' => $sourceMessage,
        ], [
            'action_id' => 'recipes.create',
            'input' => ['recipe_draft' => $draft],
        ]);

        $conversation->refresh();
        $state = $conversation->metadata['active_recipe_draft_state'];

        $this->assertSame('clarification_required', $result['status']);
        $this->assertSame('needs_clarification', $state['status']);
        $this->assertCount(20, $state['payload']['ingredients']);
        $this->assertCount(9, $state['payload']['steps']);
        $this->assertContains('yield_range', array_column($state['issues'], 'code'));
        $this->assertContains('ingredient_quantity_missing', array_column($state['issues'], 'code'));
        $this->assertSame('yield.quantity', $conversation->metadata['pending_clarifications'][0]['field_path']);
        $this->assertFalse(Recipe::query()->where('workspace_id', $workspace->id)->where('name', 'Baguette Italiano')->exists());
    }
}
