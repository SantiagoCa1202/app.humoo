<?php

namespace App\AI\Errors;

use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiRuntimeException;
use App\AI\Tools\ToolObservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ErrorResponseMapper
{
    /** @return array<string, mixed> */
    public function map(Throwable $exception, string $locale, string $correlationId, array $context = []): array
    {
        [$errorCode, $messageKey, $retryable] = match (true) {
            $exception instanceof ValidationException && array_key_exists('missing_operations', $exception->errors()) => ['OBJECTIVE_INCOMPLETE', 'objective_incomplete', false],
            $exception instanceof ValidationException && array_key_exists('termination_state', $exception->errors()) => ['INVALID_TERMINATION_STATE', 'validation_failed', false],
            $exception instanceof ValidationException => ['VALIDATION_FAILED', 'validation_failed', false],
            $exception instanceof AuthorizationException => ['PERMISSION_DENIED', 'permission_denied', false],
            $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => ['ENTITY_NOT_FOUND', 'entity_not_found', false],
            $exception instanceof AiProviderException => $this->providerError($exception),
            $exception instanceof AiRuntimeException => [
                $exception->internalCode(),
                $exception->publicMessageKey(),
                $exception->retryable(),
            ],
            default => ['INTERNAL_ERROR', 'internal_error', true],
        };

        $category = match ($errorCode) {
            'AI_RATE_LIMITED', 'AI_TIMEOUT', 'AI_CONVERSATION_LOCKED', 'AI_PROVIDER_UNAVAILABLE', 'AI_NETWORK_ERROR', 'RUN_DEADLINE_EXCEEDED', 'TOOL_TIMEOUT', 'WORKFLOW_RETRY_EXHAUSTED' => 'transient',
            'AI_QUOTA_EXHAUSTED', 'AI_BILLING_LIMIT_REACHED' => 'quota',
            'AI_AUTHENTICATION_FAILED', 'AI_AUTHORIZATION_FAILED' => 'configuration',
            'PERMISSION_DENIED' => 'permission',
            'CONFLICT', 'RECOVERY_STATE_UNCERTAIN', 'AI_PROTOCOL_STATE_CORRUPTED' => 'conflict',
            'VALIDATION_FAILED', 'ENTITY_NOT_FOUND', 'AMBIGUOUS_ENTITY', 'OBJECTIVE_INCOMPLETE', 'INVALID_TERMINATION_STATE' => 'validation',
            default => 'unknown',
        };
        $nextActions = match ($errorCode) {
            'AI_RATE_LIMITED', 'AI_TIMEOUT', 'AI_CONVERSATION_LOCKED', 'AI_PROVIDER_UNAVAILABLE', 'AI_NETWORK_ERROR', 'RUN_DEADLINE_EXCEEDED', 'TOOL_TIMEOUT', 'WORKFLOW_RETRY_EXHAUSTED' => ['retry', 'resume'],
            'AI_QUOTA_EXHAUSTED', 'AI_BILLING_LIMIT_REACHED', 'AI_AUTHENTICATION_FAILED', 'AI_AUTHORIZATION_FAILED' => ['contact_admin'],
            'RECOVERY_STATE_UNCERTAIN' => ['review'],
            'AI_PROTOCOL_STATE_CORRUPTED' => ['review'],
            'OBJECTIVE_INCOMPLETE' => ['resume', 'review'],
            default => $retryable ? ['retry'] : [],
        };
        $retryAfter = $exception instanceof AiProviderException
            ? data_get($exception->metadata(), 'retry_after_seconds')
            : null;

        return [
            'correlation_id' => $correlationId,
            'error_code' => $errorCode,
            'message' => (string) trans("chat.recovery.{$messageKey}", [], $locale),
            'public_message' => (string) trans("chat.recovery.{$messageKey}", [], $locale),
            'public_message_key' => "chat.recovery.{$messageKey}",
            'category' => $category,
            'retryable' => $retryable,
            'retry_after_seconds' => $retryAfter,
            'ai_run_id' => $context['ai_run_id'] ?? null,
            'objective_id' => $context['objective_id'] ?? null,
            'preserved_progress' => (bool) ($context['preserved_progress'] ?? filled($context['objective_id'] ?? null)),
            'completed_count' => (int) ($context['completed_count'] ?? 0),
            'pending_count' => (int) ($context['pending_count'] ?? 0),
            'next_actions' => $nextActions,
            'safe_details' => is_array($context['safe_details'] ?? null) ? $context['safe_details'] : [],
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
        $safeDetails = [];
        if ($exception instanceof ValidationException) {
            $validationErrors = collect($exception->errors())
                ->map(fn (mixed $messages): array => collect((array) $messages)
                    ->map(fn (mixed $message): string => trim((string) $message))
                    ->filter()
                    ->values()
                    ->all())
                ->filter(fn (array $messages): bool => $messages !== [])
                ->all();
            $safeDetails = [
                'validation_errors' => $validationErrors,
                'missing_fields' => array_keys($validationErrors),
            ];
            if ($error['error_code'] === 'INVALID_TERMINATION_STATE') {
                $safeDetails['actual_state'] = data_get($validationErrors, 'actual_state.0');
                $safeDetails['allowed_next_actions'] = array_values(array_filter(
                    (array) ($validationErrors['allowed_next_actions'] ?? []),
                    fn (mixed $action): bool => is_string($action) && $action !== '',
                ));
            }
        }

        $recoverable = $error['error_code'] === 'VALIDATION_FAILED' || $error['retryable'];
        $allowedNextActions = match ($error['error_code']) {
            'ENTITY_NOT_FOUND' => ['search', 'ask_user_for_clarification'],
            'PERMISSION_DENIED' => ['ask_user_for_clarification'],
            'INVALID_TERMINATION_STATE' => $safeDetails['allowed_next_actions'] ?? ['correct_arguments'],
            'VALIDATION_FAILED' => ['correct_arguments', 'ask_user_for_clarification'],
            default => $error['retryable'] ? ['retry_tool', 'ask_user_for_clarification'] : ['ask_user_for_clarification'],
        };

        return ToolObservation::make(
            false,
            $error['error_code'],
            $error['message'],
            $safeDetails,
            [
                'not_found' => $error['error_code'] === 'ENTITY_NOT_FOUND',
                'validation_failed' => $error['error_code'] === 'VALIDATION_FAILED',
                'permission_denied' => $error['error_code'] === 'PERMISSION_DENIED',
                'recoverable' => $recoverable,
            ],
            $allowedNextActions,
        );
    }

    /** @return array{string, string, bool} */
    private function providerError(AiProviderException $exception): array
    {
        return match ($exception->internalCode()) {
            'AI_AUTH_ERROR', 'AI_AUTHENTICATION_FAILED' => ['AI_AUTHENTICATION_FAILED', 'provider_authentication', false],
            'AI_AUTHORIZATION_FAILED' => ['AI_AUTHORIZATION_FAILED', 'provider_authorization', false],
            'AI_BAD_REQUEST' => ['AI_INVALID_REQUEST', 'provider_validation', false],
            'AI_CONVERSATION_LOCKED' => ['AI_CONVERSATION_LOCKED', 'provider_unavailable', true],
            'AI_INVALID_RESPONSE' => ['AI_INVALID_STRUCTURED_OUTPUT', 'provider_invalid_response', true],
            'AI_PROTOCOL_STATE_CORRUPTED' => ['AI_PROTOCOL_STATE_CORRUPTED', 'provider_protocol_state', false],
            'AI_QUOTA_EXHAUSTED', 'AI_BILLING_LIMIT_REACHED' => ['AI_QUOTA_EXHAUSTED', 'provider_quota', false],
            'AI_RATE_LIMITED' => ['AI_RATE_LIMITED', 'provider_rate_limit', true],
            'AI_TIMEOUT' => ['AI_TIMEOUT', 'provider_timeout', true],
            'AI_NETWORK_ERROR' => ['AI_NETWORK_ERROR', 'provider_unavailable', true],
            default => ['AI_PROVIDER_UNAVAILABLE', 'provider_unavailable', true],
        };
    }
}
