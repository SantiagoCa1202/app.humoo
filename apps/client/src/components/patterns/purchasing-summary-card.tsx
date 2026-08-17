import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import {
  buildPurchasingSummary,
  formatPurchasingCurrency,
  type PurchaseOrderRecord,
  type PurchasingSummaryMetricKey,
  type PurchasingSummaryRecord,
} from "@/features/purchasing";

export type PurchasingSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  onMetricPress?: (metric: PurchasingSummaryMetricKey) => void;
  purchaseOrders?: PurchaseOrderRecord[];
  summary?: PurchasingSummaryRecord | null;
};

export function PurchasingSummaryCard({
  accessibilityLabel,
  compact = false,
  onMetricPress,
  purchaseOrders,
  summary,
}: PurchasingSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const resolvedSummary = summary ?? (purchaseOrders ? buildPurchasingSummary(purchaseOrders) : null);
  const metrics: SummaryMetric[] = [
    typeof resolvedSummary?.purchaseOrders === "number"
      ? {
          accessibilityLabel: t("purchasing.summary.metricAccessibilityLabel", {
            label: t("purchasing.labels.purchaseOrders"),
            value: resolvedSummary.purchaseOrders,
          }),
          label: t("purchasing.labels.purchaseOrders"),
          onPress: onMetricPress ? () => onMetricPress("purchase_orders") : undefined,
          value: t("purchasing.metrics.purchaseOrders", {
            count: resolvedSummary.purchaseOrders,
          }),
        }
      : null,
    typeof resolvedSummary?.pendingApproval === "number"
      ? {
          label: t("purchasing.purchaseOrders.status.pending_approval"),
          onPress: onMetricPress ? () => onMetricPress("pending_approval") : undefined,
          tone: resolvedSummary.pendingApproval > 0 ? ("warning" as const) : undefined,
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.pendingApproval),
        }
      : null,
    typeof resolvedSummary?.approved === "number"
      ? {
          label: t("purchasing.purchaseOrders.status.approved"),
          onPress: onMetricPress ? () => onMetricPress("approved") : undefined,
          tone: "primary" as const,
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.approved),
        }
      : null,
    typeof resolvedSummary?.partiallyReceived === "number"
      ? {
          label: t("purchasing.purchaseOrders.status.partially_received"),
          onPress: onMetricPress ? () => onMetricPress("partially_received") : undefined,
          tone:
            resolvedSummary.partiallyReceived > 0 ? ("warning" as const) : undefined,
          value: new Intl.NumberFormat(i18n.language).format(
            resolvedSummary.partiallyReceived
          ),
        }
      : null,
    typeof resolvedSummary?.received === "number"
      ? {
          label: t("purchasing.purchaseOrders.status.received"),
          onPress: onMetricPress ? () => onMetricPress("received") : undefined,
          tone: "success" as const,
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.received),
        }
      : null,
    typeof resolvedSummary?.overdueDeliveries === "number"
      ? {
          label: t("purchasing.labels.overdueDeliveries"),
          onPress: onMetricPress ? () => onMetricPress("overdue_deliveries") : undefined,
          tone:
            resolvedSummary.overdueDeliveries > 0 ? ("danger" as const) : undefined,
          value: new Intl.NumberFormat(i18n.language).format(
            resolvedSummary.overdueDeliveries
          ),
        }
      : null,
    formatPurchasingCurrency(
      resolvedSummary?.totalOrderedValue,
      resolvedSummary?.currency,
      i18n.language
    )
      ? {
          label: t("purchasing.labels.totalOrderedValue"),
          onPress: onMetricPress ? () => onMetricPress("total_ordered_value") : undefined,
          value:
            formatPurchasingCurrency(
              resolvedSummary?.totalOrderedValue,
              resolvedSummary?.currency,
              i18n.language
            ) ?? "",
        }
      : null,
    formatPurchasingCurrency(
      resolvedSummary?.outstandingValue,
      resolvedSummary?.currency,
      i18n.language
    )
      ? {
          label: t("purchasing.labels.outstandingValue"),
          onPress: onMetricPress ? () => onMetricPress("outstanding_value") : undefined,
          value:
            formatPurchasingCurrency(
              resolvedSummary?.outstandingValue,
              resolvedSummary?.currency,
              i18n.language
            ) ?? "",
        }
      : null,
  ].filter(Boolean) as SummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("purchasing.summary.accessibilityLabel")}
      metrics={compact ? metrics.slice(0, 4) : metrics}
      subtitle={
        typeof resolvedSummary?.purchaseOrders === "number"
          ? t("purchasing.metrics.purchaseOrders", {
              count: resolvedSummary.purchaseOrders,
            })
          : undefined
      }
      title={t("purchasing.summary.title")}
      variant="elevated"
    />
  );
}
