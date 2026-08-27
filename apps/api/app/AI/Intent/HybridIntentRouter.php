<?php

namespace App\AI\Intent;

use App\AI\Advisory\InteractionMode;
use App\AI\Contracts\AIProvider;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

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
        $learned = null;
        if ($workspaceId !== '') {
            try {
                $learned = $this->patternRegistry->match($workspaceId, (string) ($context['message'] ?? ''));
            } catch (\Throwable $exception) {
                // Learned patterns optimize routing only. A missing table or a
                // transient database error must not block deterministic/AI routing.
                Log::warning('ai.intent_pattern.match_failed', [
                    'exception_class' => class_basename($exception),
                    'workspace_id' => $workspaceId,
                ]);
            }
        }

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

        $interactionMode = (string) ($decision['interaction_mode'] ?? '');
        if (!in_array($interactionMode, [InteractionMode::READ, InteractionMode::ACTION, InteractionMode::ADVISORY, InteractionMode::GENERATIVE], true)) {
            $interactionMode = match ($decision['intent'] ?? null) {
                'advisory' => InteractionMode::ADVISORY,
                'generative' => InteractionMode::GENERATIVE,
                default => $actionKey !== null && (($routing['action_policy']['risk'] ?? null) !== 'read')
                    ? InteractionMode::ACTION
                    : InteractionMode::READ,
            };
        }
        $decision['interaction_mode'] = $interactionMode;
        $routing['interaction_mode'] = $interactionMode;

        $routing['ai_fallback_used'] = ($routing['source'] ?? null) === 'ai';

        return [
            ...$decision,
            'routing' => $routing,
        ];
    }
}
