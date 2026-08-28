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
        private ToolRegistry $toolRegistry,
        private ?MessageShapeDetector $messageShapeDetector = null,
        private ?RoutingDecisionValidator $routingDecisionValidator = null,
    ) {
        $this->messageShapeDetector ??= new MessageShapeDetector();
        $this->routingDecisionValidator ??= new RoutingDecisionValidator($this->toolRegistry, $this->messageShapeDetector);
    }

    public function route(array $context): array
    {
        $message = (string) ($context['message'] ?? '');
        $shape = $this->messageShapeDetector->detect($message);

        if (!config('ai.routing.local_enabled', true)) {
            Log::info('ai.router.local_bypassed', $this->logMetadata($context, null, null, 'local_bypassed'));

            return $this->routeWithGptFallback($context, $shape);
        }

        $deterministic = $this->deterministicProvider->generate($context);
        $deterministicAction = $this->toolRegistry->actionKeyForIntent(
            (string) ($deterministic['intent'] ?? '')
        );
        $deterministicAction ??= $this->registeredActionFromDecision($deterministic);

        if ($deterministicAction !== null) {
            $local = $this->withRouting($deterministic, [
                'action_key' => $deterministicAction,
                'confidence' => 0.98,
                'matched_pattern_id' => null,
                'source' => 'deterministic',
                'message_shape' => $shape['message_shape'],
                'action_key_candidate' => $shape['action_key_candidate'],
                'shape_confidence' => $shape['confidence'],
            ]);
            $validation = $this->routingDecisionValidator->validate($local, $context);
            $this->logAttempt($context, $local, $validation, 'local');
            if ($validation['status'] === 'accepted' && $this->isComplete($validation['decision'])) {
                return $validation['decision'];
            }
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
            $learned['routing'] = [
                ...(is_array($learned['routing'] ?? null) ? $learned['routing'] : []),
                'message_shape' => $shape['message_shape'],
                'action_key_candidate' => $shape['action_key_candidate'],
                'shape_confidence' => $shape['confidence'],
            ];

            $validation = $this->routingDecisionValidator->validate($learned, $context);
            $this->logAttempt($context, $learned, $validation, 'learned');
            if ($validation['status'] === 'accepted' && $this->isComplete($validation['decision'])) {
                return $validation['decision'];
            }
        }

        return $this->routeWithGptFallback($context, $shape);
    }

    private function routeWithGptFallback(array $context, array $shape): array
    {
        Log::info('ai.router.fallback_started', $this->logMetadata($context, null, null, 'fallback_started'));
        $fallback = $this->fallbackDecision($this->fallbackProvider->generate($context), $shape, 'ai');
        $validation = $this->routingDecisionValidator->validate($fallback, $context);
        $this->logAttempt($context, $fallback, $validation, 'fallback');
        if ($validation['status'] === 'accepted') {
            return $validation['decision'];
        }

        Log::info('ai.router.fallback_repair_started', $this->logMetadata($context, $fallback, $validation, 'fallback_repair_started'));
        $repair = $this->fallbackDecision($this->fallbackProvider->generate([
            ...$context,
            'routing_repair' => [
                'reason_code' => $validation['reason_code'],
                'rejected_action_key' => data_get($fallback, 'routing.action_key'),
            ],
        ]), $shape, 'ai_repair');
        $repaired = $this->routingDecisionValidator->validate($repair, $context);
        $this->logAttempt($context, $repair, $repaired, 'fallback_repair');

        return $repaired['decision'];
    }

    private function fallbackDecision(array $decision, array $shape, string $source): array
    {
        $actionKey = $this->toolRegistry->actionKeyForIntent((string) ($decision['intent'] ?? ''));
        $actionKey ??= $this->registeredActionFromDecision($decision);
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];

        return $this->withRouting($decision, [
            'action_key' => $actionKey,
            'confidence' => is_numeric($slots['confidence'] ?? null) ? (float) $slots['confidence'] : 0.80,
            'matched_pattern_id' => null,
            'source' => $source,
            'message_shape' => $shape['message_shape'],
            'action_key_candidate' => $shape['action_key_candidate'],
            'shape_confidence' => $shape['confidence'],
        ]);
    }

    private function isComplete(array $decision): bool
    {
        $actionKey = data_get($decision, 'routing.action_key');
        if (!is_string($actionKey) || $actionKey === '') {
            return !in_array($decision['intent'] ?? null, ['clarify_scope', 'unsupported_capability'], true);
        }
        if ((float) data_get($decision, 'routing.confidence', 0) < (float) config('ai.routing.local_confidence_threshold', 0.95)) {
            return false;
        }
        $input = is_array(data_get($decision, 'slots.input')) ? data_get($decision, 'slots.input') : [];
        if ($actionKey === 'recipes.create') {
            return is_string($input['raw_recipe_text'] ?? null) && trim($input['raw_recipe_text']) !== '';
        }
        if ($actionKey === 'tasks.create') {
            return filled($input['title'] ?? data_get($decision, 'slots.task_title'));
        }
        if ($actionKey === 'menus.create') {
            return filled($input['menu_draft.name'] ?? data_get($decision, 'slots.menu_draft.name'));
        }
        $schema = $this->toolRegistry->metadata($this->toolRegistry->resolve($actionKey))['input_schema'] ?? [];
        foreach ((array) ($schema['required'] ?? []) as $field) {
            if (data_get($input, $field) === null || data_get($input, $field) === '') {
                return false;
            }
        }

        return true;
    }

    private function logAttempt(array $context, array $decision, array $validation, string $stage): void
    {
        $event = $stage === 'local' || $stage === 'learned'
            ? ($validation['status'] === 'accepted' && $this->isComplete($validation['decision']) ? 'ai.router.local_accepted' : 'ai.router.local_rejected')
            : 'ai.router.fallback_resolved';
        Log::info($event, $this->logMetadata($context, $decision, $validation, $stage));
    }

    private function logMetadata(array $context, ?array $decision, ?array $validation, string $stage): array
    {
        return [
            'conversation_id' => $context['conversation_id'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'fallback_used' => str_starts_with($stage, 'fallback'),
            'final_action_key' => $validation['decision']['routing']['action_key'] ?? null,
            'local_action_key' => $decision['routing']['action_key'] ?? null,
            'local_confidence' => $decision['routing']['confidence'] ?? null,
            'local_status' => $validation['status'] ?? null,
            'reason_code' => $validation['reason_code'] ?? null,
            'workspace_id' => $context['workspace_id'] ?? null,
        ];
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

        $routing['ai_fallback_used'] = str_starts_with((string) ($routing['source'] ?? ''), 'ai');

        return [
            ...$decision,
            'routing' => $routing,
        ];
    }
}
