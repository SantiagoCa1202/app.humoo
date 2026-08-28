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
            'parameters' => $actionKey === 'recipes.create'
                ? RecipeCreateDraftData::jsonSchema()
                : $this->genericParameters((array) ($definition['input_schema'] ?? [])),
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
