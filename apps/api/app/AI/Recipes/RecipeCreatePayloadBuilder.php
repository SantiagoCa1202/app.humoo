<?php

namespace App\AI\Recipes;

use Illuminate\Support\Str;

/** The single conversion from the flexible conversational draft to StoreRecipeRequest input. */
class RecipeCreatePayloadBuilder
{
    public function __construct(private UnitResolver $unitResolver)
    {
    }

    public function build(array $draft): array
    {
        $issues = [];
        $name = trim((string) ($draft['name'] ?? ''));
        if ($name === '') {
            $issues[] = ['code' => 'missing_name'];
        }

        $yield = is_array($draft['yield'] ?? null) ? $draft['yield'] : [
            'quantity' => $draft['yield'] ?? null,
            'unit_key' => $draft['yield_unit'] ?? null,
        ];
        $yieldQuantity = is_numeric($yield['quantity'] ?? null) ? (float) $yield['quantity'] : null;
        $yieldUnitId = $this->unitResolver->idFor($yield['unit_key'] ?? null);
        if (isset($yield['quantity_min'], $yield['quantity_max'])) {
            $issues[] = [
                'code' => 'yield_range',
                'min' => $yield['quantity_min'],
                'max' => $yield['quantity_max'],
                'unit' => $yield['unit_key'] ?? null,
            ];
        } elseif ($yieldQuantity === null || $yieldQuantity <= 0) {
            $issues[] = ['code' => 'missing_yield'];
        } elseif (!$yieldUnitId) {
            $issues[] = ['code' => 'unknown_yield_unit', 'unit' => $yield['unit_key'] ?? null];
        }

        $ingredients = [];
        foreach (array_values($draft['ingredients'] ?? []) as $index => $ingredient) {
            if (!is_array($ingredient)) {
                continue;
            }
            $ingredientName = trim((string) ($ingredient['ingredient_name'] ?? $ingredient['name'] ?? ''));
            $range = isset($ingredient['quantity_min'], $ingredient['quantity_max']);
            if ($range) {
                $issues[] = ['code' => 'quantity_range', 'ingredient' => $ingredientName, 'min' => $ingredient['quantity_min'], 'max' => $ingredient['quantity_max'], 'unit' => $ingredient['unit_key'] ?? $ingredient['unit'] ?? null];
                continue;
            }
            $quantity = is_numeric($ingredient['quantity'] ?? null) ? (float) $ingredient['quantity'] : null;
            $unitKey = $ingredient['unit_key'] ?? $ingredient['unit'] ?? null;
            $unitId = $this->unitResolver->idFor($unitKey);
            if ($ingredientName === '' || $quantity === null || $quantity <= 0 || !$unitId) {
                $issues[] = ['code' => 'invalid_ingredient', 'ingredient' => $ingredientName, 'index' => $index, 'unit' => $unitKey];
                continue;
            }
            $ingredients[] = array_filter([
                'ingredient_name' => $ingredientName,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'preparation' => $ingredient['preparation'] ?? $ingredient['preparation_note'] ?? null,
                'optional' => (bool) ($ingredient['optional'] ?? false),
                'notes' => $this->ingredientNotes($ingredient),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }
        if ($ingredients === []) {
            $issues[] = ['code' => 'missing_ingredients'];
        }

        $steps = collect($draft['steps'] ?? [])->map(function (mixed $step): ?array {
            $instruction = is_array($step) ? ($step['instruction'] ?? null) : $step;
            $instruction = trim((string) $instruction);
            return $instruction === '' ? null : array_filter([
                'title' => is_array($step) ? ($step['title'] ?? null) : null,
                'instruction' => $instruction,
                'duration_minutes' => is_array($step) ? ($step['duration_minutes'] ?? null) : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        })->filter()->values()->all();
        if ($steps === []) {
            $issues[] = ['code' => 'missing_steps'];
        }

        if ($issues !== []) {
            return ['status' => 'clarification', 'draft' => $draft, 'issues' => $issues];
        }

        return ['status' => 'ready', 'draft' => $draft, 'issues' => [], 'payload' => [
            'name' => $name,
            'description' => $draft['description'] ?? null,
            'status' => 'draft',
            'metadata' => ['source' => $draft['source'] ?? 'user_provided'],
            'version' => [
                'name' => $name,
                'description' => $draft['description'] ?? null,
                'status' => 'draft',
                'ingredients' => $ingredients,
                'steps' => $steps,
                'yields' => [[
                    'quantity' => $yieldQuantity,
                    'unit_id' => $yieldUnitId,
                    'label' => $yield['label'] ?? null,
                    'is_default' => true,
                ]],
            ],
        ]];
    }

    /** @param array<string, mixed> $ingredient */
    private function ingredientNotes(array $ingredient): ?string
    {
        $notes = array_filter([
            $ingredient['notes'] ?? null,
            isset($ingredient['group']) ? 'Group: '.$ingredient['group'] : null,
            isset($ingredient['alternatives']) ? 'Alternatives: '.implode(' / ', (array) $ingredient['alternatives']) : null,
            isset($ingredient['quantity_text']) ? 'Quantity: '.$ingredient['quantity_text'] : null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return $notes === [] ? null : implode(' | ', $notes);
    }
}
