<?php

namespace App\AI\Streaming;

use App\AI\Runtime\AiRunLifecycle;
use App\Models\Conversation;
use App\Models\Message;

class ChatStreamPublisher
{
    public function __construct(
        private AiRunLifecycle $aiRunLifecycle,
        private BestEffortChatBroadcaster $realtime,
    ) {}

    public function activity(
        Conversation $conversation,
        Message $assistantMessage,
        string $stage,
        string $label,
    ): void {
        $run = $this->aiRunLifecycle->progressByAssistantMessage(
            (string) $assistantMessage->id,
            $this->normalizeStage($stage),
        );
        $this->realtime->publish(
            $conversation->id,
            $assistantMessage->id,
            'activity',
            [
                'label' => $this->safeLabel($label),
                'stage' => $stage,
                'sequence' => $run?->sequence,
            ],
        );
    }

    public function textDelta(
        Conversation $conversation,
        Message $assistantMessage,
        string $delta,
    ): void {
        if ($delta === '') {
            return;
        }

        $this->realtime->publish(
            $conversation->id,
            $assistantMessage->id,
            'text.delta',
            ['delta' => mb_substr($delta, 0, 4096)],
        );
    }

    public function completed(Conversation $conversation, Message $assistantMessage): void
    {
        $this->realtime->publish($conversation->id, $assistantMessage->id, 'completed');
    }

    public function failed(Conversation $conversation, Message $assistantMessage): void
    {
        $this->realtime->publish($conversation->id, $assistantMessage->id, 'failed');
    }

    private function safeLabel(string $label): string
    {
        return mb_substr(trim($label), 0, 160);
    }

    private function normalizeStage(string $stage): string
    {
        return match ($stage) {
            'analysis' => 'analyzing',
            'tool_discovery', 'discovering' => 'discovering_tools',
            'preparing_response' => 'preparing_execution',
            default => $stage,
        };
    }
}
