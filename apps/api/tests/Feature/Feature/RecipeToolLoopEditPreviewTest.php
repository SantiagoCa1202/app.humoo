<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Recipes\CreateRecipe;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeToolLoopEditPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_an_ingredient_from_the_active_recipe_prepares_a_confirmation_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $tbsp = Unit::query()->where('key', 'tbsp')->firstOrFail();
        $portion = Unit::query()->where('key', 'portion')->firstOrFail();
        $recipe = app(CreateRecipe::class)->execute($workspace->id, $user->id, [
            'name' => 'Lemon Herb Chicken',
            'status' => 'active',
            'version' => [
                'name' => 'Lemon Herb Chicken',
                'status' => 'draft',
                'ingredients' => [
                    ['ingredient_name' => 'Sal', 'quantity' => 2, 'unit_id' => $tbsp->id],
                    ['ingredient_name' => 'Pimienta', 'quantity' => 1, 'unit_id' => $tbsp->id],
                ],
                'steps' => [['instruction' => 'Mezclar.']],
                'yields' => [['quantity' => 25, 'unit_id' => $portion->id, 'is_default' => true]],
            ],
        ]);
        $recipe = Recipe::query()->with('currentVersionRecord.ingredients')->findOrFail($recipe->id);
        $pepperId = $recipe->currentVersionRecord->ingredients->firstWhere('ingredient_name', 'Pimienta')->id;
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Recipe edit preview',
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
        $sourceMessage = Message::query()->create([
            'content_text' => 'Elimina la pimienta de la receta.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        app()->instance('currentWorkspace', $workspace);

        $result = app(ToolExecutor::class)->request([
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000004',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'tool_loop' => true,
            'user' => $user,
            'user_message' => (object) ['content_text' => 'Elimina la pimienta de la receta.'],
            'workspace' => $workspace,
        ], [
            'action_id' => 'recipes.edit',
            'input' => [
                'recipe_id' => $recipe->id,
                'recipe_search' => null,
                'mutation' => [
                    'convert_units' => null,
                    'ingredient_changes' => [[
                        'action' => 'remove',
                        'ingredient_name' => 'Pimienta',
                        'notes' => null,
                        'optional' => null,
                        'preparation' => null,
                        'quantity' => null,
                        'target_ingredient_id' => $pepperId,
                        'unit_key' => null,
                    ]],
                    'step_changes' => null,
                    'yield' => null,
                ],
            ],
        ]);

        $preview = collect($result['blocks'])->firstWhere('component', 'action.preview')['data'];

        $this->assertArrayHasKey('confirmation', $result);
        $this->assertSame('Pimienta', $preview['changes'][0]['after']);
        $this->assertSame('Ingrediente eliminado', $preview['changes'][0]['label']);
    }
}
