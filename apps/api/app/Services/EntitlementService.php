<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class EntitlementService
{
    public function snapshot(Workspace $workspace): array
    {
        $subscription = Subscription::query()
            ->with('plan')
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', Subscription::ACTIVE_STATUSES)
            ->orderByDesc('current_period_end')
            ->first();

        return [
            'plan' => $subscription?->plan,
            'subscription' => $subscription,
            'entitlements' => $subscription
                ? $this->forPlan($workspace->id, $subscription->plan_id)
                : [],
        ];
    }

    public function allows(
        Workspace $workspace,
        string $featureKey,
        float $requested = 1
    ): bool {
        $entitlement = collect($this->snapshot($workspace)['entitlements'])
            ->firstWhere('key', $featureKey);

        if (!$entitlement || !$entitlement['enabled']) {
            return false;
        }

        if ($entitlement['limit_value'] === null) {
            return true;
        }

        return (($entitlement['usage'] ?? 0) + $requested) <= $entitlement['limit_value'];
    }

    public function forPlan(string $workspaceId, string $planId): array
    {
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

        $usage = UsageCounter::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('feature_key', $features->pluck('key')->all())
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get()
            ->keyBy('feature_key');

        return $features->map(function ($feature) use ($usage): array {
            $counter = $usage->get($feature->key);

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
                'usage' => $counter?->usage !== null ? (float) $counter->usage : null,
                'period_start' => $counter?->period_start?->toIso8601String(),
                'period_end' => $counter?->period_end?->toIso8601String(),
                'config' => $this->decodeJsonValue($feature->config),
            ];
        })->values()->all();
    }

    private function decodeJsonValue(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        return json_decode($value, true) ?? $value;
    }
}
