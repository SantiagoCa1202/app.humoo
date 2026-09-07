<?php

namespace App\AI\Objectives;

use App\Models\AiObjective;
use Illuminate\Support\Facades\Log;

final class ObjectiveValidator
{
    public function validate(AiObjective $objective): array
    {
        $objective->load('operations', 'confirmation', 'executionPlans');
        $required = $objective->operations->where('is_required', true);
        $missing = $required->whereNotIn('status', ['completed'])->pluck('operation_key')->values()->all();
        $failed = $required->whereIn('status', ['failed', 'needs_review'])->pluck('operation_key')->values()->all();
        $blockers = is_array($objective->blockers_json) ? array_values($objective->blockers_json) : [];
        $failedInvariants = [];
        if ($required->isEmpty() && ! data_get($objective->metadata_json, 'scope_defined')) {
            $failedInvariants[] = 'required_operations_present';
        }
        if ((int) $objective->operation_count !== $required->count()) {
            $failedInvariants[] = 'required_operation_count_matches_manifest';
        }
        if ($objective->operations->contains(fn ($operation): bool => (string) $operation->workspace_id !== (string) $objective->workspace_id)) {
            $failedInvariants[] = 'operation_workspace_matches_objective';
        }
        if ($objective->executionPlans->contains(fn ($plan): bool => (string) $plan->workspace_id !== (string) $objective->workspace_id
            || (string) $plan->conversation_id !== (string) $objective->conversation_id
            || (int) $plan->revision > (int) $objective->revision)) {
            $failedInvariants[] = 'plan_scope_and_revision_match_objective';
        }

        $completedWithEvidence = $required->filter(fn ($operation): bool => $operation->status === 'completed'
            && is_array($operation->result_ref_json)
            && $operation->result_ref_json !== []);
        $missingEvidence = $required->where('status', 'completed')
            ->reject(fn ($operation): bool => $completedWithEvidence->contains('id', $operation->id))
            ->pluck('operation_key')->values()->all();
        if ($missingEvidence !== []) {
            $failedInvariants[] = 'completed_operations_have_evidence';
        }

        $operationByKey = $objective->operations->keyBy('operation_key');
        $invalidExpectedResults = collect(is_array($objective->expected_results_json) ? $objective->expected_results_json : [])
            ->filter(fn (mixed $result): bool => ! is_array($result)
                || trim((string) ($result['result_key'] ?? '')) === ''
                || trim((string) ($result['label'] ?? '')) === '')
            ->values()->all();
        if ($invalidExpectedResults !== []) {
            $failedInvariants[] = 'expected_results_are_well_formed';
        }
        $coveredResultKeys = $objective->operations
            ->flatMap(fn ($operation): array => is_array($operation->expected_result_keys_json)
                ? $operation->expected_result_keys_json
                : [])
            ->filter()
            ->unique();
        $missingExpectedResults = collect(is_array($objective->expected_results_json) ? $objective->expected_results_json : [])
            ->filter(fn (mixed $result): bool => is_array($result) && (bool) ($result['required'] ?? true))
            ->pluck('result_key')
            ->reject(fn (mixed $key): bool => $coveredResultKeys->contains((string) $key))
            ->values()
            ->all();
        if ($missingExpectedResults !== []) {
            $failedInvariants[] = 'required_expected_results_are_covered';
        }

        $failedVerificationRules = collect(is_array($objective->verification_rules_json) ? $objective->verification_rules_json : [])
            ->filter(fn (mixed $rule): bool => is_array($rule) && (bool) ($rule['required'] ?? true))
            ->filter(function (array $rule) use ($operationByKey): bool {
                $operation = $operationByKey->get((string) ($rule['operation_key'] ?? ''));

                return ! $operation || $operation->status !== 'completed';
            })->pluck('rule_key')->values()->all();
        if ($failedVerificationRules !== []) {
            $failedInvariants[] = 'required_verification_rules_passed';
        }

        if ($objective->confirmation && ((int) $objective->confirmation->manifest_revision !== (int) $objective->revision
            || ! hash_equals((string) $objective->approval_digest, (string) $objective->confirmation->approval_digest))) {
            $failedInvariants[] = 'confirmation_matches_manifest';
        }
        if ($blockers !== []) {
            $failedInvariants[] = 'required_facts_resolved';
        }
        $failedInvariants = array_values(array_unique($failedInvariants));
        $valid = $missing === [] && $failed === [] && $blockers === [] && $failedInvariants === [];
        $structuralFailures = array_values(array_diff($failedInvariants, ['required_facts_resolved', 'required_expected_results_are_covered', 'required_verification_rules_passed']));
        $canonicalStatus = $valid
            ? 'completed'
            : ($failed !== [] || $structuralFailures !== [] ? 'needs_review' : ($blockers !== [] ? 'blocked' : 'partial'));

        return [
            'valid' => $valid,
            'canonical_status' => $canonicalStatus,
            'missing_operations' => $missing,
            'failed_operations' => $failed,
            'missing_evidence' => $missingEvidence,
            'invalid_expected_results' => $invalidExpectedResults,
            'missing_expected_results' => $missingExpectedResults,
            'failed_verification_rules' => $failedVerificationRules,
            'failed_invariants' => $failedInvariants,
            'allowed_next_actions' => match ($canonicalStatus) {
                'completed' => ['respond_completed'],
                'blocked' => ['ask_user_for_clarification'],
                'needs_review' => ['review_objective', 'resume'],
                default => ['continue_with_required_tool', 'resume'],
            },
        ];
    }

    public function finalize(AiObjective $objective): array
    {
        Log::info('objective.verification_started', [
            'objective_id' => $objective->id,
            'conversation_id' => $objective->conversation_id,
            'workspace_id' => $objective->workspace_id,
        ]);
        $result = $this->validate($objective);
        $objective->forceFill([
            'status' => $result['canonical_status'],
            'finished_at' => $result['valid'] ? now() : null,
            'paused_at' => $result['valid'] ? null : now(),
            'last_heartbeat_at' => now(),
            'error_code' => $result['valid'] ? null : 'OBJECTIVE_INCOMPLETE',
            'error_message_safe' => $result['valid'] ? null : 'The objective is preserved and still has required work.',
        ])->save();
        if ($result['valid']) {
            $conversation = $objective->conversation()->where('workspace_id', $objective->workspace_id)->first();
            if ($conversation) {
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                if ((string) ($metadata['active_ai_objective_id'] ?? '') === (string) $objective->id) {
                    unset($metadata['active_ai_objective_id']);
                    $conversation->forceFill(['metadata' => $metadata])->save();
                }
            }
        }
        Log::info($result['valid'] ? 'objective.completed' : 'objective.failed', [
            'objective_id' => $objective->id,
            'conversation_id' => $objective->conversation_id,
            'workspace_id' => $objective->workspace_id,
            'missing_operation_count' => count($result['missing_operations']),
        ]);
        Log::info($result['valid'] ? 'objective.verification_passed' : 'objective.verification_failed', [
            'objective_id' => $objective->id,
            'conversation_id' => $objective->conversation_id,
            'workspace_id' => $objective->workspace_id,
            'missing_operation_count' => count($result['missing_operations']),
            'failed_invariants' => $result['failed_invariants'],
            'failed_verification_rules' => $result['failed_verification_rules'],
            'missing_expected_results' => $result['missing_expected_results'],
        ]);

        return $result;
    }
}
