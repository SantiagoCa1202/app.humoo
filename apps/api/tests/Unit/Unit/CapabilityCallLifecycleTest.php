<?php

namespace Tests\Unit\Unit;

use App\AI\Capabilities\CapabilityCall;
use App\AI\Capabilities\CapabilityCallValidator;
use App\AI\Capabilities\CapabilityFunctionRouter;
use App\AI\Capabilities\CapabilityRegistry;
use App\AI\Recipes\RecipeCreatePayloadBuilder;
use App\AI\Recipes\UnitResolver;
use App\AI\Providers\OpenAIProvider;
use Mockery;
use Tests\TestCase;

class CapabilityCallLifecycleTest extends TestCase
{
    public function test_incomplete_restaurant_recipe_call_is_structurally_valid_and_keeps_all_fields(): void
    {
        $draft = $this->restaurantRecipeDraft();
        $provider = Mockery::mock(OpenAIProvider::class);
        $provider->shouldReceive('callFunction')->twice()->andReturn(
            [
                'function_name' => 'select_capability',
                'arguments' => ['action_key' => 'recipes.create', 'confidence' => 0.99],
                'call_id' => 'route-call',
                'usage' => [],
            ],
            [
                'function_name' => 'recipes_create',
                'arguments' => $draft,
                'call_id' => 'recipe-call',
                'usage' => [],
            ],
        );

        $call = (new CapabilityFunctionRouter(
            $provider,
            new CapabilityRegistry(),
            new CapabilityCallValidator(new CapabilityRegistry()),
        ))->route([
            'correlation_id' => '01j00000000000000000000001',
            'message' => 'restaurant baguette recipe',
            'workspace_id' => '01j00000000000000000000002',
            'workspace' => (object) ['id' => '01j00000000000000000000002'],
            'user' => (object) ['id' => '01j00000000000000000000003'],
        ]);

        $this->assertInstanceOf(CapabilityCall::class, $call);
        $this->assertCount(20, $call->arguments['ingredients']);
        $this->assertCount(9, $call->arguments['steps']);
        $this->assertNull($call->arguments['ingredients'][17]['quantity']);
        $this->assertNull($call->arguments['ingredients'][17]['unit_key']);
    }

    public function test_missing_structured_ingredient_values_become_precise_domain_issues(): void
    {
        $unitResolver = Mockery::mock(UnitResolver::class);
        $unitResolver->shouldReceive('idFor')->andReturnUsing(fn (?string $unit): ?string => $unit === null ? null : 'unit-'.$unit);

        $result = (new RecipeCreatePayloadBuilder($unitResolver))->build($this->restaurantRecipeDraft());

        $this->assertSame('clarification', $result['status']);
        $this->assertCount(20, $result['draft']['ingredients']);
        $this->assertContains(['code' => 'ingredient_quantity_missing', 'field_path' => 'ingredients.17.quantity', 'ingredient' => 'mayonesa', 'index' => 17, 'reason_code' => 'missing_quantity'], $result['issues']);
        $this->assertContains(['code' => 'ingredient_unit_missing', 'field_path' => 'ingredients.17.unit_key', 'ingredient' => 'mayonesa', 'index' => 17, 'reason_code' => 'missing_unit'], $result['issues']);
    }

    public function test_negative_structured_quantity_is_domain_invalid(): void
    {
        $unitResolver = Mockery::mock(UnitResolver::class);
        $unitResolver->shouldReceive('idFor')->andReturnUsing(fn (?string $unit): ?string => $unit === null ? null : 'unit-'.$unit);
        $draft = $this->restaurantRecipeDraft();
        $draft['ingredients'][0]['quantity'] = -4;

        $result = (new RecipeCreatePayloadBuilder($unitResolver))->build($draft);

        $this->assertContains(['code' => 'invalid_ingredient', 'field_path' => 'ingredients.0.quantity', 'ingredient' => 'ingredient 1', 'index' => 0, 'unit' => 'each', 'reason_code' => 'invalid_quantity'], $result['issues']);
    }

    /** @return array<string, mixed> */
    private function restaurantRecipeDraft(): array
    {
        $ingredients = collect(range(1, 17))->map(fn (int $number): array => [
            'ingredient_name' => 'ingredient '.$number,
            'quantity' => 1,
            'quantity_min' => null,
            'quantity_max' => null,
            'quantity_text' => null,
            'unit_key' => 'each',
            'preparation' => null,
            'notes' => null,
            'optional' => false,
            'group' => null,
            'alternatives' => [],
        ])->all();
        $ingredients[] = [
            'ingredient_name' => 'mayonesa', 'quantity' => null, 'quantity_min' => null, 'quantity_max' => null,
            'quantity_text' => null, 'unit_key' => null, 'preparation' => null, 'notes' => 'capa muy fina',
            'optional' => false, 'group' => null, 'alternatives' => [],
        ];
        $ingredients[] = [
            'ingredient_name' => 'parmesano rallado', 'quantity' => null, 'quantity_min' => null, 'quantity_max' => null,
            'quantity_text' => null, 'unit_key' => null, 'preparation' => null, 'notes' => null,
            'optional' => false, 'group' => null, 'alternatives' => [],
        ];
        $ingredients[] = [
            'ingredient_name' => 'orégano', 'quantity' => null, 'quantity_min' => null, 'quantity_max' => null,
            'quantity_text' => null, 'unit_key' => null, 'preparation' => null, 'notes' => 'sobre la lechuga',
            'optional' => false, 'group' => null, 'alternatives' => [],
        ];

        return [
            'name' => 'Baguette Italiano',
            'description' => null,
            'yield' => ['quantity' => null, 'quantity_min' => 2, 'quantity_max' => 3, 'unit_key' => 'portion', 'label' => 'porciones'],
            'ingredients' => $ingredients,
            'steps' => collect(range(1, 9))->map(fn (int $number): array => [
                'title' => null, 'instruction' => 'Step '.$number, 'duration_minutes' => null,
            ])->all(),
            'source' => 'structured_ai',
        ];
    }
}
