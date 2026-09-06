<?php

namespace App\AI\Exceptions;

class AiProviderProtocolStateException extends AiProviderValidationException
{
    public function internalCode(): string
    {
        return 'AI_PROTOCOL_STATE_CORRUPTED';
    }
}
