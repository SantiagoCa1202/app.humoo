<?php

namespace Tests\Unit\Unit;

use App\AI\Contracts\AIProvider;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolRegistry;
use RuntimeException;
use Tests\TestCase;

class HybridIntentRouterResilienceTest extends TestCase
{
    public function test_missing_pattern_storage_does_not_block_the_next_router(): void
    {
        $patterns = new class(new ToolRegistry()) extends IntentPatternRegistry {
            public function match(string $workspaceId, string $message): ?array
            {
                throw new RuntimeException('intent_patterns table does not exist');
            }
        };
        $fallback = new class implements AIProvider {
            public function generate(array $context): array
            {
                return ['intent' => 'events.list', 'slots' => []];
            }
        };

        $decision = (new HybridIntentRouter(
            new RuleBasedAIProvider(),
            $fallback,
            $patterns,
            new ToolRegistry()
        ))->route([
            'message' => 'something the deterministic router does not recognize',
            'workspace_id' => '01J00000000000000000000000',
        ]);

        $this->assertSame('ai', $decision['routing']['source']);
        $this->assertSame('events.list', $decision['routing']['action_key']);
    }
}
