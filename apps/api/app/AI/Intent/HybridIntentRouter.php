<?php

namespace App\AI\Intent;

use App\AI\Contracts\AIProvider;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolRegistry;

class HybridIntentRouter
{
    public function __construct(
        private RuleBasedAIProvider $deterministicProvider,
        private AIProvider $fallbackProvider,
        private IntentPatternRegistry $patternRegistry,
        private ToolRegistry $toolRegistry
    ) {
    }

    public function route(array $context): array
    {
        $deterministic = $this->deterministicProvider->generate($context);
        $deterministicAction = $this->toolRegistry->actionKeyForIntent(
            (string) ($deterministic['intent'] ?? '')
        );
        $deterministicAction ??= $this->registeredActionFromDecision($deterministic);

        if ($deterministicAction !== null) {
            return $this->withRouting($deterministic, [
                'action_key' => $deterministicAction,
                'confidence' => 0.98,
                'matched_pattern_id' => null,
                'source' => 'deterministic',
            ]);
        }

        $workspaceId = (string) ($context['workspace_id'] ?? '');
        $learned = $workspaceId !== ''
            ? $this->patternRegistry->match($workspaceId, (string) ($context['message'] ?? ''))
            : null;

        if ($learned !== null) {
            return $learned;
        }

        $decision = $this->fallbackProvider->generate($context);
        $actionKey = $this->toolRegistry->actionKeyForIntent(
            (string) ($decision['intent'] ?? '')
        );
        $actionKey ??= $this->registeredActionFromDecision($decision);
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];

        return $this->withRouting($decision, [
            'action_key' => $actionKey,
            'confidence' => is_numeric($slots['confidence'] ?? null)
                ? (float) $slots['confidence']
                : 0.80,
            'matched_pattern_id' => null,
            'source' => 'ai',
        ]);
    }

    private function registeredActionFromDecision(array $decision): ?string
    {
        if (($decision['intent'] ?? null) !== 'tool_action') {
            return null;
        }

        $actionKey = (string) (($decision['slots']['action_key'] ?? null));
        if ($actionKey === '') {
            return null;
        }

        return $this->toolRegistry->actionKeyForIntent($actionKey);
    }

    private function withRouting(array $decision, array $routing): array
    {
        $actionKey = $routing['action_key'] ?? null;

        if (is_string($actionKey) && $actionKey !== '') {
            $routing['action_policy'] = $this->toolRegistry->resolve($actionKey)['policy'];
        }

        $routing['ai_fallback_used'] = ($routing['source'] ?? null) === 'ai';

        return [
            ...$decision,
            'routing' => $routing,
        ];
    }
}
