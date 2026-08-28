<?php

namespace Tests\Feature\Feature;

use App\AI\Contracts\AIProvider;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Policy\ActionPolicy;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolRegistry;
use App\Models\IntentPattern;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HybridIntentRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Local routing tests remain deterministic regardless of the developer's
        // runtime .env choice. The direct-GPT path is covered explicitly below.
        config()->set('ai.routing.local_enabled', true);
    }

    public function test_environment_flag_bypasses_all_local_routing_and_uses_gpt_directly(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('ai.routing.local_enabled', false);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $fallback = new class implements AIProvider {
            public int $calls = 0;

            public function generate(array $context): array
            {
                $this->calls++;

                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'clients.list', 'input' => []]];
            }
        };
        $router = new HybridIntentRouter(
            new RuleBasedAIProvider(),
            $fallback,
            app(IntentPatternRegistry::class),
            app(ToolRegistry::class),
        );

        $decision = $router->route($this->context('show my events', $workspace->id));

        $this->assertSame('clients.list', $decision['routing']['action_key']);
        $this->assertSame('ai', $decision['routing']['source']);
        $this->assertSame(1, $fallback->calls);
    }

    public function test_known_read_is_resolved_without_ai(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $decision = app(HybridIntentRouter::class)->route($this->context(
            'muéstrame los eventos',
            $workspace->id
        ));

        $this->assertSame('deterministic', $decision['routing']['source']);
        $this->assertSame('events.list', $decision['routing']['action_key']);
        Http::assertNothingSent();
    }

    public function test_spanish_recipe_document_with_a_typo_uses_the_recipe_create_path_before_ai_fallback(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $fallback = new class implements AIProvider {
            public int $calls = 0;

            public function generate(array $context): array
            {
                $this->calls++;

                return [
                    'intent' => 'tool_action',
                    'provider' => 'test',
                    'slots' => [
                        'action_key' => 'recipes.create',
                        'entity_type' => 'recipe',
                        'input' => [
                            'recipe_draft' => [
                                'name' => 'Baguette Italiano',
                                'yield' => ['quantity' => 3, 'unit_key' => 'portion'],
                                'ingredients' => [],
                                'steps' => [],
                            ],
                        ],
                    ],
                ];
            }
        };
        $recipe = <<<'RECIPE'
crea la siguiente reseta: 🥖 Baguette Italiano

Ingredientes

1 baguette grande
4 oz de jamón
4 oz de salami Genoa
3 oz de pepperoni
4 oz de provolone, en rebanadas
1 tomate mediano, en rodajas
1 taza de lechuga romana o iceberg, finamente cortada
¼ de cebolla roja, en rodajas finas
¼ taza de banana peppers o pepperoncini

Aderezo italiano

3 tbsp aceite de oliva
2 tbsp vinagre de vino tinto
½ tsp orégano seco
¼ tsp ajo en polvo
¼ tsp cebolla en polvo
¼ tsp pimienta negra
Una pizca de sal
½ tsp Dijon, opcional

Preparación

Corta la baguette longitudinalmente sin separarla completamente.
Mezcla todos los ingredientes del aderezo.
Rocía un poco de aderezo sobre ambas caras del pan.
Coloca en capas:
provolone → jamón → salami → pepperoni → tomate → cebolla → banana peppers → lechuga.
Agrega un poco más de aderezo sobre los vegetales.
Cierra la baguette y presiónala ligeramente.
Corta en 2–3 porciones.
RECIPE;

        $router = new HybridIntentRouter(
            new RuleBasedAIProvider(),
            $fallback,
            app(IntentPatternRegistry::class),
            app(ToolRegistry::class),
        );
        $decision = $router->route($this->context($recipe, $workspace->id, 'es'));

        $this->assertSame('recipe_document_create', $decision['routing']['message_shape']);
        $this->assertSame('recipes.create', $decision['routing']['action_key']);
        $this->assertSame('ai', $decision['routing']['source']);
        $this->assertSame('Baguette Italiano', $decision['slots']['input']['recipe_draft']['name']);
        $this->assertArrayNotHasKey('raw_recipe_text', $decision['slots']['input']);
        $this->assertSame(1, $fallback->calls, 'A local miss must receive one universal GPT routing attempt.');
    }

    public function test_incompatible_local_action_is_rejected_before_gpt_selects_the_canonical_capability(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $local = new class extends RuleBasedAIProvider {
            public function generate(array $context): array
            {
                return ['intent' => 'tool_action', 'slots' => [
                    'action_key' => 'menus.items.update',
                    'input' => ['item_search' => 'Baguette Italiano'],
                ]];
            }
        };
        $fallback = new class implements AIProvider {
            public int $calls = 0;

            public function generate(array $context): array
            {
                $this->calls++;

                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.create', 'input' => [
                    'recipe_draft' => [
                        'name' => 'Baguette Italiano',
                        'yield' => ['quantity' => 3, 'unit_key' => 'portion'],
                        'ingredients' => [],
                        'steps' => [],
                    ],
                ]]];
            }
        };
        $message = "crea algo nuevo\nIngredientes\n1 pan\n2 oz queso\n1 tomate\nPreparación\nMezcla todo.\nSirve caliente.\nCorta y presenta.";
        $router = new HybridIntentRouter($local, $fallback, app(IntentPatternRegistry::class), app(ToolRegistry::class));

        $decision = $router->route($this->context($message, $workspace->id, 'es'));

        $this->assertSame('recipes.create', $decision['routing']['action_key']);
        $this->assertSame('ai', $decision['routing']['source']);
        $this->assertSame(1, $fallback->calls);
    }

    public function test_recipe_document_repair_requires_the_recipe_action_and_structured_draft(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $fallback = new class implements AIProvider {
            public int $calls = 0;

            public function generate(array $context): array
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return ['intent' => 'generative', 'slots' => ['input' => []]];
                }

                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.create', 'input' => [
                    'recipe_draft' => [
                        'name' => 'Salsa Verde',
                        'yield' => ['quantity' => 2, 'unit_key' => 'portion'],
                        'ingredients' => [],
                        'steps' => [],
                    ],
                ]]];
            }
        };
        $message = "crea esta receta:\nSalsa Verde\nIngredientes\n2 tomates\n1 chile\n1 limón\nPreparación\nAsa los tomates.\nLicua los ingredientes.\nSirve la salsa.";
        $router = new HybridIntentRouter(new RuleBasedAIProvider(), $fallback, app(IntentPatternRegistry::class), app(ToolRegistry::class));

        $decision = $router->route($this->context($message, $workspace->id, 'es'));

        $this->assertSame('recipes.create', $decision['routing']['action_key']);
        $this->assertSame('ai_repair', $decision['routing']['source']);
        $this->assertSame('Salsa Verde', $decision['slots']['input']['recipe_draft']['name']);
        $this->assertSame(2, $fallback->calls);
    }

    public function test_task_creation_is_registered_as_a_confirmed_capability_without_ai(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $decision = app(HybridIntentRouter::class)->route($this->context(
            'create task "Prepare tomorrow breakfast" tomorrow at 8 for 2 hours',
            $workspace->id
        ));

        $this->assertSame('create_task', $decision['intent']);
        $this->assertSame('tasks.create', $decision['routing']['action_key']);
        $this->assertSame('deterministic', $decision['routing']['source']);
        $this->assertSame('Prepare tomorrow breakfast', $decision['slots']['task_title']);
        $this->assertNotNull($decision['slots']['starts_at']);
        $this->assertNotNull($decision['slots']['due_at']);
        Http::assertNothingSent();
    }

    public function test_task_creation_without_title_requests_clarification_instead_of_unsupported(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $decision = app(HybridIntentRouter::class)->route($this->context(
            'crea una task para mañana a las 8 que durará 2 horas',
            $workspace->id
        ));

        $this->assertSame('create_task', $decision['intent']);
        $this->assertNull($decision['slots']['task_title']);
        $this->assertNotSame('unsupported_capability', $decision['intent']);
        Http::assertNothingSent();
    }

    public function test_task_legacy_slots_are_normalized_into_registered_action_input(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $decision = app(HybridIntentRouter::class)->route($this->context(
            'crea una task para manana las 8am llamada clean coolers que durarar 8 horas',
            $workspace->id
        ));

        $this->assertSame('tool_action', $decision['intent']);
        $this->assertSame('tasks.create', $decision['slots']['action_key']);
        $this->assertSame('clean coolers', $decision['slots']['input']['title']);
        $this->assertNotNull($decision['slots']['input']['starts_at']);
        $this->assertNotNull($decision['slots']['input']['due_at']);
        Http::assertNothingSent();
    }

    public function test_directory_reads_and_writes_use_registered_tools_without_ai(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $event = app(HybridIntentRouter::class)->route($this->context(
            'create an event tomorrow at 8 called Smith Dinner',
            $workspace->id
        ));
        $clients = app(HybridIntentRouter::class)->route($this->context(
            'list clients',
            $workspace->id
        ));
        $contact = app(HybridIntentRouter::class)->route($this->context(
            'create contact John Smith for client Acme Catering',
            $workspace->id
        ));
        $venue = app(HybridIntentRouter::class)->route($this->context(
            'show venue "Downtown Hall"',
            $workspace->id
        ));

        $this->assertSame('tool_action', $event['intent']);
        $this->assertSame('events.create', $event['routing']['action_key']);
        $this->assertSame('Smith Dinner', $event['slots']['input']['name']);
        $this->assertSame('clients.list', $clients['routing']['action_key']);
        $this->assertSame('contacts.create', $contact['routing']['action_key']);
        $this->assertSame('John', $contact['slots']['input']['first_name']);
        $this->assertSame('Acme Catering', $contact['slots']['input']['client_search']);
        $this->assertSame('venues.detail', $venue['routing']['action_key']);
        $this->assertSame('Downtown Hall', $venue['slots']['entity_search']);
        Http::assertNothingSent();
    }

    public function test_directory_destructive_actions_remain_confirmation_gated(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertTrue($registry->resolve('events.delete')['requires_confirmation']);
        $this->assertTrue($registry->resolve('clients.delete')['requires_confirmation']);
        $this->assertTrue($registry->resolve('contacts.delete')['requires_confirmation']);
        $this->assertTrue($registry->resolve('venues.delete')['requires_confirmation']);
        $this->assertTrue($registry->resolve('events.cancel')['requires_confirmation']);
    }

    public function test_menu_show_never_routes_to_creation(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $decision = app(HybridIntentRouter::class)->route($this->context('show me the menu', $workspace->id));

        $this->assertSame('show_menu', $decision['intent']);
        $this->assertSame('menus.show', $decision['routing']['action_key']);
        Http::assertNothingSent();
    }

    public function test_menu_item_move_uses_existing_move_section_action(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $decision = app(HybridIntentRouter::class)->route($this->context(
            'move tortilla chips to Hot Food in Down South Boulevard',
            $workspace->id
        ));

        $this->assertSame('move_menu_item_section', $decision['intent']);
        $this->assertSame('menus.items.move_section', $decision['routing']['action_key']);
        $this->assertNotSame('unsupported_capability', $decision['intent']);
        Http::assertNothingSent();
    }

    public function test_recipe_read_and_scale_use_registered_tools(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $detail = app(HybridIntentRouter::class)->route($this->context('show recipe "Chimichurri"', $workspace->id));
        $scale = app(HybridIntentRouter::class)->route($this->context('scale recipe "Chimichurri" to 50 servings', $workspace->id));

        $this->assertSame('recipes.detail', $detail['routing']['action_key']);
        $this->assertSame('recipes.scale', $scale['routing']['action_key']);
        $this->assertFalse(app(ToolRegistry::class)->resolve('recipes.scale')['requires_confirmation']);
        Http::assertNothingSent();
    }

    public function test_prep_generation_and_regeneration_are_deterministic_registered_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $generation = app(HybridIntentRouter::class)->route($this->context(
            'generate prep for Smith Dinner for 50 people',
            $workspace->id
        ));
        $regeneration = app(HybridIntentRouter::class)->route($this->context(
            'regenerate the prep list for event Smith Dinner',
            $workspace->id
        ));

        $this->assertSame('prep.generate', $generation['routing']['action_key']);
        $this->assertSame(50, $generation['slots']['input']['guest_count']);
        $this->assertSame('prep.regenerate', $regeneration['routing']['action_key']);
        $this->assertTrue(app(ToolRegistry::class)->resolve('prep.generate')['requires_confirmation']);
        Http::assertNothingSent();
    }

    public function test_prep_item_lifecycle_requests_use_canonical_tools(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        $complete = app(HybridIntentRouter::class)->route($this->context(
            'mark prep item "Chicken" complete',
            $workspace->id
        ));
        $assign = app(HybridIntentRouter::class)->route($this->context(
            'assign prep item Chicken to John',
            $workspace->id
        ));

        $this->assertSame('prep.items.complete', $complete['routing']['action_key']);
        $this->assertSame('Chicken', $complete['slots']['input']['prep_item_search']);
        $this->assertSame('prep.items.assign', $assign['routing']['action_key']);
        $this->assertSame('John', $assign['slots']['input']['assignee_search']);
        Http::assertNothingSent();
    }

    public function test_prep_read_capabilities_do_not_require_confirmation(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertFalse($registry->resolve('prep.list')['requires_confirmation']);
        $this->assertFalse($registry->resolve('prep.detail')['requires_confirmation']);
        $this->assertFalse($registry->resolve('prep.items.list')['requires_confirmation']);
        $this->assertTrue($registry->resolve('prep.items.update')['requires_confirmation']);
        $this->assertTrue($registry->resolve('prep.items.assign')['requires_confirmation']);
    }

    public function test_active_workspace_pattern_is_resolved_without_ai(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake();
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();

        IntentPattern::query()->create([
            'action_key' => 'events.list',
            'confidence' => 0.99,
            'examples' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'normalized_key' => 'events.list',
            'occurrences' => 5,
            'pattern_json' => ['required_terms' => [['briefing']]],
            'router_version' => 'test',
            'scope' => 'workspace',
            'slot_schema' => [],
            'status' => 'active',
            'successful_executions' => 5,
            'failed_executions' => 0,
            'ambiguity_rate' => 0,
            'workspace_id' => $workspace->id,
        ]);

        $decision = app(HybridIntentRouter::class)->route($this->context(
            'show my briefing',
            $workspace->id
        ));

        $this->assertSame('learned', $decision['routing']['source']);
        $this->assertSame('events.list', $decision['routing']['action_key']);
        Http::assertNothingSent();
    }

    public function test_unknown_message_uses_the_configured_ai_fallback(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output_text' => json_encode([
                    'intent' => 'show_events',
                    'slots' => [
                        'event_id' => null,
                        'event_search' => null,
                        'confidence' => 0.94,
                    ],
                ], JSON_THROW_ON_ERROR),
                'usage' => [],
            ]),
        ]);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $decision = app(HybridIntentRouter::class)->route($this->context(
            'give me the operational calendar overview',
            $workspace->id
        ));

        $this->assertSame('ai', $decision['routing']['source']);
        $this->assertTrue($decision['routing']['ai_fallback_used']);
        $this->assertSame('events.list', $decision['routing']['action_key']);
        Http::assertSentCount(1);
    }

    public function test_successful_ai_observations_promote_only_after_thresholds(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $registry = app(IntentPatternRegistry::class);
        $decision = [
            'intent' => 'show_events',
            'slots' => ['confidence' => 0.98],
            'routing' => [
                'action_key' => 'events.list',
                'confidence' => 0.98,
                'source' => 'ai',
            ],
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $pattern = $registry->observe($workspace->id, $decision, true);
        }

        $this->assertSame('active', $pattern->status);
        $this->assertSame(5, $pattern->occurrences);
        $this->assertSame(5, $pattern->successful_executions);
    }

    public function test_ambiguous_observations_do_not_become_active(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $registry = app(IntentPatternRegistry::class);
        $decision = [
            'intent' => 'show_events',
            'slots' => ['confidence' => 0.40],
            'routing' => [
                'action_key' => 'events.list',
                'confidence' => 0.40,
                'source' => 'ai',
            ],
        ];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $pattern = $registry->observe($workspace->id, $decision, true);
        }

        $this->assertNotSame('active', $pattern->status);
        $this->assertGreaterThan(0, (float) $pattern->ambiguity_rate);
    }

    public function test_workspace_patterns_do_not_cross_tenant_boundaries_and_global_patterns_can_be_reused(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output_text' => json_encode([
                    'intent' => 'show_events',
                    'slots' => [],
                ], JSON_THROW_ON_ERROR),
            ]),
        ]);
        $workspaceA = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $workspaceB = Workspace::query()->create([
            'currency' => 'USD',
            'default_locale' => 'en',
            'name' => 'Second Kitchen',
            'slug' => 'second-kitchen',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        IntentPattern::query()->create([
            'action_key' => 'events.list',
            'confidence' => 0.99,
            'examples' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'normalized_key' => 'events.list',
            'occurrences' => 5,
            'pattern_json' => ['required_terms' => [['privatebriefing']]],
            'router_version' => 'test',
            'scope' => 'workspace',
            'slot_schema' => [],
            'status' => 'active',
            'successful_executions' => 5,
            'failed_executions' => 0,
            'ambiguity_rate' => 0,
            'workspace_id' => $workspaceA->id,
        ]);
        IntentPattern::query()->create([
            'action_key' => 'events.list',
            'confidence' => 0.99,
            'examples' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'normalized_key' => 'events.list',
            'occurrences' => 5,
            'pattern_json' => ['required_terms' => [['commonbriefing']]],
            'router_version' => 'test',
            'scope' => 'global',
            'slot_schema' => [],
            'status' => 'active',
            'successful_executions' => 5,
            'failed_executions' => 0,
            'ambiguity_rate' => 0,
            'workspace_id' => null,
        ]);

        $router = app(HybridIntentRouter::class);
        $this->assertSame('ai', $router->route($this->context('show privatebriefing', $workspaceB->id))['routing']['source']);
        $this->assertSame('learned', $router->route($this->context('show commonbriefing', $workspaceB->id))['routing']['source']);
    }

    public function test_action_policy_keeps_destructive_actions_confirmation_gated(): void
    {
        $policy = app(ActionPolicy::class);

        $this->assertSame('read', $policy->resolve('events.list')['risk']);
        $this->assertFalse($policy->requiresConfirmation('events.list'));
        $this->assertSame('low_write', $policy->resolve('menus.items.move_section')['risk']);
        $this->assertTrue($policy->requiresConfirmation('menus.delete'));
        $this->assertSame('destructive', $policy->resolve('menus.delete')['risk']);
        $this->assertTrue($policy->requiresConfirmation('tasks.create'));
    }

    private function context(string $message, string $workspaceId, string $locale = 'en'): array
    {
        return [
            'available_tools' => app(ToolRegistry::class)->allMetadata(),
            'entity_refs' => [],
            'locale' => $locale,
            'message' => $message,
            'message_id' => 'test-message',
            'recent_entity_refs' => [],
            'recent_messages' => [],
            'system_instructions' => 'Use registered tools.',
            'timezone' => 'UTC',
            'workspace_id' => $workspaceId,
        ];
    }
}
