<?php

namespace App\AI\Capabilities;

use App\AI\Capabilities\Drafts\RecipeCreateDraftData;

/** Builds OpenAI strict custom-function definitions from canonical contracts. */
final class OpenAiFunctionSchemaFactory
{
    /** @var array<int, string> */
    private const BOOLEAN_FIELDS = [
        'active', 'active_only', 'available', 'enabled', 'include_assignments',
        'in_app', 'is_primary', 'optional', 'overdue', 'preserve_assignments',
        'preserve_completed_items', 'unassigned', 'unread_only',
    ];

    /** @var array<int, string> */
    private const INTEGER_FIELDS = [
        'break_minutes', 'capacity', 'default_guest_count', 'expected_revision',
        'duration_minutes', 'guest_count', 'limit', 'minimum_priority', 'position',
        'requested_guest_count', 'time_hour', 'time_minute', 'version',
    ];

    /** @var array<int, string> */
    private const NUMBER_FIELDS = [
        'actual_quantity', 'latitude', 'longitude', 'portions', 'quantity',
        'quantity_per_guest', 'quantity_suggestion', 'target_quantity', 'yield_quantity',
    ];

    /** @var array<int, string> */
    private const ARRAY_FIELDS = [
        'member_ids', 'records', 'rules', 'sections', 'task_ids',
    ];

    /** @var array<int, string> */
    private const OBJECT_FIELDS = ['menu_draft', 'recipe_draft'];

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    public function make(array $definition): array
    {
        $actionKey = (string) $definition['action_key'];

        $tool = [
            'type' => 'function',
            'name' => str_replace('.', '_', $actionKey),
            'description' => (string) $definition['description'],
            // Workflow steps carry the exact input object of another registered
            // capability. That object is still JSON from the provider, but its
            // schema is selected dynamically by the plan validator below.
            'strict' => ! in_array($actionKey, ['execution_plans.create', 'execution_plans.revise'], true),
            'parameters' => match ($actionKey) {
                'orchestration.respond' => $this->orchestrationResponseParameters(),
                'execution_plans.create' => $this->executionPlanCreateParameters(),
                'execution_plans.revise' => $this->executionPlanRevisionParameters(),
                'recipes.create' => RecipeCreateDraftData::jsonSchema(),
                'recipes.update' => $this->recipeUpdateParameters(),
                'recipes.edit' => $this->recipeMutationParameters(),
                'recipes.duplicate' => $this->recipeDuplicateParameters(),
                'recipes.delete' => $this->recipeDeleteParameters(),
                'menus.create' => $this->menuCreateParameters(),
                'menus.update' => $this->menuUpdateParameters(),
                'menus.duplicate' => $this->menuDuplicateParameters(),
                'menus.items.reorder' => $this->menuItemReorderParameters(),
                'menus.items.batch_update' => $this->menuItemBatchUpdateParameters(),
                default => $this->genericParameters((array) ($definition['input_schema'] ?? [])),
            },
        ];

        if ((bool) ($definition['defer_loading'] ?? false)) {
            $tool['defer_loading'] = true;
        }

        return $tool;
    }

