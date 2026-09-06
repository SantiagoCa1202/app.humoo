<?php

namespace App\AI\Objectives;

use App\AI\Tools\ToolRegistry;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiObjective;
use App\Models\AiObjectiveOperation;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AiObjectiveLifecycle
{
    public const ACTIVE_STATUSES = [
        'analyzing', 'waiting_user', 'ready_for_confirmation', 'waiting_confirmation',
        'queued', 'running', 'retrying', 'paused', 'blocked', 'verifying',
        'partial', 'needs_review',
    ];

    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    public function __construct(private ToolRegistry $toolRegistry) {}

    public function startOrResume(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        Message $sourceMessage,
        string $description,
        ?string $correlationId = null,
    ): AiObjective {
        return DB::transaction(function () use ($conversation, $correlationId, $description, $sourceMessage, $user, $workspace): AiObjective {
            $lockedConversation = Conversation::query()
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $metadata = is_array($lockedConversation->metadata) ? $lockedConversation->metadata : [];
            $activeId = trim((string) ($metadata['active_ai_objective_id'] ?? ''));
            $objective = $activeId !== ''
                ? AiObjective::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('conversation_id', $conversation->id)
                    ->where('created_by', $user->id)
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->find($activeId)
                : null;

            if ($objective) {
                $objectiveMetadata = is_array($objective->metadata_json) ? $objective->metadata_json : [];
                $objective->forceFill([
                    'last_heartbeat_at' => now(),
                    'metadata_json' => [
                        ...$objectiveMetadata,
                        'last_user_message_id' => (string) $sourceMessage->id,
                    ],
                ])->save();

                return $objective;
            }

            $objective = AiObjective::query()->create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'created_by' => $user->id,
                'source_message_id' => $sourceMessage->id,
                'description' => mb_substr(trim($description), 0, 16000),
                'revision' => 1,
                'status' => 'analyzing',
                'required_facts_json' => [],
                'resolved_facts_json' => [],
                'blockers_json' => [],
                'expected_results_json' => [],
                'verification_rules_json' => [],
                'last_heartbeat_at' => now(),
                'metadata_json' => array_filter([
                    'correlation_id' => $correlationId,
                    'last_user_message_id' => (string) $sourceMessage->id,
                ]),
            ]);
            $metadata['active_ai_objective_id'] = (string) $objective->id;
            $lockedConversation->forceFill(['metadata' => $metadata])->save();

            Log::info('objective.created', $this->trace($objective));

            return $objective;
        });
    }

    public function attachRun(AiObjective $objective, AiRun $run): void
    {
        abort_unless((string) $objective->workspace_id === (string) $run->workspace_id, 404);
        abort_unless((string) $objective->conversation_id === (string) $run->conversation_id, 404);
        $run->forceFill([
            'objective_id' => $objective->id,
            'deadline_at' => $run->deadline_at ?? now()->addSeconds(max(60, (int) config('ai.deadlines.run_seconds', 540))),
            'last_heartbeat_at' => now(),
        ])->save();
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array<int, array<string, mixed>> $completionSteps
     */
    public function prepareManifest(
        AiObjective $objective,
        AiExecutionPlan $plan,
        array $input,
        array $steps,
        array $completionSteps,
    ): AiObjective {
        return DB::transaction(function () use ($completionSteps, $input, $objective, $plan, $steps): AiObjective {
            $locked = AiObjective::query()
                ->where('workspace_id', $objective->workspace_id)
                ->where('conversation_id', $objective->conversation_id)
                ->lockForUpdate()
                ->findOrFail($objective->id);
            abort_unless((string) $plan->workspace_id === (string) $locked->workspace_id, 404);
            abort_unless((string) $plan->conversation_id === (string) $locked->conversation_id, 404);

            $operations = collect($steps)->map(fn (array $step): array => $this->operationFromStep($step, 'write'))
                ->concat(collect($completionSteps)->map(fn (array $step): array => $this->operationFromStep($step, 'verification')))
                ->values()->all();
            $operationKeys = collect($operations)->pluck('operation_key')->all();
            $previousRequired = $locked->operations()
                ->where('is_required', true)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->pluck('operation_key')->all();
            $dropped = array_values(array_diff($previousRequired, $operationKeys));
            if ($dropped !== []) {
                throw ValidationException::withMessages([
                    'operations' => ['PLAN_OPERATIONS_DROPPED'],
                    'missing_operations' => $dropped,
                ]);
            }

            $requiredFacts = $this->normalizeFacts($input['required_facts'] ?? []);
            $resolvedFacts = collect($requiredFacts)->where('status', 'resolved')->values()->all();
            $blockers = collect($requiredFacts)->reject(fn (array $fact): bool => $fact['status'] === 'resolved')->values()->all();
            $expectedResults = $this->normalizeExpectedResults($input['expected_results'] ?? [], $steps);
            $verificationRules = $this->normalizeVerificationRules($input['verification_rules'] ?? [], $completionSteps, $steps);
            $coveredResultKeys = collect($operations)
                ->flatMap(fn (array $operation): array => (array) $operation['expected_result_keys_json'])
                ->filter()
                ->unique();
            $missingExpectedResults = collect($expectedResults)
                ->filter(fn (array $result): bool => (bool) ($result['required'] ?? true))
                ->pluck('result_key')
                ->reject(fn (string $key): bool => $coveredResultKeys->contains($key))
                ->values()
                ->all();
            if ($missingExpectedResults !== []) {
                throw ValidationException::withMessages([
                    'expected_results' => ['OBJECTIVE_MANIFEST_INCOMPLETE'],
                    'missing_expected_results' => $missingExpectedResults,
                ]);
            }
            $revision = $locked->operations()->exists() ? ((int) $locked->revision) + 1 : (int) $locked->revision;
            $manifest = [
                'description' => (string) $locked->description,
                'expected_results' => $expectedResults,
                'operations' => $operations,
                'required_facts' => $requiredFacts,
                'revision' => $revision,
                'verification_rules' => $verificationRules,
            ];
            $digest = hash('sha256', json_encode($this->canonicalize($manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            foreach ($operations as $operation) {
                AiObjectiveOperation::query()->updateOrCreate([
                    'objective_id' => $locked->id,
                    'operation_key' => $operation['operation_key'],
                ], [
                    ...$operation,
                    'workspace_id' => $locked->workspace_id,
                    'status' => AiObjectiveOperation::query()
                        ->where('objective_id', $locked->id)
                        ->where('operation_key', $operation['operation_key'])
                        ->value('status') ?? 'pending',
                ]);
            }

            $operationCount = $locked->operations()->where('is_required', true)->count();
            $pendingCount = $locked->operations()
                ->where('is_required', true)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            $status = $blockers === [] ? 'ready_for_confirmation' : 'waiting_user';
            $locked->forceFill([
                'analyzed_at' => now(),
                'approval_digest' => $digest,
                'blockers_json' => $blockers,
                'blocked_count' => count($blockers),
                'expected_results_json' => $expectedResults,
                'operation_count' => $operationCount,
                'pending_count' => $pendingCount,
                'required_facts_json' => $requiredFacts,
                'resolved_facts_json' => $resolvedFacts,
                'revision' => $revision,
                'status' => $status,
                'verification_rules_json' => $verificationRules,
                'last_heartbeat_at' => now(),
            ])->save();
            $plan->forceFill([
                'objective_id' => $locked->id,
                'revision' => $revision,
            ])->save();

            Log::info('objective.manifest_prepared', [
                ...$this->trace($locked),
                'blocked_count' => count($blockers),
                'operation_count' => count($operations),
                'revision' => $revision,
            ]);

            return $locked->fresh('operations');
        });
    }

    public function linkPlanItems(AiObjective $objective, AiExecutionPlan $plan): void
    {
        abort_unless((string) $objective->workspace_id === (string) $plan->workspace_id, 404);
        abort_unless((string) $objective->conversation_id === (string) $plan->conversation_id, 404);
        abort_unless((string) $objective->id === (string) $plan->objective_id, 404);
        $operations = $objective->operations()->where('kind', 'write')->get()->keyBy('operation_key');
        foreach ($plan->items as $item) {
            $operation = $operations->get((string) $item->step_key);
            if ($operation) {
                $item->forceFill(['objective_operation_id' => $operation->id])->save();
            }
        }
    }

    public function markWaitingUser(AiObjective $objective, array $missingFields, array $remainingOperations = []): void
    {
        $blockers = collect($missingFields)->map(fn (mixed $field): array => [
            'fact_key' => trim((string) $field),
            'label' => trim((string) $field),
            'status' => 'missing',
        ])->filter(fn (array $fact): bool => $fact['fact_key'] !== '')->values()->all();
        $objective->forceFill([
            'blockers_json' => $blockers,
            'blocked_count' => count($blockers),
            'paused_at' => now(),
            'status' => 'waiting_user',
            'last_heartbeat_at' => now(),
            'metadata_json' => [
                ...(is_array($objective->metadata_json) ? $objective->metadata_json : []),
                'remaining_operations' => array_values($remainingOperations),
            ],
        ])->save();
        Log::info('objective.clarification_required', [
            ...$this->trace($objective),
            'blocked_count' => count($blockers),
        ]);
    }

    public function prepareSingleOperation(AiObjective $objective, array $tool, array $draft): AiObjective
    {
        return DB::transaction(function () use ($draft, $objective, $tool): AiObjective {
            $locked = AiObjective::query()->where('workspace_id', $objective->workspace_id)
                ->lockForUpdate()->findOrFail($objective->id);
            $operationKey = 'operation_1';
            $existing = $locked->operations()->where('operation_key', $operationKey)->first();
            if ($existing && $locked->operation_count > 1) {
                return $locked;
            }
            AiObjectiveOperation::query()->updateOrCreate([
                'objective_id' => $locked->id,
                'operation_key' => $operationKey,
            ], [
                'workspace_id' => $locked->workspace_id,
                'module' => $tool['module'] ?? 'general',
                'action_key' => $tool['key'],
                'kind' => 'write',
                'status' => 'pending',
                'is_required' => true,
                'expected_result_keys_json' => [$operationKey],
                'depends_on_json' => [],
                'input_json' => (array) ($draft['input'] ?? []),
                'bindings_json' => [],
                'retry_policy_json' => [
                    'retry_safe' => (bool) data_get($tool, 'execution.retry_safe', true),
                    'timeout_class' => data_get($tool, 'execution.timeout_class', 'standard'),
                ],
                'atomicity_policy_json' => ['scope' => data_get($tool, 'execution.atomicity', 'operation')],
            ]);
            $revision = $existing ? ((int) $locked->revision) + 1 : (int) $locked->revision;
            $manifest = [
                'description' => $locked->description,
                'operations' => [[
                    'operation_key' => $operationKey,
                    'action_key' => $tool['key'],
                    'input' => (array) ($draft['input'] ?? []),
                ]],
                'revision' => $revision,
            ];
            $locked->forceFill([
                'analyzed_at' => now(),
                'approval_digest' => hash('sha256', json_encode($this->canonicalize($manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'expected_results_json' => [[
                    'result_key' => $operationKey,
                    'label' => $tool['description'] ?? $tool['key'],
                    'required' => true,
                ]],
                'operation_count' => 1,
                'pending_count' => 1,
                'revision' => $revision,
                'status' => 'ready_for_confirmation',
                'verification_rules_json' => [[
                    'rule_key' => 'verify_'.$operationKey,
                    'operation_key' => $operationKey,
                    'required' => true,
                ]],
            ])->save();

            Log::info('objective.manifest_prepared', [
                ...$this->trace($locked),
                'blocked_count' => 0,
                'operation_count' => 1,
                'revision' => $revision,
            ]);

            return $locked->fresh('operations');
        });
    }

    public function approve(AiObjective $objective, ActionConfirmation $confirmation): void
    {
        abort_unless((string) $objective->workspace_id === (string) $confirmation->workspace_id, 404);
        $confirmationMessage = $confirmation->message()
            ->where('workspace_id', $objective->workspace_id)
            ->where('conversation_id', $objective->conversation_id)
            ->first();
        abort_unless($confirmationMessage !== null, 404);
        $locked = AiObjective::query()->where('workspace_id', $objective->workspace_id)->findOrFail($objective->id);
        if (! hash_equals((string) $locked->approval_digest, (string) $confirmation->approval_digest)
            || (int) $locked->revision !== (int) $confirmation->manifest_revision) {
            throw ValidationException::withMessages([
                'confirmation' => ['The objective changed. Review the latest manifest before confirming.'],
            ]);
        }
        $locked->forceFill([
            'confirmation_id' => $confirmation->id,
            'confirmed_at' => now(),
            'status' => 'queued',
            'last_heartbeat_at' => now(),
        ])->save();
        Log::info('objective.confirmed', $this->trace($locked));
    }

    public function syncPlan(AiExecutionPlan $plan): AiObjective|null
    {
        $plan->loadMissing('objectiveRecord.operations', 'items');
        $objective = $plan->objectiveRecord;
        if (! $objective || (string) $objective->workspace_id !== (string) $plan->workspace_id) {
            return null;
        }
        foreach ($plan->items as $item) {
            if (! $item->objective_operation_id) {
                continue;
            }
            $status = match ((string) $item->status) {
                'completed' => 'completed',
                'failed' => 'failed',
                'needs_review' => 'needs_review',
                'waiting' => 'blocked',
                'running' => 'running',
                default => 'pending',
            };
            AiObjectiveOperation::query()
                ->where('workspace_id', $plan->workspace_id)
                ->where('objective_id', $objective->id)
                ->whereKey($item->objective_operation_id)
                ->update([
                    'status' => $status,
                    'result_ref_json' => $item->result_ref_json,
                    'error_code' => $item->error_code,
                    'error_message_safe' => $item->error_message,
                    'completed_at' => $status === 'completed' ? ($item->completed_at ?? now()) : null,
                    'updated_at' => now(),
                ]);
        }

        $preferredStatus = in_array((string) $objective->status, self::TERMINAL_STATUSES, true)
            ? (string) $objective->status
            : (in_array((string) $plan->status, ['completed', 'partial', 'failed'], true) ? 'verifying' : 'running');

        return $this->refreshCounts($objective, $preferredStatus);
    }

    public function refreshCounts(AiObjective $objective, ?string $preferredStatus = null): AiObjective
    {
        $wasStarted = $objective->started_at !== null;
        $nextStatus = $preferredStatus ?? $objective->status;
        $startsExecution = in_array((string) $nextStatus, ['running', 'retrying', 'verifying'], true);
        $counts = $objective->operations()
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review_count")
            ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_count")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed', 'failed', 'cancelled') THEN 1 ELSE 0 END) AS pending_count")
            ->first();
        $objective->forceFill([
            'completed_count' => (int) ($counts?->completed_count ?? 0),
            'failed_count' => (int) ($counts?->failed_count ?? 0),
            'needs_review_count' => (int) ($counts?->needs_review_count ?? 0),
            'blocked_count' => max((int) ($objective->blocked_count ?? 0), (int) ($counts?->blocked_count ?? 0)),
            'pending_count' => (int) ($counts?->pending_count ?? 0),
            'status' => $nextStatus,
            'last_heartbeat_at' => now(),
            'started_at' => $startsExecution ? ($objective->started_at ?? now()) : $objective->started_at,
        ])->save();

        if (! $wasStarted && $objective->started_at !== null) {
            Log::info('objective.execution_started', $this->trace($objective));
        }
        Log::info('objective.operation_progressed', [
            ...$this->trace($objective),
            'completed_count' => $objective->completed_count,
            'pending_count' => $objective->pending_count,
        ]);

        return $objective->fresh();
    }

    /** @param array<string, mixed> $publicError */
    public function pause(AiObjective $objective, array $publicError): AiObjective
    {
        $objective = $this->refreshCounts($objective);
        if (in_array((string) $objective->status, self::TERMINAL_STATUSES, true)) {
            return $objective;
        }

        $objective->forceFill([
            'status' => ($publicError['category'] ?? null) === 'transient' ? 'paused' : 'needs_review',
            'error_code' => $publicError['error_code'] ?? 'INTERNAL_ERROR',
            'error_message_safe' => $publicError['message'] ?? null,
            'last_heartbeat_at' => now(),
            'paused_at' => now(),
        ])->save();
        Log::warning('objective.paused', [
            ...$this->trace($objective),
            'error_code' => $objective->error_code,
            'status' => $objective->status,
        ]);

        return $objective->fresh('operations');
    }

    public function snapshot(AiObjective $objective): array
    {
        return [
            'id' => (string) $objective->id,
            'revision' => (int) $objective->revision,
            'status' => (string) $objective->status,
            'description' => (string) $objective->description,
            'operation_count' => (int) $objective->operation_count,
            'completed_count' => (int) $objective->completed_count,
            'pending_count' => (int) $objective->pending_count,
            'blocked_count' => (int) $objective->blocked_count,
            'failed_count' => (int) $objective->failed_count,
            'needs_review_count' => (int) $objective->needs_review_count,
            'blockers' => is_array($objective->blockers_json) ? $objective->blockers_json : [],
            'expected_results' => is_array($objective->expected_results_json) ? $objective->expected_results_json : [],
            'approval_digest' => $objective->approval_digest,
            'confirmation_id' => $objective->confirmation_id,
            'error_code' => $objective->error_code,
            'preserved_progress' => true,
            'updated_at' => $objective->updated_at?->toIso8601String(),
        ];
    }

    private function operationFromStep(array $step, string $kind): array
    {
        $action = $this->toolRegistry->resolve((string) $step['action_key']);

        return [
            'operation_key' => (string) $step['step_key'],
            'module' => (string) ($action['module'] ?? 'general'),
            'action_key' => (string) $action['key'],
            'kind' => $kind,
            'is_required' => (bool) ($step['is_required'] ?? true),
            'expected_result_keys_json' => collect(is_array($step['covers_result_keys'] ?? null)
                ? $step['covers_result_keys']
                : [(string) $step['step_key']])
                ->map(fn (mixed $key): string => trim((string) $key))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'depends_on_json' => array_values((array) ($step['depends_on'] ?? [])),
            'input_json' => (array) ($step['input'] ?? []),
            'bindings_json' => array_values((array) ($step['input_bindings'] ?? [])),
            'retry_policy_json' => [
                'retry_safe' => (bool) data_get($action, 'execution.retry_safe', true),
                'timeout_class' => data_get($action, 'execution.timeout_class', 'standard'),
            ],
            'atomicity_policy_json' => [
                'scope' => data_get($action, 'execution.atomicity', 'operation'),
            ],
        ];
    }

    private function normalizeFacts(mixed $facts): array
    {
        return collect(is_array($facts) ? $facts : [])->filter(fn (mixed $fact): bool => is_array($fact))
            ->map(fn (array $fact): array => [
                'fact_key' => trim((string) ($fact['fact_key'] ?? $fact['key'] ?? '')),
                'label' => trim((string) ($fact['label'] ?? $fact['fact_key'] ?? $fact['key'] ?? '')),
                'status' => in_array(($fact['status'] ?? null), ['resolved', 'missing', 'ambiguous'], true) ? $fact['status'] : 'missing',
                'value' => ($fact['status'] ?? null) === 'resolved' ? ($fact['value'] ?? null) : null,
            ])->filter(fn (array $fact): bool => $fact['fact_key'] !== '')->unique('fact_key')->values()->all();
    }

    private function normalizeExpectedResults(mixed $results, array $steps): array
    {
        $normalized = collect(is_array($results) ? $results : [])->filter(fn (mixed $result): bool => is_array($result))
            ->map(fn (array $result): array => [
                'result_key' => trim((string) ($result['result_key'] ?? $result['key'] ?? '')),
                'label' => trim((string) ($result['label'] ?? $result['result_key'] ?? $result['key'] ?? '')),
                'required' => (bool) ($result['required'] ?? true),
            ])->filter(fn (array $result): bool => $result['result_key'] !== '')->unique('result_key')->values();
        if ($normalized->isEmpty()) {
            $normalized = collect($steps)->map(fn (array $step): array => [
                'result_key' => (string) $step['step_key'],
                'label' => (string) ($step['label'] ?? $step['action_key']),
                'required' => (bool) ($step['is_required'] ?? true),
            ]);
        }

        return $normalized->all();
    }

    private function normalizeVerificationRules(mixed $rules, array $completionSteps, array $writeSteps): array
    {
        $normalized = collect(is_array($rules) ? $rules : [])->filter(fn (mixed $rule): bool => is_array($rule))
            ->map(fn (array $rule): array => [
                'rule_key' => trim((string) ($rule['rule_key'] ?? $rule['key'] ?? '')),
                'operation_key' => trim((string) ($rule['operation_key'] ?? '')),
                'required' => (bool) ($rule['required'] ?? true),
            ])->filter(fn (array $rule): bool => $rule['rule_key'] !== '')->unique('rule_key')->values();
        if ($normalized->isEmpty()) {
            $source = $completionSteps !== [] ? $completionSteps : $writeSteps;
            $normalized = collect($source)->map(fn (array $step): array => [
                'rule_key' => 'verify_'.(string) $step['step_key'],
                'operation_key' => (string) $step['step_key'],
                'required' => (bool) ($step['is_required'] ?? true),
            ]);
        }

        return $normalized->all();
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function trace(AiObjective $objective): array
    {
        return [
            'objective_id' => (string) $objective->id,
            'conversation_id' => (string) $objective->conversation_id,
            'workspace_id' => (string) $objective->workspace_id,
            'correlation_id' => data_get($objective->metadata_json, 'correlation_id'),
        ];
    }
}
