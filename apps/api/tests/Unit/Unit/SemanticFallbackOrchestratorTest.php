<?php

namespace Tests\Unit\Unit;

use App\AI\Contracts\AIProvider;
use App\AI\EntityResolution\EntityResolutionRequest;
use App\AI\EntityResolution\EntityResolutionResult;
use App\AI\Fallback\SemanticFallbackOrchestrator;
use App\AI\Tools\ToolRegistry;
use Tests\TestCase;

class SemanticFallbackOrchestratorTest extends TestCase
{
    public function test_local_not_found_calls_the_bounded_fallback_and_keeps_only_safe_search_variants(): void
    {
        $provider = new class implements AIProvider {
            public array $contexts = [];

            public function generate(array $context): array
            {
                $this->contexts[] = $context;

                return [
                    'status' => 'resolved',
                    'resolved_action_key' => 'recipes.update',
                    'payload_patch' => [],
                    'search_requests' => [
                        ['entity_type' => 'recipe', 'query' => 'ranch'],
                        ['entity_type' => 'menu', 'query' => 'must be ignored'],
                    ],
                    'selected_candidate_ids' => ['not-an-ulid'],
                    'confidence' => 0.98,
                    'needs_clarification' => false,
                    'clarification_fields' => [],
                    'reason_code' => 'typo_variant',
                ];
            }
        };
        $fallback = new SemanticFallbackOrchestrator($provider, new ToolRegistry());

        $result = $fallback->attempt(new EntityResolutionRequest(
            workspaceId: '01J00000000000000000000000',
            actorId: null,
            conversationId: null,
            actionKey: 'recipes.update',
            entityType: 'recipe',
            unresolvedField: 'recipe_id',
            rawReference: 'elreanch',
        ), new EntityResolutionResult('not_found_local'));

        $this->assertTrue($result->providerUsed);
        $this->assertSame(['ranch'], $result->searchRequests);
        $this->assertSame([], $result->selectedCandidateIds);
        $this->assertSame('recipes.update', $result->resolvedActionKey);
        $this->assertCount(1, $provider->contexts);
    }

    public function test_invented_action_key_is_rejected_before_revalidation(): void
    {
        $provider = new class implements AIProvider {
            public function generate(array $context): array
            {
                return [
                    'status' => 'resolved',
                    'resolved_action_key' => 'invented.action',
                    'payload_patch' => [],
                    'search_requests' => [],
                    'selected_candidate_ids' => [],
                    'confidence' => 1,
                    'needs_clarification' => false,
                    'clarification_fields' => [],
                    'reason_code' => 'invalid',
                ];
            }
        };

        $result = (new SemanticFallbackOrchestrator($provider, new ToolRegistry()))->attempt(new EntityResolutionRequest(
            workspaceId: '01J00000000000000000000000',
            actorId: null,
            conversationId: null,
            actionKey: 'recipes.update',
            entityType: 'recipe',
            unresolvedField: 'recipe_id',
            rawReference: 'ranch',
        ), new EntityResolutionResult('not_found_local'));

        $this->assertSame('failed', $result->status);
        $this->assertSame('invalid_action_key', $result->reasonCode);
    }
}
