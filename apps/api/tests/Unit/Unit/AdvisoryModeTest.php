<?php

namespace Tests\Unit\Unit;

use App\AI\Advisory\AdvisoryOrchestrator;
use App\AI\Advisory\PortionAnalysisService;
use App\AI\Advisory\RecipeDraftScalingService;
use App\AI\Contracts\AIProvider;
use App\AI\Presentation\ComponentRegistry;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use ReflectionMethod;
use Tests\TestCase;

class AdvisoryModeTest extends TestCase
{
    public function test_rule_based_router_distinguishes_read_action_advisory_and_generative_requests(): void
    {
        $provider = new RuleBasedAIProvider();

        $read = $provider->generate(['message' => 'show me the menu Uptown', 'locale' => 'en']);
        $action = $provider->generate(['message' => 'change chicken to 5 oz', 'locale' => 'en']);
        $advisory = $provider->generate(['message' => 'do you think we should increase chicken portions?', 'locale' => 'en']);
        $generative = $provider->generate(['message' => 'give me a recipe for ranch dressing', 'locale' => 'en']);

        $this->assertSame('show_menu', $read['intent']);
        $this->assertSame('tool_action', $action['intent']);
        $this->assertSame('advisory', $advisory['intent']);
        $this->assertSame('advisory', $advisory['interaction_mode']);
        $this->assertSame('generative', $generative['intent']);
        $this->assertSame('generative', $generative['interaction_mode']);
    }

    public function test_portion_analysis_keeps_calculations_deterministic_and_warns_when_outcomes_are_missing(): void
    {
        $analysis = (new PortionAnalysisService())->analyze([
            'events.list' => ['result_ref_json' => ['items' => [[
                'guest_count_confirmed' => 100,
            ]]]],
            'menus.search' => ['result_ref_json' => ['items' => [[
                'id' => 'menu-1',
                'name' => 'Uptown',
                'guest_count' => 100,
                'sections' => [['items' => [[
                    'name' => 'Chicken',
                    'quantity_per_guest' => 4,
                    'serving_unit' => 'oz',
                ]]]],
            ]]]],
        ], 'portion_analysis');

        $this->assertSame(100, $analysis['facts']['total_guests']);
        $this->assertSame(400.0, $analysis['facts']['menus'][0]['items'][0]['deterministic_planned_total']);
        $this->assertStringContainsString('leftovers', $analysis['warnings'][0]);
    }

    public function test_recipe_draft_scaling_never_persists_and_scales_compatible_units(): void
    {
        $scaled = (new RecipeDraftScalingService())->scale([
            'yield' => 1,
            'yield_unit' => 'gallons',
            'ingredients' => [['name' => 'Buttermilk', 'quantity' => 0.25, 'unit' => 'gallons']],
        ], 5, 'gallons');

        $this->assertSame(5.0, $scaled['yield']);
        $this->assertSame(1.25, $scaled['ingredients'][0]['quantity']);
        $this->assertTrue(ComponentRegistry::supports('advisory.result@1'));
        $this->assertTrue(ComponentRegistry::supports('recipe.draft@1'));
    }

    public function test_recipe_draft_normalization_filters_non_array_ingredients_without_passing_collection_keys(): void
    {
        $orchestrator = new AdvisoryOrchestrator(
            $this->createMock(AIProvider::class),
            $this->createMock(ToolExecutor::class),
            new ToolRegistry(),
            new PortionAnalysisService(),
            new RecipeDraftScalingService()
        );
        $method = new ReflectionMethod($orchestrator, 'normalizeRecipeDraft');

        $draft = $method->invoke($orchestrator, [
            'ingredients' => [
                'invalid value',
                ['name' => 'Buttermilk', 'quantity' => 4, 'unit' => 'cups'],
            ],
        ]);

        $this->assertSame([
            ['name' => 'Buttermilk', 'quantity' => 4.0, 'unit' => 'cups', 'preparation_note' => null],
        ], $draft['ingredients']);
    }
}
