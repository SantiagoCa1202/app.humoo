<?php

namespace App\AI\Capabilities;

use App\AI\Capabilities\Drafts\RecipeCreateDraftData;

/** Builds OpenAI strict custom-function definitions from canonical contracts. */
final class OpenAiFunctionSchemaFactory
{
    /** @var array<int, string> */
    private const BOOLEAN_FIELDS = [
        'active', 'active_only', 'available', 'enabled', 'include_assignments',
        'in_app', 'is_primary', 'optional', 'preserve_assignments',
        'preserve_completed_items', 'unread_only',
    ];

    /** @var array<int, string> */
    private const INTEGER_FIELDS = [
        'break_minutes', 'capacity', 'default_guest_count', 'expected_revision',
        'guest_count', 'limit', 'minimum_priority', 'position', 'requested_guest_count',
        'version',
    ];

    /** @var array<int, string> */
    private const NUMBER_FIELDS = [
        'actual_quantity', 'latitude', 'longitude', 'portions', 'quantity',
        'quantity_per_guest', 'quantity_suggestion', 'target_quantity', 'yield_quantity',
    ];

    /** @var array<int, string> */
    private const ARRAY_FIELDS = [
        'member_ids', 'records', 'rules', 'sections',
    ];

    /** @var array<int, string> */
    private const OBJECT_FIELDS = ['menu_draft', 'recipe_draft'];

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    public function make(array $definition): array
    {
        $actionKey = (string) $definition['action_key'];

        return [
            'type' => 'function',
            'name' => str_replace('.', '_', $actionKey),
            'description' => (string) $definition['description'],
            'strict' => true,
            'parameters' => match ($actionKey) {
                'recipes.create' => RecipeCreateDraftData::jsonSchema(),
                'recipes.update' => $this->recipeUpdateParameters(),
                default => $this->genericParameters((array) ($definition['input_schema'] ?? [])),
            },
        ];
    }

