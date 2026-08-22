<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\EntitlementService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(
        Request $request,
        EntitlementService $entitlements
    ) {
        $workspace = app('currentWorkspace');
        $this->authorizeBillingView($request);
        $snapshot = $entitlements->snapshot($workspace);

        return response()->json([
            'data' => [
                'plan' => $this->serializePlan($snapshot['plan']),
                'subscription' => $this->serializeSubscription($snapshot['subscription']),
                'entitlements' => $snapshot['entitlements'],
                'billing' => [
                    'provider' => config('services.billing.provider'),
                    'provider_configured' => filled(config('services.billing.provider')),
                    'checkout_available' => false,
                    'portal_available' => false,
                ],
            ],
        ]);
    }

    public function plans(Request $request)
    {
        $this->authorizeBillingView($request);

        $plans = Plan::query()
            ->with('features')
            ->where('active', true)
            ->where('public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan): array => $this->serializePlan($plan))
            ->values();

        return response()->json(['data' => $plans]);
    }

    public function invoices(Request $request)
    {
        $workspace = app('currentWorkspace');
        $this->authorizeBillingView($request);
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $invoices = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => InvoiceResource::collection(collect($invoices->items())),
            'path' => $invoices->path(),
            'per_page' => $invoices->perPage(),
            'next_cursor' => $invoices->nextCursor()?->encode(),
            'next_page_url' => $invoices->nextPageUrl(),
            'prev_cursor' => $invoices->previousCursor()?->encode(),
            'prev_page_url' => $invoices->previousPageUrl(),
        ]);
    }

    private function authorizeBillingView(Request $request): void
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission($workspace->id, 'billing.view'),
            403,
            'You do not have permission to view billing.'
        );
    }

    private function serializePlan(?Plan $plan): ?array
    {
        if (!$plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_monthly' => (float) $plan->price_monthly,
            'price_yearly' => $plan->price_yearly !== null ? (float) $plan->price_yearly : null,
            'currency' => $plan->currency,
            'trial_days' => $plan->trial_days,
            'features' => $plan->relationLoaded('features')
                ? $plan->features->map(fn ($feature): array => [
                    'key' => $feature->key,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'type' => $feature->type,
                    'module' => $feature->module,
                    'unit' => $feature->unit,
                    'reset_period' => $feature->reset_period,
                    'enabled' => (bool) $feature->pivot->enabled,
                    'limit_value' => $feature->pivot->limit_value !== null
                        ? (float) $feature->pivot->limit_value
                        : null,
                    'config' => $feature->pivot->config,
                ])->values()->all()
                : [],
        ];
    }

    private function serializeSubscription(?object $subscription): ?array
    {
        if (!$subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'status' => $subscription->status,
            'billing_interval' => $subscription->billing_interval,
            'currency' => $subscription->currency,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'current_period_start' => $subscription->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'cancel_at' => $subscription->cancel_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'provider_synced_at' => $subscription->provider_synced_at?->toIso8601String(),
        ];
    }
}
