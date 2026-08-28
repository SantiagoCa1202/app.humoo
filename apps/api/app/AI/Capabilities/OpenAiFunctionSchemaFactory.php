<?php

namespace App\AI\Capabilities;

use App\AI\Capabilities\Drafts\RecipeCreateDraftData;

/** Builds OpenAI strict custom-function definitions from canonical contracts. */
final class OpenAiFunctionSchemaFactory
{
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
            $properties[$field] = ['type' => ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null']];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $fields,
            'properties' => $properties,
        ];
    }
}
