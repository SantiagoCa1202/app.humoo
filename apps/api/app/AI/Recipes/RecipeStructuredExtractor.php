<?php

namespace App\AI\Recipes;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Log;

/** AI is used only after deterministic extraction cannot form a usable draft. */
class RecipeStructuredExtractor
{
    public function __construct(private AIProvider $provider, private UnitNormalizer $units)
    {
    }

    public function extract(string $rawRecipeText, string $locale): ?array
    {
        try {
            $result = $this->provider->generate([
                'advisory_request' => [
                    'interaction_mode' => 'recipe_ingestion',
                    'raw_recipe_text' => $rawRecipeText,
                    'allowed_unit_aliases' => $this->units->aliases(),
                    'schema' => 'RecipeDraft',
                ],
                'advisory_context' => [],
                'locale' => $locale,
                'recent_messages' => [['sender_type' => 'user', 'content_text' => $rawRecipeText]],
                'system_instructions' => 'Extract only facts explicitly present in the user recipe. Do not add ingredients, quantities, yields, or culinary assumptions. Preserve quantity ranges. Return recipe_draft null when facts are missing.',
            ]);
        } catch (\Throwable $exception) {
            Log::info('recipe_ingestion.ai_extraction_unavailable', ['exception_class' => class_basename($exception)]);
            return null;
        }

        return is_array($result['recipe_draft'] ?? null) ? $result['recipe_draft'] : null;
    }
}
