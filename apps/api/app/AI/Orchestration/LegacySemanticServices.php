<?php

namespace App\AI\Orchestration;

use App\AI\Advisory\AdvisoryOrchestrator;
use App\AI\Advisory\RecipeDraftPayloadMapper;
use App\AI\Capabilities\CapabilityFunctionRouter;
use App\AI\Clarifications\PendingClarificationResolver;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Intent\RoutingDecisionValidator;
use App\Application\Actions\Chat\RecordUnsupportedCapability;

/**
 * Retained only as a migration type for unreachable legacy code. The
 * canonical AI-first runtime never resolves or executes these services.
 */
final class LegacySemanticServices
{
    public function __construct(
        public HybridIntentRouter $hybridIntentRouter,
        public IntentPatternRegistry $intentPatternRegistry,
        public RecordUnsupportedCapability $recordUnsupportedCapability,
        public AdvisoryOrchestrator $advisoryOrchestrator,
        public RecipeDraftPayloadMapper $recipeDraftPayloadMapper,
        public ContinuationResolver $continuationResolver,
        public PendingClarificationResolver $pendingClarificationResolver,
        public RoutingDecisionValidator $routingDecisionValidator,
        public CapabilityFunctionRouter $capabilityFunctionRouter,
    ) {
    }
}