    /** @return array<string, mixed> */
    private function orchestrationResponseParameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['status', 'message', 'blocks', 'continuation', 'suggestions', 'reason', 'missing_fields', 'remaining_operations'],
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['completed', 'clarification_required', 'waiting_confirmation', 'partial', 'nonrecoverable_error'],
                ],
                'message' => ['type' => 'string', 'minLength' => 1],
                'blocks' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'text'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['text']],
                            'text' => ['type' => 'string', 'maxLength' => 4000],
                        ],
                    ],
                ],
                'continuation' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['state', 'reason'],
                    'properties' => [
                        'state' => ['type' => 'string', 'enum' => ['none', 'user_input', 'confirmation']],
                        'reason' => ['type' => ['string', 'null'], 'maxLength' => 500],
                    ],
                ],
                'suggestions' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string', 'maxLength' => 180]],
                'reason' => ['type' => ['string', 'null']],
                'missing_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                'remaining_operations' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function executionPlanCreateParameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'objective', 'block_size', 'steps', 'completion_steps'],
            'properties' => [
                'title' => ['type' => ['string', 'null'], 'maxLength' => 180],
                'objective' => ['type' => ['string', 'null'], 'maxLength' => 180],
                'block_size' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 10],
                'steps' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 50,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'step_key', 'action_key', 'label', 'input', 'after', 'is_required',
                        ],
                        'properties' => [
                            'step_key' => ['type' => 'string', 'maxLength' => 100],
                            'action_key' => ['type' => 'string', 'maxLength' => 120],
                            'label' => ['type' => ['string', 'null'], 'maxLength' => 180],
                            // Inputs remain structured objects. They are never
                            // reconstructed from prose or interpreted by a
                            // local parser.
                            'input' => ['type' => 'object', 'additionalProperties' => true],
                            'after' => [
                                'type' => 'array',
                                'description' => 'Optional pure sequencing dependencies. Data dependencies are derived automatically from {$from:"step_key.path"} references inside input.',
                                'items' => ['type' => 'string'],
                            ],
                            'is_required' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'completion_steps' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['step_key', 'action_key', 'label', 'input', 'after'],
                        'properties' => [
                            'step_key' => ['type' => 'string', 'maxLength' => 100],
                            'action_key' => ['type' => 'string', 'maxLength' => 120],
                            'label' => ['type' => ['string', 'null'], 'maxLength' => 180],
                            'input' => ['type' => 'object', 'additionalProperties' => true],
                            'after' => ['type' => 'array', 'description' => 'Optional pure sequencing dependencies. Data dependencies are derived from {$from:"step_key.path"} references inside input.', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function executionPlanRevisionParameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['execution_plan_id', 'items'],
            'properties' => [
                'execution_plan_id' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 50,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['item_id', 'input'],
                        'properties' => [
                            'item_id' => ['type' => 'string'],
                            // The item action determines this exact object.
                            // It is preserved and repaired structurally, never
                            // reconstructed with a local parser.
                            'input' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ],
            ],
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

    /** @return array<string, mixed> */
    private function recipeMutationParameters(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];
        $ingredientChange = [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'target_ingredient_id', 'ingredient_name', 'quantity', 'unit_key', 'preparation', 'notes', 'optional'],
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['add', 'remove', 'replace', 'set_quantity']],
                'target_ingredient_id' => $nullableString,
                'ingredient_name' => $nullableString, 'quantity' => $nullableNumber, 'unit_key' => $nullableString,
                'preparation' => $nullableString, 'notes' => $nullableString, 'optional' => ['type' => ['boolean', 'null']],
            ],
        ];
        $stepChange = [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'target_step_id', 'instruction', 'title', 'duration_minutes', 'notes'],
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['append', 'add_after', 'move_to_last', 'replace']],
                'target_step_id' => $nullableString,
                'instruction' => $nullableString, 'title' => $nullableString,
                'duration_minutes' => ['type' => ['integer', 'null']], 'notes' => $nullableString,
            ],
        ];

        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['recipe_id', 'recipe_search', 'mutation'],
            'properties' => [
                'recipe_id' => $nullableString, 'recipe_search' => $nullableString,
                'mutation' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['ingredient_changes', 'step_changes', 'yield', 'convert_units'],
                    'properties' => [
                        'ingredient_changes' => ['type' => ['array', 'null'], 'items' => $ingredientChange],
                        'step_changes' => ['type' => ['array', 'null'], 'items' => $stepChange],
                        'yield' => [
                            'type' => ['object', 'null'], 'additionalProperties' => false,
                            'required' => ['quantity', 'unit_key'],
                            'properties' => ['quantity' => $nullableNumber, 'unit_key' => $nullableString],
                        ],
                        'convert_units' => [
                            'type' => ['object', 'null'], 'additionalProperties' => false,
                            'required' => ['volume_unit_key', 'weight_unit_key'],
                            'properties' => ['volume_unit_key' => $nullableString, 'weight_unit_key' => $nullableString],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function recipeDuplicateParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['recipe_id', 'recipe_search', 'name'],
            'properties' => [
                'recipe_id' => ['type' => ['string', 'null']],
                'recipe_search' => ['type' => ['string', 'null']],
                'name' => ['type' => 'string'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function recipeDeleteParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['recipe_id', 'recipe_search'],
            'properties' => [
                'recipe_id' => ['type' => ['string', 'null']],
                'recipe_search' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuCreateParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['menu_draft'],
            'properties' => [
                'menu_draft' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['name', 'description', 'type', 'default_guest_count', 'sections', 'requested_guest_count'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'description' => ['type' => ['string', 'null']],
                        'type' => ['type' => ['string', 'null']],
                        'default_guest_count' => ['type' => ['integer', 'null']],
                        'sections' => ['type' => 'array', 'minItems' => 1, 'items' => $this->menuSectionSchema(false)],
                        'requested_guest_count' => ['type' => ['integer', 'null']],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuUpdateParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['menu_id', 'menu_search', 'name', 'description', 'type', 'status', 'default_guest_count', 'sections', 'event_id'],
            'properties' => [
                'menu_id' => ['type' => ['string', 'null']],
                'menu_search' => ['type' => ['string', 'null']],
                'name' => ['type' => ['string', 'null']],
                'description' => ['type' => ['string', 'null']],
                'type' => ['type' => ['string', 'null']],
                'status' => ['type' => ['string', 'null']],
                'default_guest_count' => ['type' => ['integer', 'null']],
                'sections' => ['type' => ['array', 'null'], 'items' => $this->menuSectionSchema(true)],
                'event_id' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuDuplicateParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['menu_id', 'menu_search', 'name', 'description', 'type', 'default_guest_count', 'sections'],
            'properties' => [
                'menu_id' => ['type' => ['string', 'null']],
                'menu_search' => ['type' => ['string', 'null']],
                'name' => ['type' => 'string'],
                'description' => ['type' => ['string', 'null']],
                'type' => ['type' => ['string', 'null']],
                'default_guest_count' => ['type' => ['integer', 'null']],
                'sections' => ['type' => ['array', 'null'], 'items' => $this->menuSectionSchema(true)],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuItemReorderParameters(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['menu_id', 'menu_search', 'item_id', 'before_item_id'],
            'properties' => [
                'menu_id' => ['type' => ['string', 'null']],
                'menu_search' => ['type' => ['string', 'null']],
                'item_id' => ['type' => ['string', 'null']],
                'before_item_id' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuItemBatchUpdateParameters(): array
    {
        $update = [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['item_id', 'name', 'description', 'notes', 'quantity_per_guest', 'serving_unit', 'recipe_id', 'recipe_version_id', 'active', 'optional'],
            'properties' => [
                'item_id' => ['type' => 'string'],
                'name' => ['type' => ['string', 'null']],
                'description' => ['type' => ['string', 'null']],
                'notes' => ['type' => ['string', 'null']],
                'quantity_per_guest' => ['type' => ['number', 'null']],
                'serving_unit' => ['type' => ['string', 'null']],
                'recipe_id' => ['type' => ['string', 'null']],
                'recipe_version_id' => ['type' => ['string', 'null']],
                'active' => ['type' => ['boolean', 'null']],
                'optional' => ['type' => ['boolean', 'null']],
            ],
        ];

        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['menu_id', 'menu_search', 'updates'],
            'properties' => [
                'menu_id' => ['type' => ['string', 'null']],
                'menu_search' => ['type' => ['string', 'null']],
                'updates' => ['type' => 'array', 'minItems' => 2, 'items' => $update],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function menuSectionSchema(bool $withIds): array
    {
        $itemProperties = [
            'name' => ['type' => 'string'],
            'description' => ['type' => ['string', 'null']],
            'notes' => ['type' => ['string', 'null']],
            'type' => ['type' => ['string', 'null']],
            'position' => ['type' => ['integer', 'null']],
            'recipe_id' => ['type' => ['string', 'null']],
            'recipe_version_id' => ['type' => ['string', 'null']],
            'quantity_per_guest' => ['type' => ['number', 'null']],
            'serving_unit' => ['type' => ['string', 'null']],
            'optional' => ['type' => ['boolean', 'null']],
            'active' => ['type' => ['boolean', 'null']],
        ];
        if ($withIds) {
            $itemProperties = ['id' => ['type' => ['string', 'null']], ...$itemProperties];
        }

        $sectionProperties = [
            'name' => ['type' => 'string'],
            'description' => ['type' => ['string', 'null']],
            'type' => ['type' => ['string', 'null']],
            'position' => ['type' => ['integer', 'null']],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => array_keys($itemProperties), 'properties' => $itemProperties,
                ],
            ],
        ];
        if ($withIds) {
            $sectionProperties = ['id' => ['type' => ['string', 'null']], ...$sectionProperties];
        }

        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => array_keys($sectionProperties), 'properties' => $sectionProperties,
        ];
    }

    /** @param array<string, mixed> $inputSchema @return array<string, mixed> */
    private function genericParameters(array $inputSchema): array
    {
        // Preserve canonical JSON Schema contracts (such as tasks.create).
        // Strict OpenAI functions require every property to be required, so
        // fields that are optional in the domain contract are represented as
        // nullable values instead of being dropped.
        if (is_array($inputSchema['properties'] ?? null)) {
            $properties = $inputSchema['properties'];
            $declaredRequired = array_values(array_filter(
                (array) ($inputSchema['required'] ?? []),
                static fn (mixed $field): bool => is_string($field) && array_key_exists($field, $properties)
            ));

            foreach ($properties as $field => $property) {
                if (! is_string($field) || ! is_array($property) || in_array($field, $declaredRequired, true)) {
                    continue;
                }

                $properties[$field] = $this->makeNullable($property);
            }

            return [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => array_keys($properties),
                'properties' => $properties === [] ? new \stdClass : $properties,
            ];
        }

        $fields = array_values(array_filter((array) ($inputSchema['fields'] ?? []), static fn (mixed $field): bool => is_string($field) && ! str_contains($field, '.')));
        $properties = [];
        foreach ($fields as $field) {
            $properties[$field] = $this->fieldSchema($field);
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $fields,
            'properties' => $properties === [] ? new \stdClass : $properties,
        ];
    }

    /** @param array<string, mixed> $property @return array<string, mixed> */
    private function makeNullable(array $property): array
    {
        $type = $property['type'] ?? ['string'];
        if (is_string($type)) {
            $property['type'] = [$type, 'null'];
        } elseif (is_array($type) && ! in_array('null', $type, true)) {
            $property['type'][] = 'null';
        }
        if (is_array($property['enum'] ?? null) && ! in_array(null, $property['enum'], true)) {
            $property['enum'][] = null;
        }

        return $property;
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
                'properties' => new \stdClass,
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
            // Generic registry fields are optional at the domain boundary.
            // Strict function schemas still require the property to exist,
            // so nullable values let the model omit unrelated fields by
            // sending null; the executor removes those nulls before update
            // validation.
            default => ['type' => ['string', 'null']],
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
