<?php

namespace App\AI\Exceptions;

use RuntimeException;

final class AiRuntimeException extends RuntimeException
{
    public function __construct(
        private string $runtimeCode,
        private string $messageKey,
        private bool $canRetry,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function internalCode(): string
    {
        return $this->runtimeCode;
    }

    public function publicMessageKey(): string
    {
        return $this->messageKey;
    }

    public function retryable(): bool
    {
        return $this->canRetry;
    }
}
