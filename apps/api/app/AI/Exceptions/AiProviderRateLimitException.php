<?php

namespace App\AI\Exceptions;

class AiProviderRateLimitException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_RATE_LIMITED';
    }
}
