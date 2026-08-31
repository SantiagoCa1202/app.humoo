<?php

namespace Tests\Unit\Unit;

use App\AI\Capabilities\Drafts\RecipeCreateDraftData;
use App\AI\Recipes\RecipeCreatePayloadBuilder;
use App\AI\Recipes\UnitResolver;
use Mockery;
use Tests\TestCase;

class RecipeCreateDraftDataTest extends TestCase
{
    public function test_complete_function_recipe_draft_uses_canonical_units_without_local_normalization(): void
    {
        $draft = RecipeCreateDraftData::from([
            'name' => 'Lemon Herb Chicken',
            'description' => null,
            'yield' => ['quantity' => 25, 'quantity_min' => null, 'quantity_max' => null, 'unit_key' => 'portion', 'label' => '25 portions'],
            'ingredients' => [
                $this->ingredient('chicken breast', 15, 'lb'),
                $this->ingredient('olive oil', 1, 'cup'),
                $this->ingredient('lemon juice', 0.5, 'cup'),
                $this->ingredient('garlic', 0.25, 'cup'),
                $this->ingredient('rosemary', 3, 'tbsp'),
                $this->ingredient('thyme', 2, 'tbsp'),
                $this->ingredient('salt', 2, 'tbsp'),
                $this->ingredient('pepper', 1, 'tbsp'),
            ],
            'steps' => [
                ['title' => null, 'instruction' => 'Marinate for 2 hours.', 'duration_minutes' => 120],
                ['title' => null, 'instruction' => 'Bake at 375 F until the internal temperature reaches 165 F.', 'duration_minutes' => null],
                ['title' => null, 'instruction' => 'Rest for 10 minutes.', 'duration_minutes' => 10],
                ['title' => null, 'instruction' => 'Slice and serve.', 'duration_minutes' => null],
            ],
            'source' => null,
        ])->toArray();

        $unitResolver = Mockery::mock(UnitResolver::class);
        $unitResolver->shouldReceive('idFor')->andReturnUsing(fn (?string $unit): ?string => $unit === null ? null : 'unit-'.$unit);

        $result = (new RecipeCreatePayloadBuilder($unitResolver))->build($draft);

        $this->assertSame('structured_ai', $draft['source']);
        $this->assertSame('lb', $draft['ingredients'][0]['unit_key']);
        $this->assertSame('cup', $draft['ingredients'][1]['unit_key']);
        $this->assertSame('ready', $result['status']);
        $this->assertCount(8, $result['payload']['version']['ingredients']);
        $this->assertCount(4, $result['payload']['version']['steps']);
    }

    /** @return array<string, mixed> */
    private function ingredient(string $name, float|int $quantity, string $unit): array
    {
        return [
            'ingredient_name' => $name,
            'quantity' => $quantity,
            'quantity_min' => null,
            'quantity_max' => null,
            'quantity_text' => null,
            'unit_key' => $unit,
            'preparation' => null,
            'notes' => null,
            'optional' => false,
            'group' => null,
            'alternatives' => [],
        ];
    }
}
