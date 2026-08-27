<?php

namespace App\AI\EntityResolution;

use App\AI\Support\Latency;
use App\AI\Fallback\SemanticFallbackOrchestrator;
use App\AI\Fallback\SemanticFallbackResult;
use App\Models\EntityAlias;
use Illuminate\Support\Facades\Log;

class EntityReferenceResolver
{
    public function __construct(
        private EntityResolverRegistry $registry,
        private EntityReferenceNormalizer $normalizer,
        private SemanticFallbackOrchestrator $semanticFallback,
    ) {
    }

    public function resolve(EntityResolutionRequest $request): EntityResolutionResult
    {
        $startedAt = microtime(true);
        $local = $this->resolveLocal($request);
        if (!in_array($local->status, ['ambiguous', 'low_confidence', 'not_found_local', 'missing_extractable_fields', 'unrecognized_intent', 'unsupported_local_pattern'], true)) {
            return $this->observed($request, $local, $startedAt);
        }

        $fallback = $this->semanticFallback->attempt($request, $local);
        if ($fallback->status === 'failed') {
            return $this->observed($request, new EntityResolutionResult(
                'system_failure', null, [], true, null, null, null, $local->status, $fallback->reasonCode
            ), $startedAt);
        }
        if (($fallback->status === 'clarification_required' || $fallback->needsClarification) && $fallback->searchRequests === []) {
            return $this->observed($request, new EntityResolutionResult(
                'clarification_required', null, $local->candidates, true, $local->strategy, $local->topScore, $local->scoreGap, $local->status, $fallback->reasonCode
            ), $startedAt);
        }

        $revalidated = $this->revalidateFallbackSearches($request, $local, $fallback);
        return $this->observed($request, $revalidated, $startedAt);
    }

    public function resolveLocal(EntityResolutionRequest $request): EntityResolutionResult
    {
        $adapter = $this->registry->forType($request->entityType);
        if ($adapter === null) {
            return new EntityResolutionResult('invalid');
        }

        $explicitId = trim((string) ($request->knownPayload[$request->unresolvedField] ?? ''));
        if ($this->isId($explicitId)) {
            $candidate = $adapter->findById($request, $explicitId);
            return new EntityResolutionResult($candidate ? 'resolved' : 'not_found_local', $candidate, $candidate ? [$candidate] : [], false, 'explicit_id', $candidate ? 1.0 : null, null);
        }

        $reference = trim((string) $request->rawReference);
        if ($reference === '' || $this->isContextualReference($reference)) {
            $contextual = collect($request->conversationReferences)
                ->filter(fn (mixed $ref): bool => is_array($ref) && ($ref['type'] ?? null) === $request->entityType)
                ->sortByDesc(fn (array $ref): int => ($ref['role'] ?? null) === 'active' ? 1 : 0)
                ->map(fn (array $ref) => $adapter->findById($request, (string) ($ref['id'] ?? '')))
                ->filter()
                ->first();
            if ($contextual instanceof EntityCandidate) {
                return new EntityResolutionResult('resolved', $contextual, [$contextual], false, 'conversation_reference', 1.0, null);
            }
        }

        $variants = $this->normalizer->variants($reference);
        if ($variants === []) {
            return new EntityResolutionResult('missing_extractable_fields');
        }

        $aliasIds = EntityAlias::query()->where('workspace_id', $request->workspaceId)
            ->where('entity_type', $request->entityType)
            ->whereIn('normalized_alias', $variants)
            ->pluck('entity_id')->unique()->values();
        if ($aliasIds->count() === 1) {
            $candidate = $adapter->findById($request, (string) $aliasIds->first());
            if ($candidate) {
                $candidate->score = 0.99;
                $candidate->matchStrategy = 'confirmed_alias';
                return new EntityResolutionResult('resolved', $candidate, [$candidate], false, 'confirmed_alias', 0.99, null);
            }
        }

        $scored = collect($adapter->candidates($request, (int) config('ai.entity_resolution.candidate_limit', 40)))
            ->map(fn (EntityCandidate $candidate) => $this->score($candidate, $variants, $request))
            ->filter(fn (EntityCandidate $candidate): bool => $candidate->score > 0)
            ->sortByDesc('score')->values();
        if ($scored->isEmpty()) {
            return new EntityResolutionResult('not_found_local');
        }

        $top = $scored->first();
        $second = $scored->get(1);
        $gap = $second ? $top->score - $second->score : 1.0;
        $threshold = $request->riskLevel === 'read'
            ? (float) config('ai.entity_resolution.read_threshold', 0.76)
            : (float) config('ai.entity_resolution.write_threshold', 0.90);
        $candidates = $scored->take(5)->all();

        // An exact canonical name is the only text match that is safe to use
        // immediately for a write. A high-confidence partial, token or fuzzy
        // match remains a suggestion and must be accepted by the user.
        $exactMatches = collect($candidates)
            ->filter(static fn (EntityCandidate $candidate): bool => $candidate->matchStrategy === 'exact_normalized');
        if ($exactMatches->count() === 1) {
            return new EntityResolutionResult('resolved', $top, $candidates, false, $top->matchStrategy, $top->score, $gap);
        }

        if ($top->score < $threshold) {
            return new EntityResolutionResult('low_confidence', null, $candidates, false, $top->matchStrategy, $top->score, $gap);
        }

        if (count($candidates) === 1) {
            return new EntityResolutionResult('suggested_match', $top, [$top], false, $top->matchStrategy, $top->score, $gap);
        }

        return new EntityResolutionResult('ambiguous', null, $candidates, false, $top->matchStrategy, $top->score, $gap);
    }

