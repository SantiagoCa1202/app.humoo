import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ListCard, type ListCardItem } from "@/components/patterns/list-card";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  compareDecimalValues,
  formatDecimalCurrency,
  formatReceiptMeasurement,
  getPurchaseOrderItemRemainingQuantity,
  getReceiptItemName,
  subtractDecimalValues,
  type ReceiptItemRecord,
} from "@/features/purchasing";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ReceiptItemsListProps = { accessibilityLabel?: string; compact?: boolean; items: ReceiptItemRecord[]; title?: React.ReactNode; };

export function ReceiptItemsList({ accessibilityLabel, compact = false, items, title }: ReceiptItemsListProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const listItems: ListCardItem[] = items.map((item, index) => {
    const poItem = item.purchaseOrderItem;
    const ordered = formatReceiptMeasurement(poItem?.quantity, poItem?.unit ?? item.unit, i18n.language);
    const previouslyReceivedRaw = poItem?.quantityReceived === null || poItem?.quantityReceived === undefined ? null : subtractDecimalValues(poItem.quantityReceived, item.quantityReceived ?? 0);
    const previouslyReceived = previouslyReceivedRaw && compareDecimalValues(previouslyReceivedRaw, "0") !== -1 ? formatReceiptMeasurement(previouslyReceivedRaw, poItem?.unit ?? item.unit, i18n.language) : null;
    const receivedNow = formatReceiptMeasurement(item.quantityReceived, item.unit ?? poItem?.unit, i18n.language);
    const remainingRaw = poItem ? getPurchaseOrderItemRemainingQuantity(poItem) : null;
    const remaining = formatReceiptMeasurement(remainingRaw, poItem?.unit ?? item.unit, i18n.language);
    const unitCost = formatDecimalCurrency(item.unitCost, item.currency, i18n.language);
    const titleLabel = getReceiptItemName(item) ?? t("purchasing.receiptItems.unknownItem");
    const details = [
      ordered ? t("purchasing.receiptItems.ordered", { value: ordered }) : null,
      previouslyReceived ? t("purchasing.receiptItems.previouslyReceived", { value: previouslyReceived }) : null,
      receivedNow ? t("purchasing.receiptItems.receivedNow", { value: receivedNow }) : null,
      remaining ? t("purchasing.receiptItems.remaining", { value: remaining }) : null,
    ].filter((detail): detail is string => Boolean(detail));
    return {
      id: item.id ?? `${item.purchaseOrderItemId ?? "receipt-item"}-${index}`,
      title: titleLabel,
      subtitle: <View style={{ gap: theme.spacing[1] }}>
        {details.map((detail) => <Text key={detail} tone="muted" variant="bodySmall">{detail}</Text>)}
        {!compact && item.lotNumber?.trim() ? <Text tone="muted" variant="bodySmall">{t("purchasing.receiptItems.lot", { value: item.lotNumber.trim() })}</Text> : null}
        {!compact && item.expiresAt?.trim() ? <Text tone="muted" variant="bodySmall">{t("purchasing.receiptItems.expiration", { value: item.expiresAt.trim() })}</Text> : null}
        {!compact && unitCost ? <Text tone="muted" variant="bodySmall">{t("purchasing.receiptItems.unitCost", { value: unitCost })}</Text> : null}
      </View>,
      trailing: item.conditionStatus === "rejected" || (item.quantityRejected !== null && item.quantityRejected !== undefined && compareDecimalValues(item.quantityRejected, "0") === 1) ? <Badge label={t("purchasing.receiptItems.discrepancy")} size="sm" variant="warning" /> : undefined,
    };
  });
  return <ListCard accessibilityLabel={accessibilityLabel ?? t("purchasing.receiptItems.accessibilityLabel")} emptyContent={<Text tone="muted" variant="bodySmall">{t("purchasing.receiptItems.empty")}</Text>} items={listItems} title={title ?? t("purchasing.receiptItems.title")} variant={compact ? "muted" : "default"} />;
}
