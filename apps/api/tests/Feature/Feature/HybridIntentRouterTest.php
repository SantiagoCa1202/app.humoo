<?php

namespace Tests\Feature\Feature;

use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Policy\ActionPolicy;
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

    private function context(string $message, string $workspaceId): array
    {
        return [
            'available_tools' => app(ToolRegistry::class)->allMetadata(),
            'entity_refs' => [],
            'locale' => 'en',
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
