<?php

namespace App\AI\Errors;

use App\AI\Exceptions\AiProviderException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ErrorResponseMapper
{
    /** @return array{correlation_id: string, error_code: string, message: string, retryable: bool, title: string} */
    public function map(Throwable $exception, string $locale, string $correlationId): array
    {
        [$errorCode, $messageKey, $retryable] = match (true) {
            $exception instanceof ValidationException => ['VALIDATION_FAILED', 'validation_failed', false],
            $exception instanceof AuthorizationException => ['PERMISSION_DENIED', 'permission_denied', false],
            $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => ['ENTITY_NOT_FOUND', 'entity_not_found', false],
            $exception instanceof AiProviderException => $this->providerError($exception),
            default => ['INTERNAL_ERROR', 'internal_error', true],
        };

        return [
            'correlation_id' => $correlationId,
            'error_code' => $errorCode,
            'message' => (string) trans("chat.recovery.{$messageKey}", [], $locale),
            'retryable' => $retryable,
            'title' => (string) trans('chat.recovery.title', [], $locale),
        ];
    }

    /**
     * Safe, provider-neutral error contract for the model tool loop.
     * Internal exception text and diagnostics intentionally never cross this
     * boundary.
     *
     * @return array<string, mixed>
     */
    public function forModel(Throwable $exception, string $locale, string $correlationId): array
    {
        $error = $this->map($exception, $locale, $correlationId);

        return [
            'ok' => false,
            'code' => $error['error_code'],
            'message_for_model' => $error['message'],
            'retryable' => $error['retryable'],
            'allowed_next_actions' => match ($error['error_code']) {
                'ENTITY_NOT_FOUND' => ['search', 'ask_user_for_clarification'],
                'PERMISSION_DENIED' => ['ask_user_for_clarification'],
                'VALIDATION_FAILED' => ['correct_arguments', 'ask_user_for_clarification'],
                default => $error['retryable'] ? ['retry_tool', 'ask_user_for_clarification'] : ['ask_user_for_clarification'],
            },
            'safe_details' => [],
        ];
    }

    /** @return array{string, string, bool} */
    private function providerError(AiProviderException $exception): array
    {
        return match ($exception->internalCode()) {
            'AI_AUTH_ERROR' => ['AI_AUTHENTICATION_FAILED', 'provider_authentication', false],
            'AI_BAD_REQUEST' => ['AI_INVALID_REQUEST', 'provider_validation', false],
            'AI_INVALID_RESPONSE' => ['AI_INVALID_STRUCTURED_OUTPUT', 'provider_invalid_response', true],
            'AI_RATE_LIMITED' => ['AI_RATE_LIMITED', 'provider_rate_limit', true],
            'AI_TIMEOUT' => ['AI_TIMEOUT', 'provider_timeout', true],
            default => ['AI_PROVIDER_UNAVAILABLE', 'provider_unavailable', true],
        };
    }
}
