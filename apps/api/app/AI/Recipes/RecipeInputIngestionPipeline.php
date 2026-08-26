<?php

namespace App\AI\Recipes;

use Illuminate\Support\Facades\Log;

/**
 * Turns pasted user recipes into a safe, non-persistent RecipeDraft and then
 * StoreRecipeRequest-compatible input. Intent selection remains outside here.
 */
class RecipeInputIngestionPipeline
{
    public function __construct(
        private FractionNormalizer $fractions,
        private UnitNormalizer $units,
        private RecipeCreatePayloadBuilder $payloadBuilder,
        private RecipeStructuredExtractor $structuredExtractor
    ) {
    }

    public function ingest(array $input, ?string $rawRecipeText, string $locale = 'en'): array
    {
        Log::info('recipe_ingestion.started', ['has_raw_text' => trim((string) $rawRecipeText) !== '', 'source' => 'user_provided']);

        $providedDraft = is_array($input['recipe_draft'] ?? null) ? $input['recipe_draft'] : $input;
        if (isset($providedDraft['version']) && is_array($providedDraft['version'])) {
            return ['status' => 'ready', 'draft' => $providedDraft, 'payload' => $providedDraft, 'issues' => []];
        }

        $draft = $this->extractDeterministically($rawRecipeText ?? '', $input);
        $result = $this->payloadBuilder->build($draft);
        if ($this->requiresStructuredExtraction($result) && trim((string) $rawRecipeText) !== '') {
            $extracted = $this->structuredExtractor->extract((string) $rawRecipeText, $locale);
            if ($extracted !== null) {
                $draft = $this->canonicalizeDraft(array_replace_recursive($draft, $extracted));
                $result = $this->payloadBuilder->build($draft);
                Log::info('recipe_ingestion.ai_extraction_used', [
                    'ingredient_count' => count($draft['ingredients'] ?? []),
                    'step_count' => count($draft['steps'] ?? []),
                ]);
            }
        }
        Log::info('recipe_ingestion.deterministic_completed', [
            'ingredient_count' => count($draft['ingredients'] ?? []),
            'step_count' => count($draft['steps'] ?? []),
            'yield_detected' => isset($draft['yield']['quantity']),
            'unresolved_unit_count' => count(array_filter($result['issues'] ?? [], fn (array $issue): bool => str_contains($issue['code'], 'unit'))),
            'ambiguous_quantity_count' => count(array_filter($result['issues'] ?? [], fn (array $issue): bool => $issue['code'] === 'quantity_range')),
            'extraction_source' => 'deterministic',
        ]);
        if ($result['status'] !== 'ready') {
            Log::info('recipe_ingestion.clarification_required', ['issue_codes' => array_values(array_unique(array_column($result['issues'], 'code')))]);
        } else {
            Log::info('recipe_ingestion.preview_ready', ['ingredient_count' => count($draft['ingredients']), 'step_count' => count($draft['steps'])]);
        }

        return $result;
    }

