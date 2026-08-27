<?php

namespace App\AI\Intent;

use Illuminate\Support\Str;

/**
 * Identifies high-confidence document forms before a learned or AI router can
 * mistake their individual tokens for an entity reference.
 */
final class MessageShapeDetector
{
    /** @return array{message_shape: string, action_key_candidate: ?string, confidence: float} */
    public function detect(string $message): array
    {
        $lines = collect(preg_split('/\R/u', $message) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();
        $normalizedLines = $lines->map(fn (string $line): string => $this->normalize($line));
        $normalized = $this->normalize($message);
        $tokens = preg_split('/[^[:alnum:]]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $hasCreateVerb = collect($tokens)->contains(fn (string $token): bool => $this->near($token, ['crea', 'crear', 'create'], 1));
        $hasRecipeSignal = collect($tokens)->contains(fn (string $token): bool => $this->near($token, ['receta', 'recipe'], 2));
        $ingredientsHeading = $normalizedLines->contains(fn (string $line): bool => preg_match('/^ingredients?\b|^ingredientes?\b/u', $line) === 1);
        $preparationAt = $normalizedLines->search(fn (string $line): bool => preg_match('/^(preparation|preparacion|instructions?|steps?|method|metodo)\b/u', $line) === 1);
        $ingredientLines = $normalizedLines
            ->take($preparationAt === false ? null : $preparationAt)
            ->filter(fn (string $line): bool => $this->looksLikeIngredient($line))
            ->count();
        $stepLines = $preparationAt === false
            ? 0
            : $normalizedLines->slice($preparationAt + 1)->filter(fn (string $line): bool => mb_strlen($line) >= 8)->count();

        if (
            $lines->count() >= 8
            && $hasCreateVerb
            && ($ingredientsHeading || $hasRecipeSignal)
            && $preparationAt !== false
            && $ingredientLines >= 3
            && $stepLines >= 3
        ) {
            return [
                'message_shape' => 'recipe_document_create',
                'action_key_candidate' => 'recipes.create',
                'confidence' => 0.99,
            ];
        }

        return [
            'message_shape' => 'freeform',
            'action_key_candidate' => null,
            'confidence' => 0.0,
        ];
    }

    private function looksLikeIngredient(string $line): bool
    {
        if (preg_match('/^(ingredients?|ingredientes?|preparation|preparacion|instructions?|steps?|method|metodo)\b/u', $line) === 1) {
            return false;
        }

        return preg_match('/^(?:\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+|[¼½¾]|una?\s+(?:pizca|cantidad)|al\s+gusto)\b/u', $line) === 1;
    }

    /** @param array<int, string> $candidates */
    private function near(string $token, array $candidates, int $maximumDistance): bool
    {
        foreach ($candidates as $candidate) {
            if ($token === $candidate || (mb_strlen($token) >= 4 && levenshtein($token, $candidate) <= $maximumDistance)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KD) ?: $value;
        }

        return Str::lower(Str::ascii($value));
    }
}
