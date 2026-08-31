<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeToolLoopCreatePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_recipe_from_the_tool_loop_creates_a_confirmation_preview_without_clarification(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Recipe tool loop preview',
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
            'content_text' => 'Crea Lemon Herb Chicken.',
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
            'locale' => 'es',
            'tool_loop' => true,
            'user' => $user,
            'workspace' => $workspace,
            'user_message' => (object) ['content_text' => 'Crea Lemon Herb Chicken'],
            'source_message' => $sourceMessage,
        ], [
            'action_id' => 'recipes.create',
            'input' => ['recipe_draft' => $this->lemonHerbChickenDraft()],
        ]);

        $preview = collect($result['blocks'])->firstWhere('component', 'action.preview')['data'];

        $this->assertArrayHasKey('confirmation', $result);
        $this->assertSame('Lemon Herb Chicken', $preview['action']);
        $this->assertSame(8, $preview['ingredient_count']);
        $this->assertSame(4, $preview['step_count']);
        $this->assertSame('25', (string) $preview['yield']['quantity']);
        $this->assertSame('portion', $preview['yield']['unit_key']);
    }

    /** @return array<string, mixed> */
    private function lemonHerbChickenDraft(): array
    {
        return [
            'name' => 'Lemon Herb Chicken',
            'description' => 'Pechuga de pollo marinada con hierbas y limón.',
            'yield' => ['quantity' => 25, 'quantity_min' => null, 'quantity_max' => null, 'unit_key' => 'portion', 'label' => '25 porciones'],
            'ingredients' => [
                $this->ingredient('pechuga de pollo', 15, 'lb'),
                $this->ingredient('aceite de oliva', 1, 'cup'),
                $this->ingredient('jugo de limón', 0.5, 'cup'),
                $this->ingredient('ajo', 0.25, 'cup', 'picado'),
                $this->ingredient('romero', 3, 'tbsp'),
                $this->ingredient('tomillo', 2, 'tbsp'),
                $this->ingredient('sal', 2, 'tbsp'),
                $this->ingredient('pimienta', 1, 'tbsp'),
            ],
            'steps' => [
                ['title' => null, 'instruction' => 'Marinar durante 2 horas.', 'duration_minutes' => 120],
                ['title' => null, 'instruction' => 'Hornear a 375 °F hasta alcanzar 165 °F internos.', 'duration_minutes' => null],
                ['title' => null, 'instruction' => 'Dejar reposar 10 minutos.', 'duration_minutes' => 10],
                ['title' => null, 'instruction' => 'Cortar y servir.', 'duration_minutes' => null],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function ingredient(string $name, float|int $quantity, string $unit, ?string $preparation = null): array
    {
        return [
            'ingredient_name' => $name,
            'quantity' => $quantity,
            'quantity_min' => null,
            'quantity_max' => null,
            'quantity_text' => null,
            'unit_key' => $unit,
            'preparation' => $preparation,
            'notes' => null,
            'optional' => false,
            'group' => null,
            'alternatives' => [],
        ];
    }
}
