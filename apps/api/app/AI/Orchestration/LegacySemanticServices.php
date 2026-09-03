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
 * Lazy dependency boundary for the disabled local-semantic pipeline.
 *
 * AIOrchestrator must not construct any of these services while the canonical
 * tool loop is enabled. They remain available only for an explicit legacy
 * rollback with AI_TOOL_LOOP_ENABLED=false.
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