    /** @return array<string, mixed> */
    private function recipeUpdateParameters(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];
        $ingredient = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'ingredient_name', 'quantity', 'unit_id', 'notes', 'optional',
                'preparation', 'component_recipe_id', 'component_recipe_version_id',
            ],
            'properties' => [
                'ingredient_name' => ['type' => 'string'],
                'quantity' => ['type' => 'number'],
                'unit_id' => ['type' => 'string'],
                'notes' => $nullableString,
                'optional' => ['type' => ['boolean', 'null']],
                'preparation' => $nullableString,
                'component_recipe_id' => $nullableString,
                'component_recipe_version_id' => $nullableString,
            ],
        ];
        $step = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['instruction', 'title', 'duration_minutes', 'notes'],
            'properties' => [
                'instruction' => ['type' => 'string'],
                'title' => $nullableString,
                'duration_minutes' => ['type' => ['integer', 'null']],
                'notes' => $nullableString,
            ],
        ];
        $yield = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['quantity', 'unit_id', 'label', 'is_default'],
            'properties' => [
                'quantity' => ['type' => 'number'],
                'unit_id' => ['type' => 'string'],
                'label' => $nullableString,
                'is_default' => ['type' => ['boolean', 'null']],
            ],
        ];
        $version = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'description', 'category', 'status', 'ingredients', 'steps', 'yields'],
            'properties' => [
                'name' => ['type' => 'string'],
                'description' => $nullableString,
                'category' => $nullableString,
                'status' => $nullableString,
                'ingredients' => ['type' => 'array', 'items' => $ingredient],
                'steps' => ['type' => 'array', 'items' => $step],
                'yields' => ['type' => 'array', 'items' => $yield],
            ],
        ];
        $recipeDraft = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'description', 'category', 'type', 'status', 'recipe_code', 'tags', 'version'],
            'properties' => [
                'name' => ['type' => 'string'],
                'description' => $nullableString,
                'category' => $nullableString,
                'type' => $nullableString,
                'status' => $nullableString,
                'recipe_code' => $nullableString,
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'version' => $version,
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['recipe_id', 'recipe_draft', 'current_version_id', 'expected_revision'],
            'properties' => [
                'recipe_id' => ['type' => 'string'],
                'recipe_draft' => $recipeDraft,
                'current_version_id' => ['type' => 'string'],
                'expected_revision' => ['type' => 'integer'],
            ],
        ];
    }

    /** @param array<string, mixed> $inputSchema @return array<string, mixed> */
    private function genericParameters(array $inputSchema): array
    {
        $fields = array_values(array_filter((array) ($inputSchema['fields'] ?? []), static fn (mixed $field): bool => is_string($field) && !str_contains($field, '.')));
        $properties = [];
        foreach ($fields as $field) {
            $properties[$field] = $this->fieldSchema($field);
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $fields,
            'properties' => $properties === [] ? new \stdClass() : $properties,
        ];
    }

    /** @return array<string, mixed> */
    private function fieldSchema(string $field): array
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return ['type' => ['boolean', 'null']];
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return ['type' => ['integer', 'null']];
        }

        if (in_array($field, self::NUMBER_FIELDS, true)) {
            return ['type' => ['number', 'null']];
        }

        if (in_array($field, self::ARRAY_FIELDS, true)) {
            return [
                'type' => ['array', 'null'],
                'items' => $this->arrayItemsSchema($field),
            ];
        }

        if (in_array($field, self::OBJECT_FIELDS, true)) {
            return $field === 'recipe_draft'
                ? [...RecipeCreateDraftData::jsonSchema(), 'type' => ['object', 'null']]
                : $this->menuDraftSchema();
        }

        if ($field === 'metadata') {
            return [
                'type' => ['object', 'null'],
                'additionalProperties' => false,
                'required' => [],
                'properties' => new \stdClass(),
            ];
        }

        return ['type' => ['string', 'null']];
    }

    /** @return array<string, mixed> */
    private function arrayItemsSchema(string $field): array
    {
        return match ($field) {
            'member_ids' => ['type' => 'string'],
            'records' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id', 'starts_at', 'ends_at', 'available'],
                'properties' => [
                    'id' => ['type' => ['string', 'null']],
                    'starts_at' => ['type' => ['string', 'null']],
                    'ends_at' => ['type' => ['string', 'null']],
                    'available' => ['type' => ['boolean', 'null']],
                ],
            ],
            'rules' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id', 'day_of_week', 'starts_at', 'ends_at', 'available', 'active'],
                'properties' => [
                    'id' => ['type' => ['string', 'null']],
                    'day_of_week' => ['type' => ['integer', 'null']],
                    'starts_at' => ['type' => ['string', 'null']],
                    'ends_at' => ['type' => ['string', 'null']],
                    'available' => ['type' => ['boolean', 'null']],
                    'active' => ['type' => ['boolean', 'null']],
                ],
            ],
            'sections' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['name', 'type', 'items'],
                'properties' => [
                    'name' => ['type' => ['string', 'null']],
                    'type' => ['type' => ['string', 'null']],
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['name', 'description', 'notes', 'recipe_reference', 'quantity_per_guest', 'serving_unit'],
                            'properties' => [
                                'name' => ['type' => ['string', 'null']],
                                'description' => ['type' => ['string', 'null']],
                                'notes' => ['type' => ['string', 'null']],
                                'recipe_reference' => ['type' => ['string', 'null']],
                                'quantity_per_guest' => ['type' => ['number', 'null']],
                                'serving_unit' => ['type' => ['string', 'null']],
                            ],
                        ],
                    ],
                ],
            ],
            default => ['type' => 'string'],
        };
    }

    /** @return array<string, mixed> */
    private function menuDraftSchema(): array
    {
        return [
            'type' => ['object', 'null'],
            'additionalProperties' => false,
            'required' => ['name', 'description', 'type', 'default_guest_count', 'event_reference', 'sections'],
            'properties' => [
                'name' => ['type' => ['string', 'null']],
                'description' => ['type' => ['string', 'null']],
                'type' => ['type' => ['string', 'null']],
                'default_guest_count' => ['type' => ['integer', 'null']],
                'event_reference' => ['type' => ['string', 'null']],
                'sections' => [
                    'type' => ['array', 'null'],
                    'items' => $this->arrayItemsSchema('sections'),
                ],
            ],
        ];
    }
}
