<?php

namespace App\AI\Exceptions;

class AiProviderInvalidResponseException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_INVALID_RESPONSE';
    }
}
