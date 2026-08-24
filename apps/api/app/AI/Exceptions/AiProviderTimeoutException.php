<?php

namespace App\AI\Exceptions;

class AiProviderTimeoutException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_TIMEOUT';
    }
}
