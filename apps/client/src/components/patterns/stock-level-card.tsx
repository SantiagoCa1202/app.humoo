import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { InventoryStatusBadge } from "@/components/patterns/inventory-status-badge";
import {
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryItemName,
  getInventoryLocationName,
  getInventoryParLevelThreshold,
  getInventoryStatus,
  getInventoryUnit,
  type InventoryItemRecord,
  type InventoryLocationReference,
  type InventoryParLevelRecord,
  type InventoryStatus,
  type InventoryStockRecord,
} from "@/features/inventory";

export type StockLevelCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  item: InventoryItemRecord;
  location?: InventoryLocationReference | null;
  onPress?: () => void | Promise<void>;
  parLevel?: InventoryParLevelRecord | null;
  selected?: boolean;
  status?: InventoryStatus | null;
  stock?: InventoryStockRecord | null;
};

export function StockLevelCard({
  accessibilityLabel,
  compact = false,
  item,
  location,
  onPress,
  parLevel,
  selected = false,
  status,
  stock,
}: StockLevelCardProps) {
  const { t, i18n } = useTranslation("common");
  const resolvedStock = stock ?? item.stock ?? null;
  const resolvedLocation = location ?? resolvedStock?.location ?? item.location ?? parLevel?.location;
  const resolvedUnit = resolvedStock?.unit ?? parLevel?.unit ?? getInventoryUnit(item, resolvedStock);
  const resolvedStatus = status ?? getInventoryStatus(item, resolvedStock);
  const parThreshold = getInventoryParLevelThreshold(parLevel);
  const metrics: SummaryMetric[] = [
    typeof resolvedStock?.onHandQuantity === "number"
      ? {
          label: t("inventory.labels.onHand"),
          value:
            formatInventoryMeasurement(
              resolvedStock.onHandQuantity,
              resolvedUnit,
              i18n.language
            ) ?? t("inventory.labels.unknownStock"),
        }
      : null,
    typeof resolvedStock?.reservedQuantity === "number"
      ? {
          label: t("inventory.labels.reserved"),
          value:
            formatInventoryMeasurement(
              resolvedStock.reservedQuantity,
              resolvedUnit,
              i18n.language
            ) ?? t("inventory.labels.unknownStock"),
        }
      : null,
    getInventoryAvailableQuantity(resolvedStock) !== null &&
    getInventoryAvailableQuantity(resolvedStock) !== undefined
      ? {
          label: t("inventory.labels.available"),
          value:
            formatInventoryMeasurement(
              getInventoryAvailableQuantity(resolvedStock),
              resolvedUnit,
              i18n.language
            ) ?? t("inventory.labels.unknownStock"),
        }
      : null,
    parLevel?.targetQuantity !== null && parLevel?.targetQuantity !== undefined
      ? {
          label: t("inventory.labels.parLevel"),
          value:
            formatInventoryMeasurement(parLevel.targetQuantity, parLevel.unit ?? resolvedUnit, i18n.language) ??
            t("inventory.labels.unknownStock"),
        }
      : null,
    parThreshold
      ? {
          label: t(parThreshold.labelKey),
          value:
            formatInventoryMeasurement(parThreshold.value, parLevel?.unit ?? resolvedUnit, i18n.language) ??
            t("inventory.labels.unknownStock"),
        }
      : null,
  ].filter(Boolean) as SummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.stockLevel.accessibilityLabel", {
          item: getInventoryItemName(item) ?? t("inventory.item.fallbackName"),
        })
      }
      metrics={compact ? metrics.slice(0, 3) : metrics}
      onPress={onPress}
      selected={selected}
      subtitle={resolvedLocation ? getInventoryLocationName(resolvedLocation) : undefined}
      title={getInventoryItemName(item) ?? t("inventory.item.fallbackName")}
      trailing={<InventoryStatusBadge showDot={false} size="sm" status={resolvedStatus} />}
      variant="elevated"
    />
  );
}
