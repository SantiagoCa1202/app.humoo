import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { InventoryStatusBadge } from "@/components/patterns/inventory-status-badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryItemName,
  getInventoryLocation,
  getInventoryLocationName,
  getInventoryStatus,
  getInventoryUnit,
  type InventoryItemRecord,
  type InventoryStatus,
  type InventoryStockRecord,
} from "@/features/inventory";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type InventoryListItemProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  item: InventoryItemRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showLocation?: boolean;
  showStatus?: boolean;
  status?: InventoryStatus | null;
  stock?: InventoryStockRecord | null;
};

export function InventoryListItem({
  accessibilityLabel,
  disabled = false,
  item,
  onPress,
  selected = false,
  showLocation = false,
  showStatus = true,
  status,
  stock,
}: InventoryListItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedStock = stock ?? item.stock ?? null;
  const resolvedStatus = status ?? getInventoryStatus(item, resolvedStock);
  const name = getInventoryItemName(item) ?? t("inventory.item.fallbackName");
  const quantityLabel =
    formatInventoryMeasurement(
      getInventoryAvailableQuantity(resolvedStock),
      getInventoryUnit(item, resolvedStock),
      i18n.language
    ) ?? t("inventory.labels.unknownStock");
  const locationName = showLocation
    ? getInventoryLocationName(getInventoryLocation(item, resolvedStock))
    : null;

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.listItem.accessibilityLabel", {
          name,
          quantity: quantityLabel,
          status: t(getStatusTranslationKey(resolvedStatus, "inventory")),
        })
      }
      disabled={disabled}
      onPress={onPress}
      padding="md"
      radius="md"
      selected={selected}
      variant="muted"
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          <Text numberOfLines={2} selectable variant="title">
            {name}
          </Text>
          <Text selectable tone="secondary" variant="bodySmall">
            {quantityLabel}
          </Text>
          {locationName ? (
            <Text selectable tone="muted" variant="caption">
              {locationName}
            </Text>
          ) : null}
        </View>
        {showStatus ? <InventoryStatusBadge size="sm" status={resolvedStatus} /> : null}
      </View>
    </BaseCard>
  );
}
