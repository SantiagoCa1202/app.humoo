<?php

namespace App\AI\Exceptions;

class AiProviderConversationLockedException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_CONVERSATION_LOCKED';
    }
}
