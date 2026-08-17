import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { InventoryListItem } from "@/components/patterns/inventory-list-item";
import { InventoryStatusBadge } from "@/components/patterns/inventory-status-badge";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
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
import { useAppTheme } from "@/theme/ThemeProvider";

export type LowStockAlertProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  item: InventoryItemRecord;
  minimumQuantity?: number | null;
  onCreatePurchaseRequest?: () => void | Promise<void>;
  onDismiss?: () => void | Promise<void>;
  onView?: () => void | Promise<void>;
  shortageQuantity?: number | null;
  status?: InventoryStatus | null;
  stock?: InventoryStockRecord | null;
  supplier?: InventoryStockRecord["supplier"];
};

export function LowStockAlert({
  accessibilityLabel,
  compact = false,
  item,
  minimumQuantity,
  onCreatePurchaseRequest,
  onDismiss,
  onView,
  shortageQuantity,
  status,
  stock,
  supplier,
}: LowStockAlertProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedStock = stock ?? item.stock ?? null;
  const resolvedStatus = status ?? getInventoryStatus(item, resolvedStock);
  const resolvedUnit = getInventoryUnit(item, resolvedStock);
  const currentLabel =
    formatInventoryMeasurement(
      getInventoryAvailableQuantity(resolvedStock),
      resolvedUnit,
      i18n.language
    ) ?? t("inventory.labels.unknownStock");
  const thresholdValue =
    minimumQuantity ??
    getInventoryThreshold(resolvedStock)?.value ??
    null;
  const thresholdLabel = formatInventoryMeasurement(
    thresholdValue,
    resolvedUnit,
    i18n.language
  );
  const shortageLabel = formatInventoryMeasurement(
    shortageQuantity ?? resolvedStock?.shortageQuantity,
    resolvedUnit,
    i18n.language
  );
  const supplierName = getInventorySupplierName(
    supplier ?? getInventorySupplier(item, resolvedStock)
  );
  const locationName = getInventoryLocationName(getInventoryLocation(item, resolvedStock));
  const titleKey =
    resolvedStatus === "out_of_stock"
      ? "inventory.lowStockAlert.outTitle"
      : "inventory.lowStockAlert.lowTitle";

  return (
    <AlertCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.lowStockAlert.accessibilityLabel")}
      description={
        <View style={{ gap: theme.spacing[3] }}>
          <InventoryListItem
            item={item}
            showLocation={!compact}
            showStatus
            status={resolvedStatus}
            stock={resolvedStock}
          />
          <View style={{ gap: theme.spacing[1] }}>
            <Text selectable tone="secondary" variant="bodySmall">
              {t("inventory.lowStockAlert.current", { value: currentLabel })}
            </Text>
            {thresholdLabel ? (
              <Text selectable tone="secondary" variant="bodySmall">
                {t("inventory.lowStockAlert.threshold", { value: thresholdLabel })}
              </Text>
            ) : null}
            {shortageLabel ? (
              <Text selectable tone="danger" variant="bodySmall">
                {t("inventory.lowStockAlert.shortage", { value: shortageLabel })}
              </Text>
            ) : null}
            {locationName ? (
              <Text selectable tone="muted" variant="caption">
                {t("inventory.lowStockAlert.location", { value: locationName })}
              </Text>
            ) : null}
            {supplierName ? (
              <Text selectable tone="muted" variant="caption">
                {t("inventory.lowStockAlert.supplier", { value: supplierName })}
              </Text>
            ) : null}
          </View>
          {onView || onCreatePurchaseRequest ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {onView ? (
                <Button
                  label={t("inventory.actions.view")}
                  onPress={onView}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onCreatePurchaseRequest ? (
                <Button
                  label={t("inventory.actions.createPurchaseRequest")}
                  onPress={onCreatePurchaseRequest}
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
        <View
          style={{
            alignItems: "center",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[2],
          }}
        >
          <Text selectable variant="h4">
            {t(titleKey)}
          </Text>
          <InventoryStatusBadge showDot={false} size="sm" status={resolvedStatus} />
        </View>
      }
      tone={resolvedStatus === "out_of_stock" ? "error" : "warning"}
      variant="muted"
    />
  );
}
