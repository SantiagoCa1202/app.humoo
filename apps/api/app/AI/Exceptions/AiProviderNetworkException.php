<?php

namespace App\AI\Exceptions;

class AiProviderNetworkException extends AiProviderException
{
    public function internalCode(): string
    {
        return 'AI_NETWORK_ERROR';
    }
}
