<?php

namespace App\AI\Clarifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PendingClarificationResolver
{
    public function resolve(object $conversation, string $workspaceId, string $clarificationId, array $input): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        $index = collect($pending)->search(fn (mixed $item): bool => is_array($item) && ($item['clarification_id'] ?? null) === $clarificationId);
        $clarification = $index === false ? null : $pending[$index];
        if (!is_array($clarification) || ($clarification['workflow'] ?? null) !== 'recipes.create' || ($clarification['status'] ?? null) !== 'pending') {
            throw ValidationException::withMessages(['clarification_id' => ['This clarification is unavailable.']]);
        }

        $selected = (string) ($input['selected_option_id'] ?? '');
        $option = collect($clarification['options'] ?? [])->firstWhere('id', $selected);
        $usedCustom = $selected === 'custom';
        $value = $usedCustom ? ($input['custom_value'] ?? null) : ($option['value'] ?? null);
        if (!is_numeric($value) || (float) $value <= 0 || (!$usedCustom && !is_array($option)) || ($usedCustom && !($clarification['allow_custom'] ?? false))) {
            throw ValidationException::withMessages(['value' => ['The selected clarification value is invalid.']]);
        }

        $draft = is_array($metadata['active_recipe_draft'] ?? null) ? $metadata['active_recipe_draft'] : null;
        $ingredientIndex = $clarification['ingredient_index'] ?? null;
        if (!is_array($draft) || !is_int($ingredientIndex) || !isset($draft['ingredients'][$ingredientIndex])) {
            throw ValidationException::withMessages(['clarification_id' => ['The associated draft is no longer available.']]);
        }
        $draft['ingredients'][$ingredientIndex]['quantity'] = (float) $value;
        unset($draft['ingredients'][$ingredientIndex]['quantity_min'], $draft['ingredients'][$ingredientIndex]['quantity_max']);
        $pending[$index]['status'] = 'resolved';
        $pending[$index]['resolved_value'] = (float) $value;
        $metadata['active_recipe_draft'] = $draft;
        $metadata['active_recipe_ingestion_issues'] = [];
        $metadata['pending_clarifications'] = $pending;
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.clarification.resolved', ['workflow' => 'recipes.create', 'clarification_type' => $clarification['type'] ?? null, 'expected_type' => 'number', 'selection_mode' => 'single', 'used_custom' => $usedCustom, 'router_bypassed' => true, 'ai_bypassed' => true, 'workspace_id' => $workspaceId]);

        return ['draft' => $draft, 'clarification' => $pending[$index]];
    }

    public function cancel(object $conversation, string $workspaceId, string $clarificationId): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        foreach ($pending as $index => $clarification) {
            if (is_array($clarification) && ($clarification['clarification_id'] ?? null) === $clarificationId && ($clarification['status'] ?? null) === 'pending') {
                $pending[$index]['status'] = 'cancelled';
                $metadata['pending_clarifications'] = $pending;
                $conversation->forceFill(['metadata' => $metadata])->save();
                Log::info('ai.clarification.cancelled', ['workflow' => $clarification['workflow'] ?? null, 'workspace_id' => $workspaceId]);
                return;
            }
        }
        throw ValidationException::withMessages(['clarification_id' => ['This clarification is unavailable.']]);
    }
}
