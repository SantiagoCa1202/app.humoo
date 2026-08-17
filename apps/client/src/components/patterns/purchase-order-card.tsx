import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { PurchaseOrderStatusBadge } from "@/components/patterns/purchase-order-status-badge";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  formatPurchasingCurrency,
  formatPurchasingDateLabel,
  getPurchaseOrderDate,
  getPurchaseOrderItemCount,
  getPurchaseOrderReceivedProgressLabel,
  getPurchaseOrderReference,
  getPurchaseOrderStatusValue,
  getPurchaseOrderSupplierName,
  type PurchaseOrderRecord,
  type SupplierRecord,
} from "@/features/purchasing";
import { getInventoryLocationName } from "@/features/inventory";

export type PurchaseOrderCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  purchaseOrder: PurchaseOrderRecord;
  selected?: boolean;
  showDelivery?: boolean;
  showSupplier?: boolean;
  supplier?: SupplierRecord | null;
};

export function PurchaseOrderCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  onPress,
  purchaseOrder,
  selected = false,
  showDelivery = true,
  showSupplier = true,
  supplier,
}: PurchaseOrderCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const reference = getPurchaseOrderReference(purchaseOrder) ?? t("purchasing.labels.purchaseOrder");
  const supplierName = getPurchaseOrderSupplierName(purchaseOrder, supplier);
  const status = getPurchaseOrderStatusValue(purchaseOrder.status);
  const metadata: EntityCardMetadataItem[] = [];
  const total = formatPurchasingCurrency(purchaseOrder.total, purchaseOrder.currency, i18n.language);
  const itemCount = getPurchaseOrderItemCount(purchaseOrder);
  const orderDate = formatPurchasingDateLabel(getPurchaseOrderDate(purchaseOrder), i18n.language);
  const expectedDate = formatPurchasingDateLabel(purchaseOrder.expectedAt, i18n.language);
  const receivedProgress = getPurchaseOrderReceivedProgressLabel(purchaseOrder, t);
  const locationName = getInventoryLocationName(purchaseOrder.inventoryLocation);

  if (total) {
    metadata.push({
      label: t("purchasing.labels.total"),
      value: total,
    });
  }

  if (typeof itemCount === "number") {
    metadata.push({
      label: t("purchasing.labels.items"),
      value: t("purchasing.metrics.items", { count: itemCount }),
    });
  }

  if (!compact && orderDate) {
    metadata.push({
      label: t("purchasing.labels.orderDate"),
      value: orderDate,
    });
  }

  if (showDelivery && expectedDate) {
    metadata.push({
      label: t("purchasing.labels.expectedDelivery"),
      value: expectedDate,
    });
  }

  if (!compact && locationName) {
    metadata.push({
      label: t("purchasing.labels.location"),
      value: locationName,
    });
  }

  if (!compact && purchaseOrder.createdBy?.name?.trim()) {
    metadata.push({
      label: t("purchasing.labels.createdBy"),
      value: purchaseOrder.createdBy.name.trim(),
    });
  }

  if (!compact && receivedProgress) {
    metadata.push({
      label: t("purchasing.labels.receivedProgress"),
      value: receivedProgress,
    });
  }

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("purchasing.purchaseOrders.cardAccessibilityLabel", {
          number: reference,
          supplier: supplierName ?? "",
        })
      }
      disabled={disabled}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={showSupplier ? supplierName ?? undefined : undefined}
      title={reference}
      trailing={
        status || actions ? (
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            {actions}
            {status ? <PurchaseOrderStatusBadge showDot={false} size="sm" status={status} /> : null}
          </View>
        ) : undefined
      }
      variant={compact ? "muted" : "default"}
    />
  );
}
