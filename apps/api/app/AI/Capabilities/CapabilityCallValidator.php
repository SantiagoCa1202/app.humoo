<?php

namespace App\AI\Capabilities;

use App\AI\Recipes\UnitRegistry;
use Illuminate\Validation\ValidationException;

/** Structural and lifecycle-contract validation; it never reinterprets language. */
final class CapabilityCallValidator
{
    public function __construct(private CapabilityRegistry $registry)
    {
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function validate(CapabilityCall $call, array $context): array
    {
        if (empty($context['workspace']) || empty($context['user'])) {
            throw ValidationException::withMessages(['context' => ['Workspace and actor are required.']]);
        }
        $definition = $this->registry->definition($call->actionKey);
        if (($definition['enabled'] ?? false) !== true || empty($definition['permission'])) {
            throw ValidationException::withMessages(['action_key' => ['The selected capability is not available.']]);
        }
        if (!in_array($call->source, ['ai', 'local', 'continuation'], true)) {
            throw ValidationException::withMessages(['source' => ['The capability call source is invalid.']]);
        }
        if ($call->actionKey === 'recipes.create') {
            $this->validateRecipeCreate($call->arguments);
        }

        return $definition;
    }

    /** @param array<string, mixed> $draft */
    private function validateRecipeCreate(array $draft): void
    {
        $allowed = ['name', 'description', 'yield', 'ingredients', 'steps', 'source'];
        if (array_diff(array_keys($draft), $allowed) !== []) {
            throw ValidationException::withMessages(['arguments' => ['The recipe draft contains unsupported fields.']]);
        }
        foreach ((array) ($draft['ingredients'] ?? []) as $ingredient) {
            if (!is_array($ingredient) || !is_bool($ingredient['optional'] ?? null)) {
                throw ValidationException::withMessages(['ingredients' => ['Each ingredient must match the recipe draft contract.']]);
            }
            $unit = $ingredient['unit_key'] ?? null;
            if ($unit !== null && !in_array($unit, (new UnitRegistry())->keys(), true)) {
                throw ValidationException::withMessages(['ingredients' => ['An ingredient uses an unsupported unit.']]);
            }
        }
    }
}
