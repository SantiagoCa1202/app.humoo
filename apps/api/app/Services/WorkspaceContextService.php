<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;

class WorkspaceContextService
{
    public function buildForMembership(WorkspaceMembership $membership): array
    {
        $membership->loadMissing([
            'workspace',
            'role.permissions',
        ]);

        $workspace = $membership->workspace;
        $permissions = $membership->role?->permissions
            ? $membership->role->permissions
                ->pluck('key')
                ->values()
                ->all()
            : [];

        $subscription = Subscription::query()
            ->with('plan')
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [
                'trialing',
                'active',
                'past_due',
                'paused',
            ])
            ->orderByDesc('current_period_end')
            ->first();

        return [
            'workspace' => $workspace,
            'membership' => $membership,
            'permissions' => $permissions,
            'plan' => $subscription?->plan,
            'subscription' => $subscription,
            'entitlements' => $subscription
                ? $this->resolveEntitlements(
                    $workspace->id,
                    $subscription->plan_id
                )
                : [],
        ];
    }

    private function resolveEntitlements(
        string $workspaceId,
        string $planId
    ): array {
        $features = DB::table('plan_features')
            ->join('features', 'features.id', '=', 'plan_features.feature_id')
            ->where('plan_features.plan_id', $planId)
            ->where('features.active', true)
            ->orderBy('features.module')
            ->orderBy('features.key')
            ->get([
                'features.key',
                'features.name',
                'features.description',
                'features.type',
                'features.module',
                'features.unit',
                'features.reset_period',
                'plan_features.enabled',
                'plan_features.limit_value',
                'plan_features.config',
            ]);

        $featureKeys = $features
            ->pluck('key')
            ->values()
            ->all();

        $usage = UsageCounter::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('feature_key', $featureKeys)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get()
            ->keyBy('feature_key');

        return $features
            ->map(function ($feature) use ($usage): array {
                $usageCounter = $usage->get($feature->key);

                return [
                    'key' => $feature->key,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'type' => $feature->type,
                    'module' => $feature->module,
                    'unit' => $feature->unit,
                    'reset_period' => $feature->reset_period,
                    'enabled' => (bool) $feature->enabled,
                    'limit_value' => $feature->limit_value !== null
                        ? (float) $feature->limit_value
                        : null,
                    'usage' => $usageCounter?->usage !== null
                        ? (float) $usageCounter->usage
                        : null,
                    'period_start' => $usageCounter?->period_start?->toIso8601String(),
                    'period_end' => $usageCounter?->period_end?->toIso8601String(),
                    'config' => $this->decodeJsonValue($feature->config),
                ];
            })
            ->values()
            ->all();
    }

    private function decodeJsonValue(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        return json_decode($value, true) ?? $value;
    }
}
