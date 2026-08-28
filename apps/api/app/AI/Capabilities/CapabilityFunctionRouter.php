<?php

namespace App\AI\Capabilities;

use App\AI\Providers\OpenAIProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/** Two-stage, capability-scoped Function Calling router. */
final class CapabilityFunctionRouter
{
    public function __construct(
        private OpenAIProvider $provider,
        private CapabilityRegistry $registry,
        private CapabilityCallValidator $validator,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function route(array $context): ?CapabilityCall
    {
        $correlationId = (string) ($context['correlation_id'] ?? '');
        Log::info('ai.capability.routing_started', [
            'correlation_id' => $correlationId,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);

        $catalog = array_map(static fn (array $definition): array => [
            'action_key' => $definition['action_key'],
            'description' => $definition['description'],
            'operation_type' => $definition['operation_type'],
            'entity_type' => $definition['entity_type'],
        ], $this->registry->definitions());
        $keys = array_column($catalog, 'action_key');
        $routing = $this->provider->callFunction([
            ...$context,
            'function_instructions' => 'Select the one capability that best matches the user request from this compact catalog: '.json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], [[
            'type' => 'function', 'name' => 'select_capability', 'description' => 'Select the best matching Humoo capability.', 'strict' => true,
            'parameters' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['action_key', 'confidence'], 'properties' => [
                'action_key' => ['type' => 'string', 'enum' => $keys],
                'confidence' => ['type' => 'number'],
            ]],
        ]]);
        $selectedAction = ($routing['function_name'] ?? null) === 'select_capability'
            ? ($routing['arguments']['action_key'] ?? null)
            : null;
        Log::info('ai.capability.routing_resolved', [
            'action_key' => $selectedAction,
            'confidence' => $routing['arguments']['confidence'] ?? null,
            'correlation_id' => $correlationId,
            'input_tokens' => $routing['usage']['input_tokens'] ?? null,
            'output_tokens' => $routing['usage']['output_tokens'] ?? null,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);
        if ($selectedAction !== 'recipes.create') {
            return null;
        }

        Log::info('ai.function.extraction_started', [
            'action_key' => 'recipes.create',
            'correlation_id' => $correlationId,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);
        $extraction = $this->provider->callFunction([
            'locale' => $context['locale'] ?? null,
            'message' => $context['message'] ?? '',
            'message_id' => $context['message_id'] ?? null,
            'function_instructions' => 'Extract only facts explicitly present in the supplied recipe. Return every stated ingredient and preparation step. Use canonical unit_key values. A counted item without a unit uses each with its numeric quantity. For a textual quantity such as "a pinch", use quantity 1, unit_key each, and preserve the text in quantity_text. Preserve alternatives, groups, optional markers, preparation, notes, and quantity ranges. An explicit range such as 2–3 portions uses quantity_min, quantity_max, and unit_key portion. Leave only genuinely absent facts null. Do not add ingredients, quantities, yield, or steps. Never return raw text.',
            'recent_messages' => [[
                'id' => $context['message_id'] ?? 'current',
                'content_text' => $context['message'] ?? '',
                'sender_type' => 'user',
            ]],
        ], [$this->registry->functionDefinition('recipes.create')]);
        if (($extraction['function_name'] ?? null) !== 'recipes_create') {
            throw ValidationException::withMessages(['function_name' => ['The extraction function did not match recipes.create.']]);
        }
        Log::info('ai.function.extraction_resolved', [
            'action_key' => 'recipes.create',
            'ingredient_count' => count($extraction['arguments']['ingredients'] ?? []),
            'step_count' => count($extraction['arguments']['steps'] ?? []),
            'correlation_id' => $correlationId,
            'input_tokens' => $extraction['usage']['input_tokens'] ?? null,
            'output_tokens' => $extraction['usage']['output_tokens'] ?? null,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);
        $call = new CapabilityCall(
            'recipes.create',
            $extraction['arguments'],
            'ai',
            (float) ($routing['arguments']['confidence'] ?? 0),
            $extraction['call_id'] ?? null,
            $correlationId,
            [
                'routing' => $routing['usage'] ?? [],
                'extraction' => $extraction['usage'] ?? [],
            ]
        );
        Log::info('ai.capability_call.created', [
            'action_key' => $call->actionKey,
            'source' => $call->source,
            'correlation_id' => $correlationId,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);
        $validationContext = [
            'user' => $context['user'] ?? null,
            'workspace' => $context['workspace'] ?? null,
        ];
        if (app()->environment(['local', 'testing'])) {
            Log::info('ai.capability_call.validation_started', [
                'stage' => 'capability_call_validation',
                'validator' => 'CapabilityCallValidator',
                'action_key' => $call->actionKey,
                'correlation_id' => $correlationId,
                'workspace_id' => $context['workspace_id'] ?? null,
            ]);
        }
        try {
            $this->validator->validate($call, $validationContext);
        } catch (ValidationException $exception) {
            if (app()->environment(['local', 'testing'])) {
                $errors = $exception->errors();
                $fieldPath = (string) (array_key_first($errors) ?? 'unknown');
                Log::warning('ai.capability_call.validation_failed', [
                    'stage' => 'capability_call_validation',
                    'validator' => 'CapabilityCallValidator',
                    'action_key' => $call->actionKey,
                    'error_code' => 'structural_invalid',
                    'field_path' => $fieldPath,
                    'reason_code' => match ($fieldPath) {
                        'context' => 'missing_workspace_or_actor',
                        'action_key' => 'capability_unavailable',
                        'source' => 'invalid_source',
                        'arguments' => 'unsupported_fields',
                        'ingredients' => 'invalid_ingredient_structure',
                        default => 'capability_call_rejected',
                    },
                    'correlation_id' => $correlationId,
                    'workspace_id' => $context['workspace_id'] ?? null,
                ]);
            }
            throw $exception;
        }
        if (app()->environment(['local', 'testing'])) {
            Log::info('ai.capability_call.validation_passed', [
                'stage' => 'capability_call_validation',
                'validator' => 'CapabilityCallValidator',
                'action_key' => $call->actionKey,
                'correlation_id' => $correlationId,
                'workspace_id' => $context['workspace_id'] ?? null,
            ]);
        }
        Log::info('ai.capability_call.validated', [
            'action_key' => $call->actionKey,
            'correlation_id' => $correlationId,
            'workspace_id' => $context['workspace_id'] ?? null,
        ]);

        return $call;
    }

    /** @param array<string, mixed> $context */
    public function routeRecipeCreate(array $context): CapabilityCall
    {
        $call = $this->route($context);
        if (!$call instanceof CapabilityCall) {
            throw ValidationException::withMessages(['action_key' => ['The selected capability is not recipes.create.']]);
        }

        return $call;
    }
}
