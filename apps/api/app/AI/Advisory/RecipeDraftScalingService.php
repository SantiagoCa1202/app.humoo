<?php

namespace App\AI\Advisory;

class RecipeDraftScalingService
{
    public function scale(array $draft, float $targetYield, string $targetUnit): array
    {
        $currentYield = is_numeric($draft['yield'] ?? null) ? (float) $draft['yield'] : 0.0;
        $currentUnit = strtolower(trim((string) ($draft['yield_unit'] ?? '')));
        $targetUnit = strtolower(trim($targetUnit));

        if ($currentYield <= 0 || $currentUnit === '' || $currentUnit !== $targetUnit || $targetYield <= 0) {
            return $draft;
        }

        $factor = $targetYield / $currentYield;
        $draft['yield'] = $targetYield;
        $draft['yield_unit'] = $targetUnit;
        $draft['ingredients'] = collect($draft['ingredients'] ?? [])
            ->map(function (mixed $ingredient) use ($factor): mixed {
                if (!is_array($ingredient) || !is_numeric($ingredient['quantity'] ?? null)) {
                    return $ingredient;
                }

                $ingredient['quantity'] = round((float) $ingredient['quantity'] * $factor, 4);

                return $ingredient;
            })->values()->all();
        $draft['notes'] = trim(implode(' ', array_filter([
            $draft['notes'] ?? null,
            'Scaled deterministically by '.round($factor, 4).'.',
        ])));

        return $draft;
    }
}