    private function revalidateFallbackSearches(EntityResolutionRequest $request, EntityResolutionResult $local, SemanticFallbackResult $fallback): EntityResolutionResult
    {
        $attempts = [$local];
        $seen = [$this->normalizer->normalize((string) $request->rawReference) => true];
        foreach ($fallback->searchRequests as $query) {
            $normalized = $this->normalizer->normalize($query);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $attempts[] = $this->resolveLocal(new EntityResolutionRequest(
                workspaceId: $request->workspaceId,
                actorId: $request->actorId,
                conversationId: $request->conversationId,
                actionKey: $request->actionKey,
                entityType: $request->entityType,
                unresolvedField: $request->unresolvedField,
                rawReference: $query,
                knownPayload: $request->knownPayload,
                contextConstraints: $request->contextConstraints,
                conversationReferences: $request->conversationReferences,
                locale: $request->locale,
                riskLevel: $request->riskLevel,
                originalMessage: $request->originalMessage,
            ));
        }

        foreach ($attempts as $attempt) {
            if ($attempt->status !== 'resolved') {
                continue;
            }

            return new EntityResolutionResult(
                'resolved',
                $attempt->resolved,
                $attempt->candidates,
                true,
                $attempt->strategy,
                $attempt->topScore,
                $attempt->scoreGap,
                $local->status,
                $fallback->reasonCode
            );
        }

        $candidates = collect($attempts)->flatMap(static fn (EntityResolutionResult $attempt): array => $attempt->candidates)
            ->unique(fn (EntityCandidate $candidate): string => $candidate->entityId)->sortByDesc('score')->values();
        $selected = $candidates->whereIn('entityId', $fallback->selectedCandidateIds)->values();
        $threshold = $request->riskLevel === 'read'
            ? (float) config('ai.entity_resolution.read_threshold', 0.76)
            : (float) config('ai.entity_resolution.write_threshold', 0.90);
        if ($request->riskLevel === 'read' && $selected->count() === 1 && ($fallback->confidence ?? 0) >= $threshold) {
            $candidate = $selected->first();
            return new EntityResolutionResult('resolved', $candidate, [$candidate], true, 'ai_reranked_authorized_candidate', $candidate->score, null, $local->status, $fallback->reasonCode);
        }
        if ($selected->count() === 1) {
            $candidate = $selected->first();
            return new EntityResolutionResult('suggested_match', $candidate, [$candidate], true, 'ai_reranked_authorized_candidate', $candidate->score, null, $local->status, $fallback->reasonCode);
        }
        if ($candidates->isNotEmpty()) {
            return new EntityResolutionResult('ambiguous', null, $candidates->take(5)->all(), true, 'ai_search_revalidated', $candidates->first()->score, null, $local->status, $fallback->reasonCode);
        }

        return new EntityResolutionResult('final_not_found', null, [], true, null, null, null, $local->status, $fallback->reasonCode);
    }

