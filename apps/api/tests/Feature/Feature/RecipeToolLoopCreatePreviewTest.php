<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Recipes\CreateRecipe;
use App\Models\ActionConfirmation;
use App\Models\Allergen;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\MenuItem;
use App\Models\Message;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

        $allergen = Allergen::query()->create([
            'active' => true,
            'category' => 'us_major',
            'key' => 'test-milk',
            'name' => 'Milk',
        ]);
        $executor = app(ToolExecutor::class);
        $catalog = $executor->request([
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000003',
            'locale' => 'es',
            'tool_loop' => true,
            'user' => $user,
            'workspace' => $workspace,
            'user_message' => $sourceMessage,
            'source_message' => $sourceMessage,
        ], [
            'action_id' => 'recipes.catalog',
            'input' => [],
        ]);
        $this->assertContains($allergen->id, array_column($catalog['result_ref_json']['allergens'], 'id'));
        $this->assertContains('portion', array_column($catalog['result_ref_json']['units'], 'key'));

        $draft = $this->lemonHerbChickenDraft();
        $draft['allergens'] = [[
            'allergen_id' => $allergen->id,
            'presence' => 'contains',
            'source' => 'ai',
        ]];
        $result = $executor->request([
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
            'input' => ['recipe_draft' => $draft],
        ]);

        $preview = collect($result['blocks'])->firstWhere('component', 'action.preview')['data'];

        $this->assertArrayHasKey('confirmation', $result);
        $this->assertSame('Lemon Herb Chicken', $preview['action']);
        $this->assertSame(8, $preview['ingredient_count']);
        $this->assertSame(4, $preview['step_count']);
        $this->assertSame('25', (string) $preview['yield']['quantity']);
        $this->assertSame('portion', $preview['yield']['unit_key']);

        $confirmation = ActionConfirmation::query()->findOrFail($result['confirmation']['id']);
        $token = $this->login('owner@humoo.local', 'password');
        $this->withToken($token)->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/confirmations/{$result['confirmation']['token']}/confirm", [
                'idempotency_key' => $confirmation->idempotency_key,
            ])->assertOk()->assertJsonPath('data.confirmation.status', 'executed');
        $recipe = Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Lemon Herb Chicken')->with('currentVersionRecord.allergens')->firstOrFail();
        $this->assertSame($allergen->id, $recipe->currentVersionRecord->allergens->first()?->id);
        $this->assertSame($recipe->id, data_get($conversation->fresh()->metadata, 'ai_operational_context.active_entity_refs.0.id'));
        $this->assertSame($recipe->currentVersionRecord->id, data_get($conversation->fresh()->metadata, 'ai_operational_context.active_entity_refs.0.current_version_id'));

        $duplicate = $executor->request([
            'conversation' => $conversation->fresh(),
            'correlation_id' => '01j00000000000000000000005',
            'locale' => 'es',
            'tool_loop' => true,
            'user' => $user,
            'workspace' => $workspace,
            'user_message' => $sourceMessage,
            'source_message' => $sourceMessage,
        ], [
            'action_id' => 'recipes.create',
            'input' => ['recipe_draft' => $draft],
        ]);
        $this->assertSame('failed', $duplicate['status']);
        $this->assertSame('ACTIVE_ENTITY_ALREADY_EXISTS', $duplicate['error_code']);
        $this->assertSame($recipe->id, $duplicate['result_ref_json']['id']);
        $this->assertSame(1, Recipe::query()->where('workspace_id', $workspace->id)
            ->where('name', 'Lemon Herb Chicken')->count());
    }

    public function test_explicit_recipe_component_is_persisted_without_changing_menu_assignments_and_invalid_relations_are_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $portion = Unit::query()->where('key', 'portion')->firstOrFail();
        $target = $this->createRecipe($workspace, $user, 'Steak Frites Component Target', $portion);
        $component = $this->createRecipe($workspace, $user, 'Black Peppercorn Sauce Component', $portion);
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Recipe component validation',
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
            'content_text' => 'Vincula la salsa dentro de la receta Steak Frites.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000006',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'tool_loop' => true,
            'user' => $user,
            'user_message' => $sourceMessage,
            'workspace' => $workspace,
        ];
        $menuAssignmentsBefore = MenuItem::query()->where('workspace_id', $workspace->id)
            ->orderBy('id')->get(['id', 'recipe_id', 'recipe_version_id'])->toArray();

        $preview = $executor->request($context, [
            'action_id' => 'recipes.edit',
            'input' => [
                'recipe_id' => $target->id,
                'mutation' => ['ingredient_changes' => [$this->componentIngredient($component)]],
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $this->assertSame($component->id, data_get($confirmation->draft_json, 'input.version.ingredients.1.component_recipe_id'));
        $this->assertSame($component->currentVersionRecord->id, data_get($confirmation->draft_json, 'input.version.ingredients.1.component_recipe_version_id'));

        $token = $this->login('owner@humoo.local', 'password');
        $confirm = fn () => $this->withToken($token)->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/confirmations/{$preview['confirmation']['token']}/confirm", [
                'idempotency_key' => $confirmation->idempotency_key,
            ])->assertOk()->assertJsonPath('data.confirmation.status', 'executed');
        $confirm();
        $target->refresh()->load('currentVersionRecord.ingredients');
        $linkedIngredient = $target->currentVersionRecord->ingredients
            ->firstWhere('component_recipe_id', $component->id);
        $this->assertNotNull($linkedIngredient);
        $this->assertSame($component->currentVersionRecord->id, $linkedIngredient->component_recipe_version_id);
        $this->assertSame($menuAssignmentsBefore, MenuItem::query()->where('workspace_id', $workspace->id)
            ->orderBy('id')->get(['id', 'recipe_id', 'recipe_version_id'])->toArray());
        $versionCount = $target->versions()->count();
        $confirm();
        $this->assertSame($versionCount, $target->versions()->count());

        $wrongVersionInput = $this->componentIngredient($component);
        $wrongVersionInput['component_recipe_version_id'] = $target->currentVersionRecord->id;
        $this->assertValidationField(
            fn () => $executor->request($context, [
                'action_id' => 'recipes.edit',
                'input' => ['recipe_id' => $target->id, 'mutation' => ['ingredient_changes' => [$wrongVersionInput]]],
            ]),
            'version.ingredients.2.component_recipe_version_id',
        );

        $selfInput = $this->componentIngredient($target);
        $this->assertValidationField(
            fn () => $executor->request($context, [
                'action_id' => 'recipes.edit',
                'input' => ['recipe_id' => $target->id, 'mutation' => ['ingredient_changes' => [$selfInput]]],
            ]),
            'version.ingredients.2.component_recipe_id',
        );

        $foreignWorkspace = Workspace::query()->create([
            'currency' => 'USD',
            'default_locale' => 'en',
            'name' => 'Foreign component kitchen',
            'slug' => 'foreign-component-kitchen',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        $foreignComponent = $this->createRecipe($foreignWorkspace, $user, 'Foreign sauce', $portion);
        $this->assertValidationField(
            fn () => $executor->request($context, [
                'action_id' => 'recipes.edit',
                'input' => ['recipe_id' => $target->id, 'mutation' => ['ingredient_changes' => [$this->componentIngredient($foreignComponent)]]],
            ]),
            'version.ingredients.2.component_recipe_id',
        );

        $cycleComponent = app(CreateRecipe::class)->execute($workspace->id, $user->id, [
            'name' => 'Cycle component',
            'status' => 'active',
            'version' => [
                'name' => 'Cycle component',
                'ingredients' => [[
                    'ingredient_name' => $target->name,
                    'quantity' => 1,
                    'unit_id' => $portion->id,
                    'component_recipe_id' => $target->id,
                    'component_recipe_version_id' => $target->currentVersionRecord->id,
                ]],
                'steps' => [['instruction' => 'Preparar.']],
                'yields' => [['quantity' => 1, 'unit_id' => $portion->id, 'is_default' => true]],
            ],
        ])->load('currentVersionRecord');
        $this->assertValidationField(
            fn () => $executor->request($context, [
                'action_id' => 'recipes.edit',
                'input' => ['recipe_id' => $target->id, 'mutation' => ['ingredient_changes' => [$this->componentIngredient($cycleComponent)]]],
            ]),
            'version.ingredients.2.component_recipe_id',
        );
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
            'component_recipe_id' => null,
            'component_recipe_version_id' => null,
        ];
    }

    private function createRecipe(Workspace $workspace, User $user, string $name, Unit $portion): Recipe
    {
        return app(CreateRecipe::class)->execute($workspace->id, $user->id, [
            'name' => $name,
            'status' => 'active',
            'version' => [
                'name' => $name,
                'ingredients' => [['ingredient_name' => 'Base', 'quantity' => 1, 'unit_id' => $portion->id]],
                'steps' => [['instruction' => 'Preparar.']],
                'yields' => [['quantity' => 1, 'unit_id' => $portion->id, 'is_default' => true]],
            ],
        ])->load('currentVersionRecord');
    }

    /** @return array<string, mixed> */
    private function componentIngredient(Recipe $component): array
    {
        return [
            'action' => 'add',
            'component_recipe_id' => $component->id,
            'component_recipe_version_id' => $component->currentVersionRecord->id,
            'ingredient_name' => $component->name,
            'notes' => null,
            'optional' => false,
            'preparation' => null,
            'quantity' => 1,
            'target_ingredient_id' => null,
            'unit_key' => 'portion',
        ];
    }

    private function assertValidationField(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'device_name' => 'phpunit-recipe-tool-loop',
            'email' => $email,
            'password' => $password,
        ])->assertOk()->json('token');
    }
}
