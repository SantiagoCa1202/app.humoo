<?php

namespace App\AI\Orchestration;

use App\AI\Tools\ToolObservation;
use Illuminate\Support\Facades\Log;

final class ToolLoopRetryBudget
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, true> */
    private array $failedFingerprints = [];

    /** @var array<string, true> */
    private array $seenFingerprints = [];

    private int $duplicateCallsAvoided = 0;

    /** @var array<string, string> */
    private array $blockedActions = [];

    private ?string $lastRetryReason = null;

    private ?string $planValidationErrorCode = null;

    /** @var array{steps: array<int, string>, completion_steps: array<int, string>}|null */
    private ?array $failedPlanManifest = null;

    private int $structuralPlanRepairs;

    private int $toolArgumentRepairs;

    private ?string $correlationId;

    public function __construct(
        int $structuralPlanRepairs,
        int $toolArgumentRepairs,
        ?string $correlationId = null,
    ) {
        $this->structuralPlanRepairs = $structuralPlanRepairs;
        $this->toolArgumentRepairs = $toolArgumentRepairs;
        $this->correlationId = $correlationId;
    }

    /**
     * @param  array<string, mixed>|null  $arguments
     * @param  array<string, mixed>  $observation
     * @return array<string, mixed>
     */
    public function apply(?string $actionKey, ?array $arguments, array $observation): array
    {
        $fingerprint = hash('sha256', (string) $actionKey.'|'.json_encode($arguments ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->seenFingerprints[$fingerprint] = true;
        if (($observation['ok'] ?? false) === true) {
            return $observation;
        }

        $code = (string) ($observation['code'] ?? data_get($observation, 'error.code', 'TOOL_FAILED'));
        if (in_array($code, ['REPEATED_TOOL_CALL', 'RETRY_BUDGET_EXHAUSTED'], true)) {
            return $observation;
        }
        if ($code === 'PERMISSION_DENIED') {
            if ($actionKey !== null) {
                $this->blockedActions[$actionKey] = 'permission_denied';
            }

            return $this->stop($observation, 'permission_denied');
        }

        if (isset($this->failedFingerprints[$fingerprint])) {
            return $this->stop($observation, 'repeated_identical_call', 'REPEATED_TOOL_CALL');
        }
        $this->failedFingerprints[$fingerprint] = true;

        $reason = in_array($actionKey, ['execution_plans.create', 'execution_plans.revise'], true)
            && $code === 'VALIDATION_FAILED'
                ? 'structural_plan_repair'
                : 'tool_argument_repair';
        $this->lastRetryReason = $reason;
        if ($reason === 'structural_plan_repair') {
            $this->captureFailedPlanManifest($arguments ?? []);
            $this->planValidationErrorCode = 'STRUCTURAL_PLAN_ERROR';
            $observation['code'] = 'STRUCTURAL_PLAN_ERROR';
            $observation['error']['code'] = 'STRUCTURAL_PLAN_ERROR';
            $observation['safe_details']['plan_repair'] = [
                'preserve_all_operations' => true,
                'required_step_keys' => $this->failedPlanManifest['steps'] ?? [],
                'required_completion_step_keys' => $this->failedPlanManifest['completion_steps'] ?? [],
            ];
            $observation['allowed_next_actions'] = ['repair_same_complete_plan'];
            $observation['meta']['allowed_next_actions'] = $observation['allowed_next_actions'];
        }
        $limit = $reason === 'structural_plan_repair'
            ? max(0, $this->structuralPlanRepairs)
            : max(0, $this->toolArgumentRepairs);
        $counterKey = $reason.'|'.($actionKey ?? 'unknown');
        $attempt = ($this->counts[$counterKey] ?? 0) + 1;
        $this->counts[$counterKey] = $attempt;

        Log::info('ai.tool_loop.retry_budget', [
            'action_key' => $actionKey,
            'attempt' => $attempt,
            'correlation_id' => $this->correlationId,
            'limit' => $limit,
            'retry_reason' => $reason,
        ]);

        if ($attempt <= $limit) {
            return $observation;
        }

        if ($actionKey !== null) {
            $this->blockedActions[$actionKey] = $reason.'_exhausted';
        }

        return $this->stop($observation, $reason.'_exhausted', 'RETRY_BUDGET_EXHAUSTED');
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed>|null */
    public function guard(string $actionKey, array $arguments): ?array
    {
        if (isset($this->blockedActions[$actionKey])) {
            return $this->blockedObservation('RETRY_BUDGET_EXHAUSTED', $this->blockedActions[$actionKey]);
        }

        if ($actionKey === 'execution_plans.create' && $this->failedPlanManifest !== null) {
            $missing = $this->missingPlanOperations($arguments);
            if ($missing['steps'] !== [] || $missing['completion_steps'] !== []) {
                return ToolObservation::make(
                    false,
                    'PLAN_OPERATIONS_DROPPED',
                    'Repair the same complete execution plan. Do not drop operations while correcting its structure.',
                    [
                        'missing_step_keys' => $missing['steps'],
                        'missing_completion_step_keys' => $missing['completion_steps'],
                        'preserve_all_operations' => true,
                    ],
                    ['recoverable' => true, 'validation_failed' => true],
                    ['repair_same_complete_plan'],
                );
            }
        }

        $fingerprint = hash('sha256', $actionKey.'|'.json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (isset($this->seenFingerprints[$fingerprint])) {
            $this->duplicateCallsAvoided++;
            return $this->blockedObservation('REPEATED_TOOL_CALL', 'repeated_identical_call');
        }

        return null;
    }

    /** @param array<string, mixed> $arguments */
    private function captureFailedPlanManifest(array $arguments): void
    {
        if ($this->failedPlanManifest !== null) {
            return;
        }

        $this->failedPlanManifest = [
            'steps' => $this->planStepKeys($arguments['steps'] ?? []),
            'completion_steps' => $this->planStepKeys($arguments['completion_steps'] ?? []),
        ];
    }

    /** @param array<string, mixed> $arguments @return array{steps: array<int, string>, completion_steps: array<int, string>} */
    private function missingPlanOperations(array $arguments): array
    {
        $steps = $this->planStepKeys($arguments['steps'] ?? []);
        $completionSteps = $this->planStepKeys($arguments['completion_steps'] ?? []);

        return [
            'steps' => array_values(array_diff($this->failedPlanManifest['steps'], $steps)),
            'completion_steps' => array_values(array_diff($this->failedPlanManifest['completion_steps'], $completionSteps)),
        ];
    }

    /** @return array<int, string> */
    private function planStepKeys(mixed $steps): array
    {
        return collect(is_array($steps) ? $steps : [])
            ->filter(static fn (mixed $step): bool => is_array($step) && filled($step['step_key'] ?? null))
            ->pluck('step_key')
            ->map(static fn (mixed $key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, int|bool|string|null> */
    public function metrics(): array
    {
        $repairs = array_sum($this->counts);

        return [
            'first_attempt_valid' => $repairs === 0,
            'duplicate_calls_avoided' => $this->duplicateCallsAvoided,
            'plan_validation_error_code' => $this->planValidationErrorCode,
            'retry_count' => $repairs,
            'retry_reason' => $this->lastRetryReason,
            'structural_plan_retry_count' => array_sum(array_filter(
                $this->counts,
                fn (string $key): bool => str_starts_with($key, 'structural_plan_repair|'),
                ARRAY_FILTER_USE_KEY,
            )),
            'tool_argument_retry_count' => array_sum(array_filter(
                $this->counts,
                fn (string $key): bool => str_starts_with($key, 'tool_argument_repair|'),
                ARRAY_FILTER_USE_KEY,
            )),
        ];
    }

    /** @param array<string, mixed> $observation @return array<string, mixed> */
    private function stop(array $observation, string $reason, ?string $code = null): array
    {
        $observation['retryable'] = false;
        $observation['allowed_next_actions'] = ['ask_user_for_clarification', 'respond_to_user'];
        $observation['signals']['recoverable'] = false;
        $observation['meta']['allowed_next_actions'] = $observation['allowed_next_actions'];
        $observation['meta']['retry_stop_reason'] = $reason;
        if ($code !== null) {
            $observation['code'] = $code;
            $observation['error']['code'] = $code;
        }

        return $observation;
    }

    /** @return array<string, mixed> */
    private function blockedObservation(string $code, string $reason): array
    {
        return ToolObservation::make(
            false,
            $code,
            'The retry budget is exhausted. Do not call this tool again in this run; clarify or end the turn.',
            ['retry_stop_reason' => $reason],
            ['recoverable' => false],
            ['ask_user_for_clarification', 'respond_to_user'],
        );
    }
}
