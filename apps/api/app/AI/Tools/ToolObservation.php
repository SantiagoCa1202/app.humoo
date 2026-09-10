<?php

namespace App\AI\Tools;

/**
 * Canonical, provider-neutral observation returned to the model after every
 * tool call.
 */
final class ToolObservation
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $signals
     * @param  array<int, string>  $allowedNextActions
     * @return array<string, mixed>
     */
    public static function make(
        bool $ok,
        ?string $code,
        string $message,
        array $data = [],
        array $signals = [],
        array $allowedNextActions = [],
    ): array {
        $normalizedSignals = [
            'requires_confirmation' => false,
            'ambiguous' => false,
            'partial' => false,
            'has_more' => false,
            'conflict' => false,
            'not_found' => false,
            'validation_failed' => false,
            'permission_denied' => false,
            'recoverable' => false,
            ...$signals,
        ];

        $error = $ok ? null : [
            'code' => $code ?? 'TOOL_FAILED',
            'message' => $message,
        ];

        return [
            'ok' => $ok,
            'data' => $data,
            'error' => $error,
            'signals' => $normalizedSignals,
            'meta' => [
                'message_for_model' => $message,
                'allowed_next_actions' => array_values(array_unique($allowedNextActions)),
            ],
        ];
    }
}
