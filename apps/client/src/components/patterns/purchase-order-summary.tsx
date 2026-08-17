import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import {
  buildPurchaseOrderDraftTotals,
  formatPurchasingCurrency,
  formatPurchasingDateLabel,
  getSupplierName,
  type PurchaseOrderItemEditorValues,
  type SupplierRecord,
} from "@/features/purchasing";

export type PurchaseOrderSummaryProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currency?: string | null;
  discount?: number | null;
  expectedDelivery?: string | null;
  items?: PurchaseOrderItemEditorValues[];
  itemCount?: number | null;
  shipping?: number | null;
  subtotal?: number | null;
  supplier?: SupplierRecord | null;
  tax?: number | null;
  total?: number | null;
};

export function PurchaseOrderSummary({
  accessibilityLabel,
  compact = false,
  currency,
  discount,
  expectedDelivery,
  items = [],
  itemCount,
  shipping,
  subtotal,
  supplier,
  tax,
  total,
}: PurchaseOrderSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const derivedTotals = useMemo(
    () =>
      buildPurchaseOrderDraftTotals({
        currency,
        discount,
        items,
        shipping,
        tax,
      }),
    [currency, discount, items, shipping, tax]
  );
  const resolvedSubtotal = subtotal ?? derivedTotals.subtotal;
  const resolvedTotal = total ?? derivedTotals.total;
  const resolvedItemCount = itemCount ?? items.length;
  const metrics: SummaryMetric[] = [
    {
      label: t("purchasing.labels.items"),
      value: t("purchasing.metrics.items", { count: resolvedItemCount }),
    },
    resolvedSubtotal !== null && resolvedSubtotal !== undefined
      ? {
          label: t("purchasing.labels.subtotal"),
          value:
            formatPurchasingCurrency(
              resolvedSubtotal,
              currency ?? derivedTotals.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    typeof discount === "number" && discount > 0
      ? {
          label: t("purchasing.labels.discount"),
          value:
            formatPurchasingCurrency(
              discount,
              currency ?? derivedTotals.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    typeof shipping === "number" && shipping > 0
      ? {
          label: t("purchasing.labels.shipping"),
          value:
            formatPurchasingCurrency(
              shipping,
              currency ?? derivedTotals.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    typeof tax === "number" && tax > 0
      ? {
          label: t("purchasing.labels.tax"),
          value:
            formatPurchasingCurrency(
              tax,
              currency ?? derivedTotals.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    resolvedTotal !== null && resolvedTotal !== undefined
      ? {
          label: t("purchasing.labels.total"),
          tone: "primary" as const,
          value:
            formatPurchasingCurrency(
              resolvedTotal,
              currency ?? derivedTotals.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    expectedDelivery
      ? {
          label: t("purchasing.labels.expectedDelivery"),
          value: formatPurchasingDateLabel(expectedDelivery, i18n.language) ?? expectedDelivery,
        }
      : null,
  ].filter(Boolean) as SummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("purchasing.orderSummary.accessibilityLabel")}
      metrics={compact ? metrics.slice(0, 4) : metrics}
      subtitle={getSupplierName(supplier) ?? undefined}
      title={t("purchasing.orderSummary.title")}
      variant="elevated"
    />
  );
}
