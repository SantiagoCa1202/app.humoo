import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  formatPurchasingCurrency,
  formatPurchasingDateLabel,
  formatPurchasingMeasurement,
  formatSupplierLeadTime,
  getSupplierItemLocationName,
  getSupplierItemName,
  getSupplierName,
  type SupplierItemRecord,
  type SupplierRecord,
} from "@/features/purchasing";

export type SupplierItemCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  inventoryItem?: SupplierItemRecord["inventoryItem"];
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  supplier?: SupplierRecord | null;
  supplierItem: SupplierItemRecord;
};

export function SupplierItemCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  inventoryItem,
  onPress,
  selected = false,
  supplier,
  supplierItem,
}: SupplierItemCardProps) {
  const { t, i18n } = useTranslation("common");
  const title =
    getSupplierItemName(supplierItem, inventoryItem ?? supplierItem.inventoryItem) ??
    t("purchasing.labels.supplierItem");
  const supplierName = getSupplierName(supplier ?? supplierItem.supplier);
  const subtitle = compact ? supplierName ?? undefined : supplierItem.brand?.trim() || supplierName || undefined;
  const metadata: EntityCardMetadataItem[] = [];
  const unitPrice = formatPurchasingCurrency(
    supplierItem.price,
    supplierItem.currency,
    i18n.language
  );
  const minimumQuantity = formatPurchasingMeasurement(
    supplierItem.minimumOrderQuantity,
    supplierItem.unit,
    i18n.language
  );
  const packSize = formatPurchasingMeasurement(
    supplierItem.packQuantity,
    supplierItem.packUnit,
    i18n.language
  );
  const leadTime = formatSupplierLeadTime(supplierItem.leadTimeDays, t);
  const priceUpdatedAt = formatPurchasingDateLabel(supplierItem.priceUpdatedAt, i18n.language);
  const locationName = getSupplierItemLocationName(supplierItem);

  if (supplierName) {
    metadata.push({
      label: t("purchasing.labels.supplier"),
      value: supplierName,
    });
  }

  if (supplierItem.supplierSku?.trim()) {
    metadata.push({
      label: t("purchasing.labels.supplierSku"),
      value: supplierItem.supplierSku.trim(),
    });
  }

  if (unitPrice) {
    metadata.push({
      label: t("purchasing.labels.unitPrice"),
      value: unitPrice,
    });
  }

  if (packSize) {
    metadata.push({
      label: t("purchasing.labels.packSize"),
      value: packSize,
    });
  }

  if (minimumQuantity) {
    metadata.push({
      label: t("purchasing.labels.minimumOrderQuantity"),
      value: minimumQuantity,
    });
  }

  if (!compact && leadTime) {
    metadata.push({
      label: t("purchasing.labels.leadTime"),
      value: leadTime,
    });
  }

  if (!compact && locationName) {
    metadata.push({
      label: t("purchasing.labels.location"),
      value: locationName,
    });
  }

  if (!compact && priceUpdatedAt) {
    metadata.push({
      label: t("purchasing.labels.priceUpdatedAt"),
      value: priceUpdatedAt,
    });
  }

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("purchasing.supplierItems.cardAccessibilityLabel", {
          name: title,
        })
      }
      disabled={disabled}
      eyebrow={supplierItem.preferred ? t("purchasing.supplierItems.preferred") : undefined}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={subtitle}
      title={title}
      trailing={actions}
      variant={compact ? "muted" : "default"}
    />
  );
}
