<?php

namespace App\AI\Capabilities\Drafts;

use App\AI\Recipes\UnitNormalizer;

/**
 * Contract accepted from the recipes.create function call before domain
 * validation. Nullable fields intentionally represent missing information,
 * never a request for Laravel to infer it from natural language again.
 */
final class RecipeCreateDraftData
{
    /** @param array<string, mixed> $input */
    public static function from(array $input): self
    {
        $data = [
            'name' => self::stringOrNull($input['name'] ?? null),
            'description' => self::stringOrNull($input['description'] ?? null),
            'yield' => self::yieldFrom($input['yield'] ?? null),
            'ingredients' => collect($input['ingredients'] ?? [])
                ->filter(fn (mixed $ingredient): bool => is_array($ingredient))
                ->map(fn (array $ingredient): array => self::ingredientFrom($ingredient))
                ->values()
                ->all(),
            'steps' => collect($input['steps'] ?? [])
                ->filter(fn (mixed $step): bool => is_array($step))
                ->map(fn (array $step): array => [
                    'title' => self::stringOrNull($step['title'] ?? null),
                    'instruction' => trim((string) ($step['instruction'] ?? '')),
                    'duration_minutes' => self::integerOrNull($step['duration_minutes'] ?? null),
                ])
                ->values()
                ->all(),
            // This is a transport boundary marker, not an instruction to run
            // the conversational ingestion/parser again.
            'source' => 'structured_ai',
        ];

        return new self($data);
    }

    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public static function jsonSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'description', 'yield', 'ingredients', 'steps', 'source'],
            'properties' => [
                'name' => $nullableString,
                'description' => $nullableString,
                'yield' => [
                    'type' => ['object', 'null'],
                    'additionalProperties' => false,
                    'required' => ['quantity', 'quantity_min', 'quantity_max', 'unit_key', 'label'],
                    'properties' => [
                        'quantity' => $nullableNumber,
                        'quantity_min' => $nullableNumber,
                        'quantity_max' => $nullableNumber,
                        'unit_key' => ['type' => ['string', 'null'], 'enum' => [...array_keys((new UnitNormalizer())->aliases()), null]],
                        'label' => $nullableString,
                    ],
                ],
                'ingredients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['ingredient_name', 'quantity', 'quantity_min', 'quantity_max', 'quantity_text', 'unit_key', 'preparation', 'notes', 'optional', 'group', 'alternatives'],
                        'properties' => [
                            'ingredient_name' => ['type' => 'string'],
                            'quantity' => $nullableNumber,
                            'quantity_min' => $nullableNumber,
                            'quantity_max' => $nullableNumber,
                            'quantity_text' => $nullableString,
                            'unit_key' => ['type' => ['string', 'null'], 'enum' => [...array_keys((new UnitNormalizer())->aliases()), null]],
                            'preparation' => $nullableString,
                            'notes' => $nullableString,
                            'optional' => ['type' => 'boolean'],
                            'group' => $nullableString,
                            'alternatives' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['title', 'instruction', 'duration_minutes'],
                        'properties' => [
                            'title' => $nullableString,
                            'instruction' => ['type' => 'string'],
                            'duration_minutes' => ['type' => ['integer', 'null']],
                        ],
                    ],
                ],
                'source' => $nullableString,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private static function yieldFrom(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return [
            'quantity' => self::numberOrNull($value['quantity'] ?? null),
            'quantity_min' => self::numberOrNull($value['quantity_min'] ?? null),
            'quantity_max' => self::numberOrNull($value['quantity_max'] ?? null),
            'unit_key' => (new UnitNormalizer())->normalize($value['unit_key'] ?? null),
            'label' => self::stringOrNull($value['label'] ?? null),
        ];
    }

    /** @param array<string, mixed> $ingredient @return array<string, mixed> */
    private static function ingredientFrom(array $ingredient): array
    {
        return [
            'ingredient_name' => trim((string) ($ingredient['ingredient_name'] ?? '')),
            'quantity' => self::numberOrNull($ingredient['quantity'] ?? null),
            'quantity_min' => self::numberOrNull($ingredient['quantity_min'] ?? null),
            'quantity_max' => self::numberOrNull($ingredient['quantity_max'] ?? null),
            'quantity_text' => self::stringOrNull($ingredient['quantity_text'] ?? null),
            'unit_key' => (new UnitNormalizer())->normalize($ingredient['unit_key'] ?? null),
            'preparation' => self::stringOrNull($ingredient['preparation'] ?? null),
            'notes' => self::stringOrNull($ingredient['notes'] ?? null),
            'optional' => (bool) ($ingredient['optional'] ?? false),
            'group' => self::stringOrNull($ingredient['group'] ?? null),
            'alternatives' => collect($ingredient['alternatives'] ?? [])
                ->filter(fn (mixed $alternative): bool => is_string($alternative) && trim($alternative) !== '')
                ->map(fn (string $alternative): string => trim($alternative))
                ->values()
                ->all(),
        ];
    }

    private static function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
