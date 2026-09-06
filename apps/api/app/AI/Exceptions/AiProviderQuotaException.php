<?php

namespace App\AI\Exceptions;

class AiProviderQuotaException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_QUOTA_EXHAUSTED';
    }
}
