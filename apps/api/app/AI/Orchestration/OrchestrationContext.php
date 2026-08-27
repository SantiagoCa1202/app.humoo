<?php

namespace App\AI\Orchestration;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Str;

/**
 * Immutable request context shared by every orchestration stage.
 *
 * Conversation state remains server-owned: callers never provide a workspace,
 * actor, draft, or continuation snapshot through the chat message payload.
 */
final class OrchestrationContext
{
    /** @param array<int, array<string, mixed>> $entityRefs */
    /** @param array<int, array<string, mixed>> $recentMessages */
    public function __construct(
        public Workspace $workspace,
        public User $actor,
        public WorkspaceMembership $membership,
        public Conversation $conversation,
        public Message $currentMessage,
        public Message $assistantMessage,
        public string $locale,
        public string $timezone,
        public array $entityRefs,
        public array $recentMessages,
        public array $availableTools,
        public string $systemInstructions,
        public array $pendingContinuations,
        public array $activeEntities,
        public ?array $lastInteraction,
        public string $correlationId,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'active_entities' => $this->activeEntities,
            'assistant_message' => $this->assistantMessage,
            'available_tools' => $this->availableTools,
            'conversation' => $this->conversation,
            'correlation_id' => $this->correlationId,
            'entity_refs' => $this->entityRefs,
            'last_interaction' => $this->lastInteraction,
            'locale' => $this->locale,
            'membership' => $this->membership,
            'pending_continuations' => $this->pendingContinuations,
            'recent_entity_refs' => $this->entityRefs,
            'recent_messages' => $this->recentMessages,
            'system_instructions' => $this->systemInstructions,
            'timezone' => $this->timezone,
            'user' => $this->actor,
            'user_message' => $this->currentMessage,
            'workspace' => $this->workspace,
        ];
    }

    public static function correlationId(): string
    {
        $requestId = app()->bound('request')
            ? app('request')->attributes->get('request_id')
            : null;

        return is_string($requestId) && $requestId !== ''
            ? $requestId
            : (string) Str::ulid();
    }
}
