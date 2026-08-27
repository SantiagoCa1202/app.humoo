<?php

namespace Tests\Unit;

use App\AI\EntityResolution\EntityCandidate;
use App\AI\EntityResolution\EntityReferenceNormalizer;
use App\AI\EntityResolution\EntityReferenceResolver;
use App\AI\EntityResolution\EntityResolutionRequest;
use App\AI\EntityResolution\EntityResolverAdapter;
use App\AI\EntityResolution\EntityResolverRegistry;
use App\AI\Fallback\SemanticFallbackOrchestrator;
use App\AI\Fallback\SemanticFallbackResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EntityReferenceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_canonical_reference_resolves_without_a_suggestion(): void
    {
        $resolver = $this->resolver([
            $this->candidate('01J00000000000000000000001', 'Ranch casero'),
        ]);

        $result = $resolver->resolveLocal($this->request('Ranch casero'));

        $this->assertSame('resolved', $result->status);
        $this->assertSame('01J00000000000000000000001', $result->resolved?->entityId);
    }

    public function test_unique_non_exact_write_match_requires_confirmation(): void
    {
        $resolver = $this->resolver([
            $this->candidate('01J00000000000000000000001', 'Ranch casero'),
        ]);

        $result = $resolver->resolveLocal($this->request('ranch'));

        $this->assertSame('suggested_match', $result->status);
        $this->assertSame('01J00000000000000000000001', $result->resolved?->entityId);
    }

    public function test_multiple_non_exact_candidates_are_ambiguous_even_when_one_scores_higher(): void
    {
        $resolver = $this->resolver([
            $this->candidate('01J00000000000000000000001', 'Ranch casero'),
            $this->candidate('01J00000000000000000000002', 'Ranch picante'),
        ]);

        $result = $resolver->resolveLocal($this->request('ranch'));

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->resolved);
        $this->assertCount(2, $result->candidates);
    }

    public function test_invented_identifier_finishes_as_not_found_after_revalidation(): void
    {
        $fallback = Mockery::mock(SemanticFallbackOrchestrator::class);
        $fallback->shouldReceive('attempt')->once()->andReturn(new SemanticFallbackResult('not_found'));
        $resolver = $this->resolver([], $fallback);

        $result = $resolver->resolve($this->request('01J00000000000000000000009', ['recipe_id' => '01J00000000000000000000009']));

        $this->assertSame('final_not_found', $result->status);
        $this->assertSame('not_found_local', $result->localStatus);
    }

    /** @param EntityCandidate[] $candidates */
    private function resolver(array $candidates, ?SemanticFallbackOrchestrator $fallback = null): EntityReferenceResolver
    {
        $adapter = new class($candidates) implements EntityResolverAdapter {
            /** @param EntityCandidate[] $candidates */
            public function __construct(private array $candidates) {}
            public function entityType(): string { return 'recipe'; }
            public function findById(EntityResolutionRequest $request, string $id): ?EntityCandidate
            {
                return collect($this->candidates)->first(fn (EntityCandidate $candidate): bool => $candidate->entityId === $id);
            }
            public function candidates(EntityResolutionRequest $request, int $limit): array { return $this->candidates; }
        };

        return new EntityReferenceResolver(
            new EntityResolverRegistry([$adapter]),
            new EntityReferenceNormalizer(),
            $fallback ?? Mockery::mock(SemanticFallbackOrchestrator::class)
        );
    }

    private function request(?string $reference, array $knownPayload = []): EntityResolutionRequest
    {
        return new EntityResolutionRequest(
            workspaceId: '01J00000000000000000000000',
            actorId: '01J00000000000000000000000',
            conversationId: '01J00000000000000000000000',
            actionKey: 'recipes.update',
            entityType: 'recipe',
            unresolvedField: 'recipe_id',
            rawReference: $reference,
            knownPayload: $knownPayload,
            riskLevel: 'write',
        );
    }

    private function candidate(string $id, string $name): EntityCandidate
    {
        return new EntityCandidate($id, 'recipe', $name, ['name' => $name]);
    }
}
