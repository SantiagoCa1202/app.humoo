<?php

namespace App\AI\Advisory;

use App\Models\Unit;
use Illuminate\Support\Str;

/** Converts a conversational proposal into the existing recipes.create input. */
class RecipeDraftPayloadMapper
{
    public function toCreateInput(array $draft): ?array
    {
        $yieldUnitId = $this->unitId($draft['yield_unit'] ?? null);
        $yield = is_numeric($draft['yield'] ?? null) ? (float) $draft['yield'] : null;
        $ingredients = collect($draft['ingredients'] ?? [])->map(function (mixed $ingredient): ?array {
            if (!is_array($ingredient) || !is_numeric($ingredient['quantity'] ?? null)) {
                return null;
            }
            $unitId = $this->unitId($ingredient['unit'] ?? null);
            $name = trim((string) ($ingredient['name'] ?? ''));

            return $unitId && $name !== '' ? [
                'ingredient_name' => $name,
                'quantity' => (float) $ingredient['quantity'],
                'unit_id' => $unitId,
                'preparation' => $ingredient['preparation_note'] ?? null,
            ] : null;
        })->all();

        if (!$yieldUnitId || !$yield || in_array(null, $ingredients, true) || $ingredients === []) {
            return null;
        }

        return [
            'name' => trim((string) ($draft['name'] ?? '')),
            'description' => $draft['description'] ?? null,
            'metadata' => ['source' => 'ai_assisted', 'proposal_source' => 'ai_generated_proposal'],
            'status' => 'draft',
            'version' => [
                'name' => trim((string) ($draft['name'] ?? '')),
                'description' => $draft['description'] ?? null,
                'ingredients' => $ingredients,
                'steps' => collect($draft['steps'] ?? [])->filter('is_string')->map(fn (string $instruction): array => ['instruction' => $instruction])->values()->all(),
                'yields' => [[
                    'quantity' => $yield,
                    'unit_id' => $yieldUnitId,
                    'is_default' => true,
                ]],
            ],
        ];
    }

    private function unitId(mixed $value): ?string
    {
        $value = Str::lower(trim((string) $value));
        if ($value === '') {
            return null;
        }
        $candidates = array_values(array_unique([$value, rtrim($value, 's'), match ($value) {
            'gallons', 'gallon' => 'gal',
            'ounces', 'ounce' => 'oz',
            'pounds', 'pound' => 'lb',
            default => $value,
        }]));

        return Unit::query()->where(function ($query) use ($candidates): void {
            foreach ($candidates as $candidate) {
                $query->orWhereRaw('LOWER(`key`) = ?', [$candidate])
                    ->orWhereRaw('LOWER(name) = ?', [$candidate])
                    ->orWhereRaw('LOWER(symbol) = ?', [$candidate]);
            }
        })->value('id');
    }
}