    private function extractDeterministically(string $raw, array $partial): array
    {
        $raw = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $raw);
        $raw = preg_replace('/[ \t]+/', ' ', $raw) ?? $raw;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), static fn (string $line): bool => $line !== ''));
        $title = $this->titleFrom($lines[0] ?? '');
        $yield = $this->yieldFrom($title);
        foreach ($lines as $line) {
            $yield ??= $this->yieldFrom($line);
        }
        $name = $this->nameFromTitle($title);

        $preparationAt = null;
        $inlinePreparation = null;
        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:preparaci[oó]n|preparation|instructions?|steps?|m[eé]todo)\s*:\s*(.*)$/iu', $line, $matches)) {
                $preparationAt = $index;
                $inlinePreparation = trim($matches[1]);
                break;
            }
        }
        $ingredientLines = array_slice($lines, 1, $preparationAt === null ? null : $preparationAt - 1);
        $ingredients = [];
        foreach ($ingredientLines as $line) {
            if (preg_match('/^(ingredientes?|ingredient(?:s)?)\b|^(ingrediente\s+cantidad|ingredient\s+quantity)$/iu', $line)) {
                continue;
            }
            $ingredient = $this->ingredientFrom($line);
            if ($ingredient !== null) {
                $ingredients[] = $ingredient;
            }
        }
        $preparationLines = $preparationAt === null ? [] : array_slice($lines, $preparationAt + 1);
        if ($inlinePreparation !== null && $inlinePreparation !== '') {
            array_unshift($preparationLines, $inlinePreparation);
        }
        $steps = $this->stepsFrom($preparationLines);

        return $this->canonicalizeDraft(array_replace_recursive([
            'name' => $name,
            'yield' => $yield,
            'ingredients' => $ingredients,
            'steps' => $steps,
            'source' => 'user_provided',
        ], $this->normalizePartial($partial)));
    }

    private function normalizePartial(array $partial): array
    {
        $draft = is_array($partial['recipe_draft'] ?? null) ? $partial['recipe_draft'] : $partial;
        return array_filter([
            'name' => trim((string) ($draft['name'] ?? '')) ?: null,
            'description' => $draft['description'] ?? null,
            'yield' => is_array($draft['yield'] ?? null) ? $draft['yield'] : (isset($draft['yield']) || isset($draft['yield_unit']) ? [
                'quantity' => $draft['yield'] ?? null,
                'unit_key' => $draft['yield_unit'] ?? null,
            ] : null),
            'ingredients' => is_array($draft['ingredients'] ?? null) ? $draft['ingredients'] : null,
            'steps' => is_array($draft['steps'] ?? null) ? $draft['steps'] : null,
            'source' => $draft['source'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function requiresStructuredExtraction(array $result): bool
    {
        return collect($result['issues'] ?? [])->contains(fn (array $issue): bool => in_array($issue['code'] ?? null, [
            'missing_name', 'missing_yield', 'unknown_yield_unit', 'invalid_ingredient', 'missing_ingredients', 'missing_steps',
        ], true));
    }

    private function canonicalizeDraft(array $draft): array
    {
        if (is_array($draft['yield'] ?? null)) {
            $draft['yield']['unit_key'] = $this->units->normalize($draft['yield']['unit_key'] ?? $draft['yield']['unit'] ?? null);
        } elseif (isset($draft['yield']) || isset($draft['yield_unit'])) {
            $draft['yield'] = [
                'quantity' => $draft['yield'] ?? null,
                'unit_key' => $this->units->normalize($draft['yield_unit'] ?? null),
            ];
        }
        $draft['ingredients'] = collect($draft['ingredients'] ?? [])->map(function (mixed $ingredient): mixed {
            if (!is_array($ingredient)) {
                return $ingredient;
            }
            $ingredient['ingredient_name'] = trim((string) ($ingredient['ingredient_name'] ?? $ingredient['name'] ?? ''));
            $ingredient['unit_key'] = $this->units->normalize($ingredient['unit_key'] ?? $ingredient['unit'] ?? null);
            if (isset($ingredient['quantity']) && is_string($ingredient['quantity'])) {
                $range = $this->fractions->parseRange($ingredient['quantity']);
                if ($range !== null) {
                    unset($ingredient['quantity']);
                    $ingredient['quantity_min'] = $range['min'];
                    $ingredient['quantity_max'] = $range['max'];
                } else {
                    $ingredient['quantity'] = $this->fractions->parse($ingredient['quantity']);
                }
            }
            return $ingredient;
        })->values()->all();
        $draft['source'] = 'user_provided';

        return $draft;
    }

    private function titleFrom(string $line): string
    {
        $line = preg_replace('/^\s*(?:crea(?:r)?|guarda(?:r)?|a[nñ]ade|registra(?:r)?|quiero crear|create|save|add)\s+(?:esta\s+)?(?:receta|recipe)\s*:?\s*/iu', '', $line) ?? $line;
        $line = preg_replace('/^\s*(?:receta|recipe)\s*:\s*/iu', '', $line) ?? $line;
        return trim($line);
    }

    private function nameFromTitle(string $title): string
    {
        $name = preg_replace('/\s*[–—-]\s*(?:aprox\.?|aproximadamente|approximately|about|~|rinde|yield|makes|rendimiento|para)\b.*$/iu', '', $title) ?? $title;
        return trim($name);
    }

    private function yieldFrom(string $value): ?array
    {
        $unitPattern = $this->unitPattern();
        $quantityPattern = $this->quantityPattern();
        if (!preg_match('/(?:aprox\.?|aproximadamente|approximately|about|~|rinde|yield|makes|rendimiento|para)\s*:?\s*('.$quantityPattern.')\s+('.$unitPattern.')\b/iu', $value, $matches)) {
            return null;
        }
        $quantity = $this->fractions->parse($matches[1]);
        $unit = $this->units->normalize($matches[2]);
        return $quantity === null || $unit === null ? null : [
            'quantity' => $quantity,
            'unit_key' => $unit,
            'label' => trim($matches[1].' '.$matches[2]),
            'approximate' => (bool) preg_match('/aprox|approximately|about|~/iu', $matches[0]),
        ];
    }

    private function ingredientFrom(string $line): ?array
    {
        $line = trim(preg_replace('/^[\-•*]+\s*/u', '', $line) ?? $line);
        $unitPattern = $this->unitPattern();
        $quantityPattern = $this->quantityPattern();
        $matches = [];
        if (!preg_match('/^(?<name>.+?)\s*(?:\||:|\s-\s|\t|\s{2,})?\s+(?<quantity>'.$quantityPattern.')\s+(?<unit>'.$unitPattern.')\b(?<tail>.*)$/iu', $line, $matches)
            && !preg_match('/^(?<quantity>'.$quantityPattern.')\s+(?<unit>'.$unitPattern.')\s+(?<name>.+?)(?<tail>)$/iu', $line, $matches)) {
            return null;
        }
        $name = trim((string) $matches['name']);
        $unit = $this->units->normalize($matches['unit']);
        if ($name === '' || $unit === null) {
            return null;
        }
        $range = $this->fractions->parseRange($matches['quantity']);
        $ingredient = ['ingredient_name' => $name, 'unit_key' => $unit];
        if ($range !== null) {
            $ingredient['quantity_min'] = $range['min'];
            $ingredient['quantity_max'] = $range['max'];
        } else {
            $quantity = $this->fractions->parse($matches['quantity']);
            if ($quantity === null) {
                return null;
            }
            $ingredient['quantity'] = $quantity;
        }
        $tail = trim((string) ($matches['tail'] ?? ''));
        if ($tail !== '') {
            $ingredient['notes'] = ltrim($tail, " ,;-:");
        }
        return $ingredient;
    }

    private function stepsFrom(array $lines): array
    {
        $text = trim(implode("\n", $lines));
        if ($text === '') {
            return [];
        }
        $pieces = preg_split('/(?:\n+)|(?<=[.!?])\s+(?=\p{Lu})/u', $text) ?: [];
        return collect($pieces)->map(fn (string $step): string => trim(preg_replace('/^\d+[.)]\s*/', '', $step) ?? $step))
            ->filter()->map(fn (string $instruction): array => ['instruction' => $instruction])->values()->all();
    }

    private function unitPattern(): string
    {
        $aliases = collect($this->units->aliases())->flatten()->sortByDesc(fn (string $alias): int => strlen($alias))->map(fn (string $alias): string => preg_quote($alias, '/'))->all();
        return '(?:'.implode('|', $aliases).')';
    }

    private function quantityPattern(): string
    {
        $part = '(?:\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?|\d+\/\d+|[½¼¾⅓⅔⅛⅜⅝⅞]|\d+[½¼¾⅓⅔⅛⅜⅝⅞])';
        return $part.'(?:\s*(?:–|—|\bto\b|\ba\b|-)\s*'.$part.')?';
    }
}
