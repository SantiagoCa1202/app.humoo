import { View } from "react-native";
import { useTranslation } from "react-i18next";

import {
  EntityCard,
  type EntityCardMetadataItem,
} from "@/components/patterns/entity-card";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryItemName,
  getInventoryLocation,
  getInventoryLocationName,
  getInventoryStatus,
  getInventorySupplier,
  getInventorySupplierName,
  getInventoryThreshold,
  getInventoryUnit,
  type InventoryItemRecord,
  type InventoryStatus,
  type InventoryStockRecord,
} from "@/features/inventory";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type InventoryItemCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  item: InventoryItemRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showLocation?: boolean;
  showStatus?: boolean;
  showSupplier?: boolean;
  showThreshold?: boolean;
  status?: InventoryStatus | null;
  stock?: InventoryStockRecord | null;
  trailing?: React.ReactNode;
};

export function InventoryItemCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  item,
  onPress,
  selected = false,
  showLocation = false,
  showStatus = true,
  showSupplier = false,
  showThreshold = true,
  status,
  stock,
  trailing,
}: InventoryItemCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedStock = stock ?? item.stock ?? null;
  const resolvedStatus = status ?? getInventoryStatus(item, resolvedStock);
  const name = getInventoryItemName(item) ?? t("inventory.item.fallbackName");
  const unit = getInventoryUnit(item, resolvedStock);
  const quantityLabel =
    formatInventoryMeasurement(
      getInventoryAvailableQuantity(resolvedStock),
      unit,
      i18n.language
    ) ?? t("inventory.labels.unknownStock");
  const onHandLabel = formatInventoryMeasurement(
    resolvedStock?.onHandQuantity,
    unit,
    i18n.language
  );
  const reservedLabel = formatInventoryMeasurement(
    resolvedStock?.reservedQuantity,
    unit,
    i18n.language
  );
  const threshold = showThreshold ? getInventoryThreshold(resolvedStock) : null;
  const thresholdLabel = threshold
    ? formatInventoryMeasurement(threshold.value, unit, i18n.language)
    : null;
  const locationName = getInventoryLocationName(getInventoryLocation(item, resolvedStock));
  const supplierName = getInventorySupplierName(getInventorySupplier(item, resolvedStock));
  const metadata: EntityCardMetadataItem[] = [
    threshold && thresholdLabel
      ? {
          label: t(threshold.labelKey),
          value: thresholdLabel,
        }
      : null,
    !compact && onHandLabel && onHandLabel !== quantityLabel
      ? {
          label: t("inventory.labels.onHand"),
          value: onHandLabel,
        }
      : null,
    !compact && reservedLabel
      ? {
          label: t("inventory.labels.reserved"),
          value: reservedLabel,
        }
      : null,
    showLocation && locationName
      ? {
          label: t("inventory.labels.location"),
          value: locationName,
        }
      : null,
    showSupplier && supplierName
      ? {
          label: t("inventory.labels.supplier"),
          value: supplierName,
        }
      : null,
    !compact && item.sku?.trim()
      ? {
          label: t("inventory.labels.sku"),
          value: item.sku.trim(),
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.item.cardAccessibilityLabel", {
          name,
          quantity: quantityLabel,
          status: t(getStatusTranslationKey(resolvedStatus, "inventory")),
        })
      }
      disabled={disabled}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      status={showStatus ? resolvedStatus : undefined}
      statusNamespace="inventory"
      subtitle={
        <View style={{ gap: theme.spacing[1] }}>
          <Text selectable tone="secondary" variant="bodySmall">
            {quantityLabel}
          </Text>
          {!compact && (locationName || supplierName) ? (
            <Text selectable tone="muted" variant="caption">
              {[locationName, supplierName].filter(Boolean).join(" - ")}
            </Text>
          ) : null}
        </View>
      }
      title={name}
      trailing={
        actions || trailing ? (
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            {trailing}
            {actions}
          </View>
        ) : undefined
      }
      variant={compact ? "muted" : "default"}
    />
  );
}