    private function score(EntityCandidate $candidate, array $variants, EntityResolutionRequest $request): EntityCandidate
    {
        foreach ($candidate->searchableValues as $field => $value) {
            $normalizedValue = $this->normalizer->normalize((string) $value);
            $tokens = $this->normalizer->tokens($normalizedValue);
            foreach ($variants as $variant) {
                $variantTokens = $this->normalizer->tokens($variant);
                if ($normalizedValue === $variant) {
                    return $this->matched($candidate, 1.0, 'exact_normalized', (string) $field);
                }
                if ($variantTokens !== [] && count(array_diff($variantTokens, $tokens)) === 0) {
                    $candidate = $this->matched($candidate, 0.90, 'token_exact', (string) $field);
                }
                if (str_contains($normalizedValue, $variant) || str_contains($variant, $normalizedValue)) {
                    $candidate = $this->matched($candidate, 0.82, 'controlled_partial', (string) $field);
                }
                if (max(strlen($normalizedValue), strlen($variant)) >= 4) {
                    $distance = levenshtein($normalizedValue, $variant);
                    $ratio = 1 - ($distance / max(strlen($normalizedValue), strlen($variant)));
                    if ($ratio >= 0.78 && $ratio > $candidate->score) {
                        $candidate = $this->matched($candidate, round($ratio, 3), 'fuzzy', (string) $field);
                    }
                }
            }
        }

        return $candidate;
    }

    private function matched(EntityCandidate $candidate, float $score, string $strategy, string $field): EntityCandidate
    {
        if ($score > $candidate->score) {
            $candidate->score = $score;
            $candidate->matchStrategy = $strategy;
            $candidate->matchedFields = [$field];
        }

        return $candidate;
    }

    private function isId(string $value): bool { return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value) === 1; }
    private function isContextualReference(string $value): bool { return in_array($this->normalizer->normalize($value), ['that', 'this', 'this one', 'ese', 'esa', 'este', 'esta', 'anterior', 'previous'], true); }

    private function observed(EntityResolutionRequest $request, EntityResolutionResult $result, float $startedAt): EntityResolutionResult
    {
        Log::info('ai.entity_resolution.completed', [
            'action_key' => $request->actionKey,
            'entity_type' => $request->entityType,
            'resolution_status' => $result->status,
            'deterministic_status' => $result->localStatus ?? $result->status,
            'final_status' => $result->status,
            'fallback_trigger_reason' => $result->fallbackReason,
            'strategy' => $result->strategy,
            'candidate_count' => count($result->candidates),
            'top_score' => $result->topScore,
            'score_gap' => $result->scoreGap,
            'ai_fallback_used' => $result->aiFallbackUsed,
            'latency_ms' => Latency::fromSeconds($startedAt, microtime(true)),
            'workspace_id' => $request->workspaceId,
        ]);
        Log::info('entity_reference.completed', [
            'action_key' => $request->actionKey,
            'ai_fallback_used' => $result->aiFallbackUsed,
            'candidate_count' => count($result->candidates),
            'conversation_id' => $request->conversationId,
            'entity_type' => $request->entityType,
            'final_status' => $result->status,
            'resolution_strategy' => $result->strategy,
            'workspace_id' => $request->workspaceId,
        ]);

        return $result;
    }
}
