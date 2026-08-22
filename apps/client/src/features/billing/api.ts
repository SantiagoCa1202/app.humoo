import { apiRequest } from "@/api/client";
import type {
  BillingSnapshot,
  EntitlementRecord,
  InvoiceRecord,
  InvoicesPage,
  PlanRecord,
  SubscriptionRecord,
} from "@/features/billing/types";

type ApiEntitlement = {
  config?: Record<string, unknown> | null;
  description?: string | null;
  enabled: boolean;
  key: string;
  limit_value?: number | null;
  module?: string | null;
  name: string;
  period_end?: string | null;
  period_start?: string | null;
  reset_period: string;
  type: string;
  unit?: string | null;
  usage?: number | null;
};

type ApiPlan = {
  currency: string;
  description?: string | null;
  features?: ApiEntitlement[];
  id: string;
  key: string;
  name: string;
  price_monthly: number;
  price_yearly?: number | null;
  trial_days: number;
};

type ApiSubscription = {
  billing_interval?: "month" | "year" | null;
  cancel_at?: string | null;
  cancel_at_period_end: boolean;
  currency: string;
  current_period_end?: string | null;
  current_period_start?: string | null;
  ends_at?: string | null;
  id: string;
  provider?: string | null;
  starts_at?: string | null;
  status: string;
  trial_ends_at?: string | null;
  provider_synced_at?: string | null;
};

type ApiBillingConfig = {
  checkout_available: boolean;
  portal_available: boolean;
  provider: string | null;
  provider_configured: boolean;
};

type ApiInvoice = {
  amount_due: number;
  amount_paid: number;
  currency: string;
  due_at?: string | null;
  hosted_invoice_url?: string | null;
  id: string;
  invoice_number?: string | null;
  issued_at?: string | null;
  paid_at?: string | null;
  provider: string;
  status: string;
  subtotal: number;
  tax_amount: number;
  total: number;
};

function mapEntitlement(value: ApiEntitlement): EntitlementRecord {
  return {
    config: value.config ?? null,
    description: value.description ?? null,
    enabled: value.enabled,
    key: value.key,
    limitValue: value.limit_value ?? null,
    module: value.module ?? null,
    name: value.name,
    periodEnd: value.period_end ?? null,
    periodStart: value.period_start ?? null,
    resetPeriod: value.reset_period,
    type: value.type,
    unit: value.unit ?? null,
    usage: value.usage ?? null,
  };
}

function mapPlan(value: ApiPlan): PlanRecord {
  return {
    currency: value.currency,
    description: value.description ?? null,
    features: (value.features ?? []).map(mapEntitlement),
    id: value.id,
    key: value.key,
    name: value.name,
    priceMonthly: value.price_monthly,
    priceYearly: value.price_yearly ?? null,
    trialDays: value.trial_days,
  };
}

function mapSubscription(value: ApiSubscription): SubscriptionRecord {
  return {
    billingInterval: value.billing_interval ?? null,
    cancelAt: value.cancel_at ?? null,
    cancelAtPeriodEnd: value.cancel_at_period_end,
    currency: value.currency,
    currentPeriodEnd: value.current_period_end ?? null,
    currentPeriodStart: value.current_period_start ?? null,
    endsAt: value.ends_at ?? null,
    id: value.id,
    provider: value.provider ?? null,
    startsAt: value.starts_at ?? null,
    status: value.status,
    trialEndsAt: value.trial_ends_at ?? null,
    providerSyncedAt: value.provider_synced_at ?? null,
  };
}

export async function getBilling(
  authToken: string,
  workspaceId: string,
): Promise<BillingSnapshot> {
  const response = await apiRequest<{
    data: {
      billing: ApiBillingConfig;
      entitlements: ApiEntitlement[];
      plan: ApiPlan | null;
      subscription: ApiSubscription | null;
    };
  }>("/billing", { authToken, workspaceId });

  return {
    billing: {
      checkoutAvailable: response.data.billing.checkout_available,
      portalAvailable: response.data.billing.portal_available,
      provider: response.data.billing.provider,
      providerConfigured: response.data.billing.provider_configured,
    },
    entitlements: response.data.entitlements.map(mapEntitlement),
    plan: response.data.plan ? mapPlan(response.data.plan) : null,
    subscription: response.data.subscription
      ? mapSubscription(response.data.subscription)
      : null,
  };
}

export async function getPlans(
  authToken: string,
  workspaceId: string,
): Promise<PlanRecord[]> {
  const response = await apiRequest<{ data: ApiPlan[] }>("/billing/plans", {
    authToken,
    workspaceId,
  });

  return response.data.map(mapPlan);
}

export async function getInvoices(
  authToken: string,
  workspaceId: string,
  cursor?: string | null,
): Promise<InvoicesPage> {
  const response = await apiRequest<{
    data: ApiInvoice[];
    next_cursor: string | null;
    path: string;
    per_page: number;
  }>("/billing/invoices", {
    authToken,
    query: { cursor: cursor ?? undefined, per_page: 25 },
    workspaceId,
  });

  return {
    data: response.data.map((invoice): InvoiceRecord => ({
      amountDue: invoice.amount_due,
      amountPaid: invoice.amount_paid,
      currency: invoice.currency,
      dueAt: invoice.due_at ?? null,
      hostedInvoiceUrl: invoice.hosted_invoice_url ?? null,
      id: invoice.id,
      invoiceNumber: invoice.invoice_number ?? null,
      issuedAt: invoice.issued_at ?? null,
      paidAt: invoice.paid_at ?? null,
      provider: invoice.provider,
      status: invoice.status,
      subtotal: invoice.subtotal,
      taxAmount: invoice.tax_amount,
      total: invoice.total,
    })),
    nextCursor: response.next_cursor,
    path: response.path,
    perPage: response.per_page,
  };
}
