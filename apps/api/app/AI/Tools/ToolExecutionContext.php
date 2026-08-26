<?php

namespace App\AI\Tools;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

final class ToolExecutionContext
{
    public function __construct(
        public Workspace $workspace,
        public User $user,
        public WorkspaceMembership $membership,
        public Conversation $conversation,
        public string $locale,
        public string $timezone,
        public ?Message $message = null,
    ) {
    }

    public static function fromChatContext(array $context): self
    {
        return new self(
            workspace: $context['workspace'],
            user: $context['user'],
            membership: $context['membership'],
            conversation: $context['conversation'],
            locale: (string) ($context['locale'] ?? 'en'),
            timezone: (string) ($context['timezone'] ?? $context['workspace']->timezone ?? 'UTC'),
            message: $context['user_message'] ?? null,
        );
    }

    public function toArray(array $additional = []): array
    {
        return [
            ...$additional,
            'conversation' => $this->conversation,
            'conversation_id' => $this->conversation->id,
            'locale' => $this->locale,
            'membership' => $this->membership,
            'message_id' => $this->message?->id,
            'timezone' => $this->timezone,
            'user' => $this->user,
            'user_message' => $this->message,
            'workspace' => $this->workspace,
        ];
    }
}
