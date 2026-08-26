<?php

namespace App\AI\Advisory;

use App\AI\Contracts\AIProvider;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\Conversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdvisoryOrchestrator
{
    public function __construct(
        private AIProvider $provider,
        private ToolExecutor $toolExecutor,
        private ToolRegistry $toolRegistry,
        private PortionAnalysisService $portionAnalysis,
        private RecipeDraftScalingService $recipeDraftScaling
    ) {
    }

    public function respond(array $context, array $decision, AiRun $aiRun): array
    {
        $mode = (string) ($decision['interaction_mode'] ?? InteractionMode::ADVISORY);
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];
        $analysisType = (string) ($slots['analysis_type'] ?? ($mode === InteractionMode::GENERATIVE ? 'recipe_generation' : 'general_advisory'));
        $plan = array_slice($this->readPlan($mode, $analysisType, $slots, $context), 0, max(1, (int) config('ai.max_advisory_tool_calls', config('ai.max_tool_calls_per_turn', 4))));
        $toolResults = [];
        $entityRefs = [];

        foreach ($plan as $position => $step) {
            $tool = $this->toolRegistry->resolve($step['action_id']);
            if (($tool['policy']['risk'] ?? null) !== 'read' || ($tool['mode'] ?? null) !== 'read') {
                continue;
            }

            $call = AiToolCall::query()->create([
                'workspace_id' => $context['workspace']->id,
                'ai_run_id' => $aiRun->id,
                'tool_key' => $tool['key'],
                'position' => $position,
                'arguments_json' => ['input' => $step['input']],
                'requires_confirmation' => false,
                'started_at' => now(),
                'status' => 'running',
            ]);

            try {
                $result = $this->toolExecutor->request($this->toolContext($context, $call->id), [
                    'action_id' => $tool['action_id'],
                    'input' => $step['input'],
                    'idempotency_key' => null,
                ]);
                $call->forceFill([
                    'completed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'completed',
                ])->save();
                $toolResults[$tool['key']] = $result;
                $entityRefs = [...$entityRefs, ...(is_array($result['entity_refs'] ?? null) ? $result['entity_refs'] : [])];
            } catch (\Throwable $exception) {
                $call->forceFill([
                    'completed_at' => now(),
                    'error_code' => class_basename($exception),
                    'error_message' => $exception->getMessage(),
                    'status' => 'failed',
                ])->save();
                throw $exception;
            }
        }

        $analytics = $this->portionAnalysis->analyze($toolResults, $analysisType);
        $activeDraft = $this->activeRecipeDraft($context['conversation'] ?? null);
        $providerResult = $this->provider->generate([
            'advisory_request' => [
                'analysis_type' => $analysisType,
                'interaction_mode' => $mode,
                'menu_structure_locked' => collect(\Illuminate\Support\Arr::get($analytics, 'facts.menus', []))->contains('menu_structure_locked', true),
                'requested_output' => $mode === InteractionMode::GENERATIVE ? 'recipe_draft' : 'recommendation',
                'user_constraints' => $slots['constraints'] ?? [],
            ],
            'advisory_context' => [
                'analytics' => $analytics,
                'active_recipe_draft' => $activeDraft,
                'workspace_data' => $this->compactToolData($toolResults),
            ],
            'locale' => $context['locale'],
            'message' => $context['user_message']->content_text ?? '',
            'message_id' => $context['user_message']->id ?? '',
            'recent_messages' => $context['recent_messages'] ?? [],
            'system_instructions' => $context['system_instructions'] ?? '',
        ]);
        $response = $this->normalizeResponse($providerResult, $mode, $analysisType, $analytics, $activeDraft, (string) ($context['user_message']->content_text ?? ''), $context['locale']);
        $response['recommendation_draft']['entity_refs'] = collect($entityRefs)
            ->filter(fn (mixed $ref): bool => is_array($ref) && filled($ref['id'] ?? null))
            ->unique(fn (array $ref): string => ($ref['type'] ?? '').':'.($ref['id'] ?? ''))
            ->values()
            ->all();
        $this->storeDraftState($context['conversation'] ?? null, $response);

        return [
            'blocks' => $this->blocks($response, $context['locale']),
            'entity_refs' => collect($entityRefs)->filter(fn (mixed $ref): bool => is_array($ref) && filled($ref['id'] ?? null))->unique(fn (array $ref): string => ($ref['type'] ?? '').':'.($ref['id'] ?? ''))->values()->all(),
            'interaction_mode' => $mode,
            'analysis_type' => $analysisType,
            'recommendation_draft' => $response['recommendation_draft'],
            'recipe_draft' => $response['recipe_draft'],
            'suggestions' => $response['suggestions'],
            'tool_keys' => array_keys($toolResults),
            'usage' => $providerResult['usage'] ?? null,
            'provider' => $providerResult['provider'] ?? null,
            'model' => $providerResult['model'] ?? null,
        ];
    }

    private function readPlan(string $mode, string $analysisType, array $slots, array $context): array
    {
        $message = (string) ($context['user_message']->content_text ?? '');
        $menuSearch = $this->namedTarget($message, ['menu', 'menú']) ?? ($slots['menu_search'] ?? null);
        $recipeSearch = $this->namedTarget($message, ['recipe', 'receta']) ?? $this->recipeTarget($message);
        $timezone = (string) ($context['timezone'] ?? 'UTC');
        $from = Carbon::now($timezone)->subDays(7)->toDateString();
        $to = Carbon::now($timezone)->toDateString();

        if ($mode === InteractionMode::GENERATIVE) {
            return array_values(array_filter([
                $menuSearch ? ['action_id' => 'menus.search', 'input' => ['search' => $menuSearch]] : null,
                $recipeSearch ? ['action_id' => 'recipes.list', 'input' => ['search' => $recipeSearch]] : null,
            ]));
        }

        return array_values(array_filter([
            ['action_id' => 'events.list', 'input' => ['date_from' => $from, 'date_to' => $to, 'limit' => 12]],
            $menuSearch ? ['action_id' => 'menus.search', 'input' => ['search' => $menuSearch]] : ['action_id' => 'menus.search', 'input' => []],
            in_array($analysisType, ['portion_analysis', 'prep_analysis', 'operational_analysis'], true)
                ? ['action_id' => 'prep.list', 'input' => ['active_only' => false, 'limit' => 12]]
                : null,
        ]));
    }

    private function toolContext(array $context, string $toolCallId): array
    {
        return [
            'ai_tool_call_id' => $toolCallId,
            'locale' => $context['locale'],
            'membership' => $context['membership'],
            'source_message' => $context['assistant_message'],
            'entity_refs' => $context['entity_refs'] ?? [],
            'routing' => $context['routing'] ?? null,
            'user' => $context['user'],
            'workspace' => $context['workspace'],
        ];
    }

    private function compactToolData(array $toolResults): array
    {
        return collect($toolResults)->map(fn (array $result): array => [
            'items' => array_slice((array) ($result['result_ref_json']['items'] ?? []), 0, 12),
        ])->all();
    }

    private function normalizeResponse(array $provider, string $mode, string $analysisType, array $analytics, ?array $activeDraft, string $message, string $locale): array
    {
        $recipeDraft = is_array($provider['recipe_draft'] ?? null) ? $provider['recipe_draft'] : null;
        if ($recipeDraft === null && $mode === InteractionMode::GENERATIVE && $activeDraft !== null) {
            $recipeDraft = $this->scaleDraftFromMessage($activeDraft, $message);
        }
        if ($recipeDraft !== null) {
            $recipeDraft = $this->normalizeRecipeDraft($recipeDraft);
        }

        $recommendations = collect($provider['recommendations'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'target' => $item['target'] ?? null,
                'current_value' => $item['current_value'] ?? null,
                'proposed_value' => $item['proposed_value'] ?? null,
                'unit' => $item['unit'] ?? null,
                'reasoning' => $item['reasoning'] ?? null,
                'confidence' => in_array($item['confidence'] ?? null, ['low', 'medium', 'high'], true) ? $item['confidence'] : 'low',
                'evidence' => array_values(array_filter((array) ($item['evidence'] ?? []), 'is_string')),
                'action_key' => is_string($item['action_key'] ?? null) ? $item['action_key'] : null,
                'action_input_json' => is_string($item['action_input_json'] ?? null) ? $item['action_input_json'] : null,
                'source' => 'ai_recommendation',
            ])->values()->all();

        return [
            'summary' => is_string($provider['summary'] ?? null) && trim($provider['summary']) !== ''
                ? $provider['summary']
                : 'The recommendation is based only on the workspace records and deterministic calculations shown below.',
            'findings' => array_values(array_filter((array) ($provider['findings'] ?? []), 'is_string')),
            'warnings' => array_values(array_unique([
                trans('chat.advisory.insufficient_outcome_data', [], $locale),
                ...array_filter((array) ($provider['warnings'] ?? []), 'is_string'),
            ])),
            'recommendation_draft' => [
                'id' => (string) Str::ulid(),
                'analysis_type' => $analysisType,
                'entity_refs' => [],
                'recommendations' => $recommendations,
                'source' => 'ai_recommendation',
            ],
            'recipe_draft' => $recipeDraft,
            'suggestions' => $mode === InteractionMode::GENERATIVE
                ? ['Save this recipe', 'Adjust this recipe']
                : ['Show the menu', 'Review prep'],
        ];
    }

    private function normalizeRecipeDraft(array $draft): array
    {
        return [
            'name' => trim((string) ($draft['name'] ?? 'Recipe proposal')),
            'description' => $draft['description'] ?? null,
            'yield' => is_numeric($draft['yield'] ?? null) ? (float) $draft['yield'] : null,
            'yield_unit' => $draft['yield_unit'] ?? null,
            'ingredients' => collect($draft['ingredients'] ?? [])->filter('is_array')->map(fn (array $ingredient): array => [
                'name' => $ingredient['name'] ?? $ingredient['ingredient_name'] ?? null,
                'quantity' => is_numeric($ingredient['quantity'] ?? null) ? (float) $ingredient['quantity'] : null,
                'unit' => $ingredient['unit'] ?? null,
                'preparation_note' => $ingredient['preparation_note'] ?? $ingredient['preparation'] ?? null,
            ])->filter(fn (array $ingredient): bool => filled($ingredient['name']))->values()->all(),
            'steps' => collect($draft['steps'] ?? [])->map(fn (mixed $step): ?string => is_string($step) ? $step : (is_array($step) ? ($step['instruction'] ?? null) : null))->filter()->values()->all(),
            'notes' => $draft['notes'] ?? null,
            'allergens' => array_values(array_filter((array) ($draft['allergens'] ?? []), 'is_string')),
            'source' => 'ai_generated_proposal',
        ];
    }

    private function blocks(array $response, string $locale): array
    {
        $blocks = [[
            'component' => 'advisory.result',
            'data' => [
                'summary' => $response['summary'],
                'findings' => $response['findings'],
                'recommendations' => $response['recommendation_draft']['recommendations'],
                'warnings' => $response['warnings'],
                'sources' => $response['recommendation_draft']['entity_refs'],
            ],
            'schema_version' => 1,
            'type' => 'component',
        ]];
        if ($response['recipe_draft'] !== null) {
            $blocks[] = ['component' => 'recipe.draft', 'data' => $response['recipe_draft'], 'schema_version' => 1, 'type' => 'component'];
        }

        return $blocks;
    }

    private function storeDraftState(?Conversation $conversation, array $response): void
    {
        if (!$conversation) {
            return;
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['active_recommendation_draft'] = $response['recommendation_draft'];
        if ($response['recipe_draft'] !== null) {
            $metadata['active_recipe_draft'] = $response['recipe_draft'];
        }
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    private function activeRecipeDraft(?Conversation $conversation): ?array
    {
        $metadata = is_array($conversation?->metadata) ? $conversation->metadata : [];
        return is_array($metadata['active_recipe_draft'] ?? null) ? $metadata['active_recipe_draft'] : null;
    }

    private function scaleDraftFromMessage(array $draft, string $message): array
    {
        if (preg_match('/(?:for|para)\s+(\d+(?:\.\d+)?)\s*(gallons?|galones?)/iu', $message, $matches) !== 1) {
            return $draft;
        }

        return $this->recipeDraftScaling->scale($draft, (float) $matches[1], Str::startsWith(Str::lower($matches[2]), 'gal') ? 'gallons' : $matches[2]);
    }

    private function namedTarget(string $message, array $nouns): ?string
    {
        $pattern = '/(?:'.implode('|', array_map(fn (string $noun): string => preg_quote($noun, '/'), $nouns)).')\s+([\pL\pN][\pL\pN\s&\'-]{1,80})/iu';
        return preg_match($pattern, $message, $matches) === 1 ? trim($matches[1]) : null;
    }

    private function recipeTarget(string $message): ?string
    {
        return preg_match('/(?:recipe|receta)\s+(?:de|for)?\s*([\pL\pN][\pL\pN\s&\'-]{1,80})/iu', $message, $matches) === 1
            ? trim($matches[1])
            : null;
    }
}
