<?php

namespace App\AI\Fallback;

use App\AI\Contracts\AIProvider;
use App\AI\EntityResolution\EntityResolutionRequest;
use App\AI\EntityResolution\EntityResolutionResult;
use App\AI\Tools\ToolRegistry;
use App\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Log;

class SemanticFallbackOrchestrator
{
    public function __construct(
        private AIProvider $provider,
        private ToolRegistry $toolRegistry,
    ) {
    }

    public function attempt(EntityResolutionRequest $request, EntityResolutionResult $local): SemanticFallbackResult
    {
        if (!in_array($local->status, ['ambiguous', 'low_confidence', 'not_found_local', 'missing_extractable_fields', 'unrecognized_intent', 'unsupported_local_pattern'], true)) {
            return new SemanticFallbackResult('skipped');
        }

        $startedAt = microtime(true);
        Log::info('ai.semantic_fallback.started', [
            'deterministic_status' => $local->status,
            'fallback_trigger_reason' => $local->status,
            'action_key' => $request->actionKey,
            'entity_type' => $request->entityType,
            'workspace_id' => $request->workspaceId,
        ]);

        try {
            $decision = $this->provider->generate([
                'locale' => $request->locale,
                'message' => $request->originalMessage ?: $request->rawReference,
                'available_tools' => $this->availableCapabilities(),
                'semantic_fallback_request' => [
                    'original_message' => $request->originalMessage ?: $request->rawReference,
                    'locale' => $request->locale,
                    'conversation_context' => $request->conversationReferences,
                    'deterministic_result' => $local->toArray(),
                    'proposed_action_key' => $request->actionKey,
                    'known_payload' => $request->knownPayload,
                    'unresolved_fields' => [$request->unresolvedField],
                    'entity_types' => [$request->entityType],
                    'available_capabilities' => $this->availableCapabilities(),
                    'safe_candidate_summaries' => array_map(static fn ($candidate): array => $candidate->toArray(), $local->candidates),
                ],
            ]);
        } catch (AiProviderException $exception) {
            Log::warning('ai.semantic_fallback.failed', [
                'deterministic_status' => $local->status,
                'exception_class' => class_basename($exception),
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'workspace_id' => $request->workspaceId,
            ]);

            return new SemanticFallbackResult('failed', reasonCode: 'provider_failure', providerUsed: true);
        }

        $result = $this->validatedResult($decision, $request);
        Log::info('ai.semantic_fallback.completed', [
            'deterministic_status' => $local->status,
            'ai_fallback_used' => true,
            'ai_attempt_count' => 1,
            'search_variants_count' => count($result->searchRequests),
            'revalidation_status' => $result->status,
            'resolved_action_key' => $result->resolvedActionKey,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'workspace_id' => $request->workspaceId,
        ]);

        return $result;
    }

    private function validatedResult(array $decision, EntityResolutionRequest $request): SemanticFallbackResult
    {
        $status = (string) ($decision['status'] ?? 'not_found');
        $allowedStatuses = ['resolved', 'clarification_required', 'not_found', 'unsupported_capability'];
        if (!in_array($status, $allowedStatuses, true)) {
            return new SemanticFallbackResult('failed', reasonCode: 'invalid_fallback_status', providerUsed: true);
        }

        $actionKey = $decision['resolved_action_key'] ?? null;
        $actionKey = is_string($actionKey) && $actionKey !== ''
            ? $this->toolRegistry->actionKeyForIntent($actionKey)
            : $request->actionKey;
        if ($actionKey !== null && $this->toolRegistry->actionKeyForIntent($actionKey) === null) {
            return new SemanticFallbackResult('failed', reasonCode: 'invalid_action_key', providerUsed: true);
        }

        $searchRequests = collect($decision['search_requests'] ?? [])
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->filter(fn (array $item): bool => ($item['entity_type'] ?? null) === $request->entityType)
            ->map(static fn (array $item): string => trim((string) ($item['query'] ?? '')))
            ->filter(static fn (string $query): bool => $query !== '')
            ->unique()
            ->take((int) config('ai.semantic_fallback.max_search_variants', 3))
            ->values()
            ->all();
        $selectedIds = collect($decision['selected_candidate_ids'] ?? [])
            ->filter(static fn (mixed $id): bool => is_string($id) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $id) === 1)
            ->unique()->take(5)->values()->all();

        return new SemanticFallbackResult(
            status: $status,
            resolvedActionKey: $actionKey,
            payloadPatch: is_array($decision['payload_patch'] ?? null) ? $decision['payload_patch'] : [],
            searchRequests: $searchRequests,
            selectedCandidateIds: $selectedIds,
            confidence: is_numeric($decision['confidence'] ?? null) ? (float) $decision['confidence'] : null,
            needsClarification: (bool) ($decision['needs_clarification'] ?? false),
            clarificationFields: collect($decision['clarification_fields'] ?? [])->filter(static fn (mixed $field): bool => is_string($field))->values()->all(),
            reasonCode: is_string($decision['reason_code'] ?? null) ? $decision['reason_code'] : null,
            providerUsed: true,
        );
    }

    private function availableCapabilities(): array
    {
        return collect($this->toolRegistry->allMetadata())
            ->map(static fn (array $tool): array => ['key' => $tool['key'], 'entity_type' => $tool['entity_type'] ?? null])
            ->values()->all();
    }
}
