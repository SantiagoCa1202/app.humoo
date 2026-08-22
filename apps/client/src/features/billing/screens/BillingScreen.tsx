import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppShell } from "@/components/patterns/AppShell";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppText } from "@/components/primitives/AppText";
import { useBilling, useBillingInvoices, useBillingPlans } from "@/features/billing";
import { useAppTheme } from "@/theme/ThemeProvider";

function formatDate(value: string | null, language: string): string {
  if (!value) {
    return "-";
  }

  return new Intl.DateTimeFormat(language, { dateStyle: "medium" }).format(new Date(value));
}

export default function BillingScreen() {
  const { i18n, t } = useTranslation("app");
  const { theme } = useAppTheme();
  const billingQuery = useBilling();
  const plansQuery = useBillingPlans();
  const invoicesQuery = useBillingInvoices();
  const invoices = useMemo(
    () => invoicesQuery.data?.pages.flatMap((page) => page.data) ?? [],
    [invoicesQuery.data],
  );

  return (
    <AppShell title={t("billingTitle")} subtitle={t("billingSubtitle")}>
      <View style={{ gap: theme.spacing[4] }}>
        {billingQuery.isLoading ? <StateBlock title={t("billingLoading")} tone="loading" /> : null}
        {billingQuery.error ? (
          <StateBlock
            actionLabel={t("billingRetry")}
            onAction={() => void billingQuery.refetch()}
            title={t("billingError")}
            tone="error"
          />
        ) : null}
        {billingQuery.data ? (
          <>
            <SectionCard description={billingQuery.data.plan?.description ?? t("billingNoPlanDescription")} title={t("billingCurrentPlanTitle")}>
              <AppText variant="hero">{billingQuery.data.plan?.name ?? t("billingNoPlan")}</AppText>
              <AppText muted>
                {t("billingStatus", { status: billingQuery.data.subscription?.status ?? t("billingUnavailable") })}
              </AppText>
              <AppText muted>
                {t("billingPeriod", {
                  end: formatDate(billingQuery.data.subscription?.currentPeriodEnd ?? null, i18n.language),
                  start: formatDate(billingQuery.data.subscription?.currentPeriodStart ?? null, i18n.language),
                })}
              </AppText>
              {!billingQuery.data.billing.providerConfigured ? (
                <AppText style={{ color: theme.colors.status.warning }}>
                  {t("billingProviderUnavailable")}
                </AppText>
              ) : null}
            </SectionCard>
            <SectionCard description={t("billingEntitlementsDescription")} title={t("billingEntitlementsTitle")}>
              {billingQuery.data.entitlements.length ? (
                billingQuery.data.entitlements.map((entitlement) => (
                  <ListItemCard
                    key={entitlement.key}
                    meta={[
                      entitlement.limitValue === null
                        ? t("billingUnlimited")
                        : t("billingUsage", {
                            limit: entitlement.limitValue,
                            usage: entitlement.usage ?? 0,
                          }),
                    ]}
                    title={entitlement.name}
                  >
                    <AppText muted variant="caption">
                      {entitlement.description}
                    </AppText>
                  </ListItemCard>
                ))
              ) : (
                <StateBlock title={t("billingNoEntitlements")} tone="empty" />
              )}
            </SectionCard>
          </>
        ) : null}
        {plansQuery.data?.length ? (
          <SectionCard description={t("billingPlansDescription")} title={t("billingPlansTitle")}>
            {plansQuery.data.map((plan) => (
              <ListItemCard
                key={plan.id}
                meta={[`${plan.currency} ${plan.priceMonthly}/month`]}
                title={plan.name}
              >
                <AppText muted variant="caption">
                  {plan.description}
                </AppText>
              </ListItemCard>
            ))}
          </SectionCard>
        ) : null}
        <SectionCard description={t("billingInvoicesDescription")} title={t("billingInvoicesTitle")}>
          {invoicesQuery.isLoading ? <StateBlock title={t("billingInvoicesLoading")} tone="loading" /> : null}
          {invoicesQuery.error ? <StateBlock title={t("billingInvoicesError")} tone="error" /> : null}
          {!invoicesQuery.isLoading && !invoicesQuery.error && invoices.length === 0 ? (
            <StateBlock title={t("billingInvoicesEmpty")} tone="empty" />
          ) : null}
          {invoices.map((invoice) => (
            <ListItemCard
              key={invoice.id}
              meta={[
                t("billingInvoiceStatus", { status: invoice.status }),
                t("billingInvoiceIssued", { date: formatDate(invoice.issuedAt, i18n.language) }),
              ]}
              title={invoice.invoiceNumber ?? invoice.id}
            >
              <AppText variant="bodyMedium">
                {invoice.currency} {invoice.total.toFixed(2)}
              </AppText>
            </ListItemCard>
          ))}
        </SectionCard>
      </View>
    </AppShell>
  );
}
