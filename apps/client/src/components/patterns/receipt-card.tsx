import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { Badge } from "@/components/primitives/badge";
import {
  formatPurchasingDateLabel,
  getPurchaseOrderReference,
  getPurchaseOrderSupplierName,
  getReceiptReference,
  type PurchaseOrderRecord,
  type ReceiptRecord,
  type SupplierRecord,
} from "@/features/purchasing";
import { getInventoryLocationName } from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ReceiptCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  onPress?: () => void | Promise<void>;
  purchaseOrder?: PurchaseOrderRecord | null;
  receipt: ReceiptRecord;
  selected?: boolean;
  supplier?: SupplierRecord | null;
};

function getReceiptStatusTranslationKey(status?: string | null) {
  if (status === "draft") return "purchasing.receipts.status.draft";
  if (status === "receiving") return "purchasing.receipts.status.receiving";
  if (status === "completed") return "purchasing.receipts.status.completed";
  if (status === "cancelled") return "purchasing.receipts.status.cancelled";
  return null;
}

export function ReceiptCard({
  accessibilityLabel,
  actions,
  compact = false,
  onPress,
  purchaseOrder,
  receipt,
  selected = false,
  supplier,
}: ReceiptCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedPurchaseOrder = purchaseOrder ?? receipt.purchaseOrder ?? null;
  const reference = getReceiptReference(receipt) ?? t("purchasing.receipts.fallbackReference");
  const poReference = getPurchaseOrderReference(resolvedPurchaseOrder);
  const supplierName = getPurchaseOrderSupplierName(resolvedPurchaseOrder, supplier);
  const receivedAt = formatPurchasingDateLabel(receipt.receivedAt, i18n.language);
  const locationName = getInventoryLocationName(receipt.inventoryLocation);
  const itemCount = receipt.itemCount ?? receipt.items?.length ?? null;
  const statusKey = getReceiptStatusTranslationKey(receipt.status);
  const metadata: EntityCardMetadataItem[] = [];

  if (poReference) metadata.push({ label: t("purchasing.receipts.labels.purchaseOrder"), value: poReference });
  if (receivedAt) metadata.push({ label: t("purchasing.receipts.labels.receivedAt"), value: receivedAt });
  if (!compact && receipt.receivedBy?.name?.trim()) metadata.push({ label: t("purchasing.receipts.labels.receivedBy"), value: receipt.receivedBy.name.trim() });
  if (typeof itemCount === "number") metadata.push({ label: t("purchasing.receipts.labels.items"), value: t("purchasing.receipts.itemCount", { count: itemCount }) });
  if (!compact && locationName) metadata.push({ label: t("purchasing.receipts.labels.location"), value: locationName });

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("purchasing.receipts.cardAccessibilityLabel", { reference })}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={supplierName ?? undefined}
      title={reference}
      trailing={statusKey || actions ? (
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {actions}
          {statusKey ? <Badge label={t(statusKey)} size="sm" variant={receipt.status === "cancelled" ? "danger" : "neutral"} /> : null}
        </View>
      ) : undefined}
      variant={compact ? "muted" : "default"}
    />
  );
}
