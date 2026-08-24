<?php

namespace App\AI\Exceptions;

class AiProviderValidationException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_BAD_REQUEST';
    }
}
