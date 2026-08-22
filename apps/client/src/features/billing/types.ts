export type EntitlementRecord = {
  config: Record<string, unknown> | null;
  description: string | null;
  enabled: boolean;
  key: string;
  limitValue: number | null;
  module: string | null;
  name: string;
  periodEnd: string | null;
  periodStart: string | null;
  resetPeriod: string;
  type: string;
  unit: string | null;
  usage: number | null;
};

export type PlanRecord = {
  currency: string;
  description: string | null;
  features: EntitlementRecord[];
  id: string;
  key: string;
  name: string;
  priceMonthly: number;
  priceYearly: number | null;
  trialDays: number;
};

export type SubscriptionRecord = {
  billingInterval: "month" | "year" | null;
  cancelAt: string | null;
  cancelAtPeriodEnd: boolean;
  currency: string;
  currentPeriodEnd: string | null;
  currentPeriodStart: string | null;
  endsAt: string | null;
  id: string;
  provider: string | null;
  startsAt: string | null;
  status: string;
  trialEndsAt: string | null;
  providerSyncedAt: string | null;
};

export type BillingSnapshot = {
  billing: {
    checkoutAvailable: boolean;
    portalAvailable: boolean;
    provider: string | null;
    providerConfigured: boolean;
  };
  entitlements: EntitlementRecord[];
  plan: PlanRecord | null;
  subscription: SubscriptionRecord | null;
};

export type InvoiceRecord = {
  amountDue: number;
  amountPaid: number;
  currency: string;
  dueAt: string | null;
  hostedInvoiceUrl: string | null;
  id: string;
  invoiceNumber: string | null;
  issuedAt: string | null;
  paidAt: string | null;
  provider: string;
  status: string;
  subtotal: number;
  taxAmount: number;
  total: number;
};

export type InvoicesPage = {
  data: InvoiceRecord[];
  nextCursor: string | null;
  path: string;
  perPage: number;
};
