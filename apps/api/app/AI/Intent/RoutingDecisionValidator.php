<?php

namespace App\AI\Intent;

use App\AI\Tools\ToolRegistry;

/** Ensures a router proposal is compatible with the detected message form. */
final class RoutingDecisionValidator
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private MessageShapeDetector $messageShapeDetector,
    ) {
    }

    /** @param array<string, mixed> $decision @param array<string, mixed> $context @return array{decision: array<string, mixed>, status: string, reason_code: ?string, shape: array<string, mixed>} */
    public function validate(array $decision, array $context): array
    {
        $message = (string) ($context['message'] ?? $context['user_message']->content_text ?? '');
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];
        $shape = [
            'message_shape' => (string) ($routing['message_shape'] ?? ''),
            'action_key_candidate' => $routing['action_key_candidate'] ?? null,
            'confidence' => (float) ($routing['shape_confidence'] ?? 0),
        ];
        if ($shape['message_shape'] === '') {
            $shape = $this->messageShapeDetector->detect($message);
        }

        $actionKey = $this->canonicalActionKey($decision);
        $candidate = is_string($shape['action_key_candidate'] ?? null) ? $shape['action_key_candidate'] : null;
        if ($candidate !== null && $shape['confidence'] >= 0.95 && $actionKey !== $candidate) {
            return [
                'decision' => $decision,
                'status' => 'rejected',
                'reason_code' => 'shape_action_incompatible',
                'shape' => $shape,
            ];
        }

        if ($actionKey === null) {
            return ['decision' => $decision, 'status' => 'accepted', 'reason_code' => null, 'shape' => $shape];
        }

        $tool = $this->toolRegistry->resolve($actionKey);
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];
        $input = is_array($slots['input'] ?? null) ? $slots['input'] : [];
        if (($tool['operation_type'] ?? null) === 'create' && ($tool['target_entity_required'] ?? false) === false) {
            unset($slots['entity_id'], $slots['entity_search']);
            foreach ((array) ($tool['target_reference_fields'] ?? []) as $field) {
                unset($input[$field]);
            }
        }

        if ($actionKey === 'recipes.create' && $shape['message_shape'] === 'recipe_document_create') {
            $input = ['raw_recipe_text' => $message];
        }

        $slots['action_key'] = $actionKey;
        $slots['input'] = $input;
        $decision['intent'] = 'tool_action';
        $decision['slots'] = $slots;
        $decision['routing'] = [
            ...$routing,
            'action_key' => $actionKey,
            'message_shape' => $shape['message_shape'],
            'action_key_candidate' => $shape['action_key_candidate'],
            'shape_confidence' => $shape['confidence'],
        ];

        return ['decision' => $decision, 'status' => 'accepted', 'reason_code' => null, 'shape' => $shape];
    }

    /** @param array<string, mixed> $decision */
    private function canonicalActionKey(array $decision): ?string
    {
        $candidates = [
            data_get($decision, 'routing.action_key'),
            data_get($decision, 'slots.action_key'),
            $decision['intent'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && ($key = $this->toolRegistry->actionKeyForIntent($candidate)) !== null) {
                return $key;
            }
        }

        return null;
    }

}
