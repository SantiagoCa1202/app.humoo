<?php

namespace App\AI\Exceptions;

class AiProviderAuthenticationException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_AUTH_ERROR';
    }
}
