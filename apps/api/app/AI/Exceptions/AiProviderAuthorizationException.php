<?php

namespace App\AI\Exceptions;

class AiProviderAuthorizationException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_AUTH_ERROR';
    }
}
