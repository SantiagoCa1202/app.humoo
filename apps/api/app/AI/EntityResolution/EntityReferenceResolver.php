<?php

namespace App\AI\EntityResolution;

use App\Models\EntityAlias;
use Illuminate\Support\Facades\Log;

class EntityReferenceResolver
{
    public function __construct(
        private EntityResolverRegistry $registry,
        private EntityReferenceNormalizer $normalizer,
    ) {
    }

    public function resolve(EntityResolutionRequest $request): EntityResolutionResult
    {
        $startedAt = microtime(true);
        $adapter = $this->registry->forType($request->entityType);
        if ($adapter === null) {
            return $this->observed($request, new EntityResolutionResult('invalid'), $startedAt);
        }

        $explicitId = trim((string) ($request->knownPayload[$request->unresolvedField] ?? ''));
        if ($this->isId($explicitId)) {
            $candidate = $adapter->findById($request, $explicitId);
            return $this->observed($request, new EntityResolutionResult($candidate ? 'resolved' : 'not_found', $candidate, $candidate ? [$candidate] : [], false, 'explicit_id', $candidate ? 1.0 : null, null), $startedAt);
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
                return $this->observed($request, new EntityResolutionResult('resolved', $contextual, [$contextual], false, 'conversation_reference', 1.0, null), $startedAt);
            }
        }

        $variants = $this->normalizer->variants($reference);
        if ($variants === []) {
            return $this->observed($request, new EntityResolutionResult('not_found'), $startedAt);
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
                return $this->observed($request, new EntityResolutionResult('resolved', $candidate, [$candidate], false, 'confirmed_alias', 0.99, null), $startedAt);
            }
        }

        $scored = collect($adapter->candidates($request, (int) config('ai.entity_resolution.candidate_limit', 40)))
            ->map(fn (EntityCandidate $candidate) => $this->score($candidate, $variants, $request))
            ->filter(fn (EntityCandidate $candidate): bool => $candidate->score > 0)
            ->sortByDesc('score')->values();
        if ($scored->isEmpty()) {
            return $this->observed($request, new EntityResolutionResult('not_found'), $startedAt);
        }

        $top = $scored->first();
        $second = $scored->get(1);
        $gap = $second ? $top->score - $second->score : 1.0;
        $threshold = $request->riskLevel === 'read'
            ? (float) config('ai.entity_resolution.read_threshold', 0.76)
            : (float) config('ai.entity_resolution.write_threshold', 0.90);
        $minimumGap = (float) config('ai.entity_resolution.minimum_score_gap', 0.08);
        $result = $top->score >= $threshold && ($top->matchStrategy === 'exact_normalized' || $gap >= $minimumGap)
            ? new EntityResolutionResult('resolved', $top, $scored->take(5)->all(), false, $top->matchStrategy, $top->score, $gap)
            : new EntityResolutionResult('ambiguous', null, $scored->take(5)->all(), false, $top->matchStrategy, $top->score, $gap);

        return $this->observed($request, $result, $startedAt);
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
            'strategy' => $result->strategy,
            'candidate_count' => count($result->candidates),
            'top_score' => $result->topScore,
            'score_gap' => $result->scoreGap,
            'ai_fallback_used' => $result->aiFallbackUsed,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'workspace_id' => $request->workspaceId,
        ]);

        return $result;
    }
}
