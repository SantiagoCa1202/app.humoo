import { useTranslation } from "react-i18next";

import {
  SummaryCard,
  type SummaryMetric,
} from "@/components/patterns/summary-card";
import { InventoryStatusBadge } from "@/components/patterns/inventory-status-badge";
import {
  buildInventorySummary,
  formatInventoryCurrency,
  type InventoryItemRecord,
  type InventorySummaryMetricKey,
  type InventorySummaryRecord,
} from "@/features/inventory";

export type InventorySummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  items?: InventoryItemRecord[];
  metrics?: SummaryMetric[];
  onMetricPress?: (metric: InventorySummaryMetricKey) => void;
  summary?: InventorySummaryRecord | null;
};

export function InventorySummaryCard({
  accessibilityLabel,
  compact = false,
  items,
  metrics,
  onMetricPress,
  summary,
}: InventorySummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const resolvedSummary = summary ?? (items ? buildInventorySummary(items) : null);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          typeof resolvedSummary?.total === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.labels.items"),
                  value: resolvedSummary.total,
                }),
                label: t("inventory.labels.items"),
                onPress: onMetricPress ? () => onMetricPress("total") : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.total),
              }
            : null,
          typeof resolvedSummary?.inStock === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.status.in_stock"),
                  value: resolvedSummary.inStock,
                }),
                label: t("inventory.status.in_stock"),
                onPress: onMetricPress ? () => onMetricPress("in_stock") : undefined,
                tone: "success" as const,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.inStock),
              }
            : null,
          typeof resolvedSummary?.lowStock === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.status.low_stock"),
                  value: resolvedSummary.lowStock,
                }),
                label: t("inventory.status.low_stock"),
                onPress: onMetricPress ? () => onMetricPress("low_stock") : undefined,
                tone: resolvedSummary.lowStock > 0 ? ("warning" as const) : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.lowStock),
              }
            : null,
          typeof resolvedSummary?.outOfStock === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.status.out_of_stock"),
                  value: resolvedSummary.outOfStock,
                }),
                label: t("inventory.status.out_of_stock"),
                onPress: onMetricPress ? () => onMetricPress("out_of_stock") : undefined,
                tone: resolvedSummary.outOfStock > 0 ? ("danger" as const) : undefined,
                value: new Intl.NumberFormat(i18n.language).format(
                  resolvedSummary.outOfStock
                ),
              }
            : null,
          typeof resolvedSummary?.unknown === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.status.unknown"),
                  value: resolvedSummary.unknown,
                }),
                label: t("inventory.status.unknown"),
                onPress: onMetricPress ? () => onMetricPress("unknown") : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.unknown),
              }
            : null,
          typeof resolvedSummary?.locations === "number"
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.labels.location"),
                  value: resolvedSummary.locations,
                }),
                label: t("inventory.labels.location"),
                onPress: onMetricPress ? () => onMetricPress("locations") : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.locations),
              }
            : null,
          formatInventoryCurrency(
            resolvedSummary?.inventoryValue,
            resolvedSummary?.currency,
            i18n.language
          )
            ? {
                accessibilityLabel: t("inventory.summary.metricAccessibilityLabel", {
                  label: t("inventory.labels.inventoryValue"),
                  value: formatInventoryCurrency(
                    resolvedSummary?.inventoryValue,
                    resolvedSummary?.currency,
                    i18n.language
                  ),
                }),
                label: t("inventory.labels.inventoryValue"),
                onPress: onMetricPress ? () => onMetricPress("inventory_value") : undefined,
                value: formatInventoryCurrency(
                  resolvedSummary?.inventoryValue,
                  resolvedSummary?.currency,
                  i18n.language
                ),
              }
            : null,
        ].filter(Boolean) as SummaryMetric[];

  const trailingStatus =
    typeof resolvedSummary?.outOfStock === "number" && resolvedSummary.outOfStock > 0
      ? "out_of_stock"
      : typeof resolvedSummary?.lowStock === "number" && resolvedSummary.lowStock > 0
      ? "low_stock"
      : typeof resolvedSummary?.total === "number" && resolvedSummary.total > 0
      ? "in_stock"
      : null;

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.summary.accessibilityLabel")}
      metrics={compact ? resolvedMetrics.slice(0, 4) : resolvedMetrics}
      subtitle={
        typeof resolvedSummary?.total === "number"
          ? t("inventory.metrics.items", { count: resolvedSummary.total })
          : undefined
      }
      title={t("inventory.summary.title")}
      trailing={
        trailingStatus ? <InventoryStatusBadge showDot={false} size="sm" status={trailingStatus} /> : undefined
      }
      variant="elevated"
    />
  );
}
