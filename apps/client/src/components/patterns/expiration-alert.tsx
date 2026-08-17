import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryDateLabel,
  formatInventoryMeasurement,
  getInventoryDaysUntilExpiration,
  getInventoryExpirationStatus,
  getInventoryItemName,
  getInventoryLocationName,
  getInventoryLotLabel,
  type InventoryItemRecord,
  type InventoryLocationReference,
  type InventoryLotRecord,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ExpirationAlertProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  expirationStatus?: "expiring_soon" | "expired" | null;
  item?: InventoryItemRecord | null;
  location?: InventoryLocationReference | null;
  lot: InventoryLotRecord;
  onDismiss?: () => void | Promise<void>;
  onRecordWaste?: () => void | Promise<void>;
  onView?: () => void | Promise<void>;
  remainingQuantity?: number | null;
};

export function ExpirationAlert({
  accessibilityLabel,
  compact = false,
  expirationStatus,
  item,
  location,
  lot,
  onDismiss,
  onRecordWaste,
  onView,
  remainingQuantity,
}: ExpirationAlertProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedStatus = getInventoryExpirationStatus(lot, expirationStatus);

  if (!resolvedStatus) {
    return null;
  }

  const resolvedItem = item ?? lot.inventoryItem ?? null;
  const resolvedLocation = location ?? lot.location ?? null;
  const itemName = getInventoryItemName(resolvedItem) ?? t("inventory.item.fallbackName");
  const lotLabel = getInventoryLotLabel(lot);
  const expiresAtLabel = formatInventoryDateLabel(lot.expiresAt, i18n.language);
  const quantityLabel = formatInventoryMeasurement(
    remainingQuantity ?? lot.quantityOnHand,
    lot.unit,
    i18n.language
  );
  const daysRemaining = getInventoryDaysUntilExpiration(lot);

  return (
    <AlertCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.expiration.accessibilityLabel", {
          item: itemName,
        })
      }
      description={
        <View style={{ gap: theme.spacing[3] }}>
          <View style={{ gap: theme.spacing[1] }}>
            <Text selectable variant="bodySmall">
              {itemName}
            </Text>
            {lotLabel ? (
              <Text selectable tone="secondary" variant="bodySmall">
                {t("inventory.expiration.lotValue", { value: lotLabel })}
              </Text>
            ) : null}
            {expiresAtLabel ? (
              <Text selectable tone="secondary" variant="bodySmall">
                {t("inventory.expiration.expiresAt", { value: expiresAtLabel })}
              </Text>
            ) : null}
            {quantityLabel ? (
              <Text selectable tone="secondary" variant="bodySmall">
                {t("inventory.expiration.remainingQuantity", { value: quantityLabel })}
              </Text>
            ) : null}
            {resolvedLocation ? (
              <Text selectable tone="muted" variant="caption">
                {t("inventory.expiration.locationValue", {
                  value: getInventoryLocationName(resolvedLocation),
                })}
              </Text>
            ) : null}
            {resolvedStatus === "expiring_soon" && typeof daysRemaining === "number" ? (
              <Text selectable tone="warning" variant="caption">
                {t("inventory.expiration.daysRemaining", { count: daysRemaining })}
              </Text>
            ) : null}
          </View>
          {onView || onRecordWaste ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {onView ? (
                <Button
                  label={t("inventory.actions.view")}
                  onPress={onView}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onRecordWaste ? (
                <Button
                  label={t("inventory.actions.recordWaste")}
                  onPress={onRecordWaste}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
            </View>
          ) : null}
        </View>
      }
      dismissible={Boolean(onDismiss)}
      onDismiss={onDismiss ? () => void onDismiss() : undefined}
      title={
        resolvedStatus === "expired"
          ? t("inventory.expiration.expiredTitle")
          : t("inventory.expiration.expiringSoonTitle")
      }
      tone={resolvedStatus === "expired" ? "error" : "warning"}
      variant="muted"
    />
  );
}
