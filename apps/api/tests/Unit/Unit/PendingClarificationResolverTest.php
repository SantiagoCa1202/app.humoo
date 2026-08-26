<?php

namespace Tests\Unit\Unit;

use App\AI\Clarifications\PendingClarificationResolver;
use Tests\TestCase;

class PendingClarificationResolverTest extends TestCase
{
    public function test_it_resolves_a_recipe_quantity_range_without_persisting_a_recipe(): void
    {
        $conversation = new class {
            public string $id = '01j00000000000000000000001';
            public array $metadata = [
                'active_recipe_draft' => [
                    'ingredients' => [[
                        'ingredient_name' => 'Sal',
                        'quantity_min' => 1.5,
                        'quantity_max' => 2.0,
                        'unit_key' => 'tbsp',
                    ]],
                ],
                'pending_clarifications' => [[
                    'allow_custom' => true,
                    'clarification_id' => '01j00000000000000000000002',
                    'conversation_id' => '01j00000000000000000000001',
                    'expected_type' => 'number',
                    'ingredient_index' => 0,
                    'options' => [['id' => 'min', 'value' => 1.5], ['id' => 'max', 'value' => 2.0]],
                    'status' => 'pending',
                    'workflow' => 'recipes.create',
                    'workspace_id' => '01j00000000000000000000003',
                ]],
            ];

            public function forceFill(array $attributes): self
            {
                $this->metadata = $attributes['metadata'];

                return $this;
            }

            public function save(): bool
            {
                return true;
            }
        };

        $result = app(PendingClarificationResolver::class)->resolve(
            $conversation,
            '01j00000000000000000000003',
            '01j00000000000000000000002',
            ['selected_option_id' => 'min']
        );

        $ingredient = $result['draft']['ingredients'][0];
        $this->assertSame(1.5, $ingredient['quantity']);
        $this->assertArrayNotHasKey('quantity_min', $ingredient);
        $this->assertArrayNotHasKey('quantity_max', $ingredient);
        $this->assertSame('resolved', $result['clarification']['status']);
        $this->assertSame('resolved', $conversation->metadata['pending_clarifications'][0]['status']);
    }
}
