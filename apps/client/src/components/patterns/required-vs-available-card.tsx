import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryMeasurement,
  getInventoryItemName,
  getInventoryLocationName,
  getRequiredAvailabilityShortage,
  getRequiredAvailabilityStatus,
  type InventoryItemRecord,
  type InventoryLocationReference,
  type InventoryRequirementRecord,
  type InventoryRequirementStatus,
  type InventoryUnitReference,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RequiredVsAvailableCardProps = {
  accessibilityLabel?: string;
  available?: number | null;
  compact?: boolean;
  item?: InventoryItemRecord | null;
  location?: InventoryLocationReference | null;
  onPurchase?: () => void | Promise<void>;
  onViewInventory?: () => void | Promise<void>;
  required?: number | null;
  requirement?: InventoryRequirementRecord | null;
  shortage?: number | null;
  source?: string | null;
  status?: InventoryRequirementStatus | null;
  unit?: InventoryUnitReference | null;
};

export function RequiredVsAvailableCard({
  accessibilityLabel,
  available,
  compact = false,
  item,
  location,
  onPurchase,
  onViewInventory,
  required,
  requirement,
  shortage,
  source,
  status,
  unit,
}: RequiredVsAvailableCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedItem = item ?? requirement?.item ?? null;
  const resolvedUnit = unit ?? requirement?.unit ?? null;
  const resolvedLocation = location ?? requirement?.location ?? null;
  const resolvedRequired = required ?? requirement?.required ?? null;
  const resolvedAvailable = available ?? requirement?.available ?? null;
  const resolvedShortage =
    shortage ?? requirement?.shortage ?? getRequiredAvailabilityShortage(requirement);
  const resolvedStatus = status ?? getRequiredAvailabilityStatus({
    ...requirement,
    available: resolvedAvailable,
    required: resolvedRequired,
    shortage: resolvedShortage,
  });
  const statusTone =
    resolvedStatus === "sufficient"
      ? "success"
      : resolvedStatus === "shortage"
      ? "warning"
      : "neutral";

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.requirement.accessibilityLabel", {
          item: getInventoryItemName(resolvedItem) ?? t("inventory.item.fallbackName"),
        })
      }
      padding="lg"
      variant="default"
    >
      <View style={{ gap: theme.spacing[3] }}>
        <View
          style={{
            alignItems: "flex-start",
            flexDirection: "row",
            gap: theme.spacing[3],
            justifyContent: "space-between",
          }}
        >
          <View style={{ flex: 1, gap: theme.spacing[1] }}>
            <Text selectable variant="title">
              {getInventoryItemName(resolvedItem) ?? t("inventory.item.fallbackName")}
            </Text>
            {[source ?? requirement?.sourceLabel ?? null, getInventoryLocationName(resolvedLocation)]
              .filter(Boolean)
              .length ? (
              <Text selectable tone="muted" variant="caption">
                {[source ?? requirement?.sourceLabel ?? null, getInventoryLocationName(resolvedLocation)]
                  .filter(Boolean)
                  .join(" - ")}
              </Text>
            ) : null}
          </View>
          <Badge
            label={t(`inventory.requirement.status.${resolvedStatus}`)}
            size="sm"
            variant={statusTone}
          />
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 120 }}>
            <Text tone="muted" variant="caption">
              {t("inventory.requirement.required")}
            </Text>
            <Text selectable variant="bodySmall">
              {formatInventoryMeasurement(resolvedRequired, resolvedUnit, i18n.language) ??
                t("inventory.requirement.unknown")}
            </Text>
          </View>
          <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 120 }}>
            <Text tone="muted" variant="caption">
              {t("inventory.requirement.available")}
            </Text>
            <Text selectable variant="bodySmall">
              {formatInventoryMeasurement(resolvedAvailable, resolvedUnit, i18n.language) ??
                t("inventory.requirement.unknown")}
            </Text>
          </View>
          {resolvedShortage !== null && resolvedShortage !== undefined ? (
            <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 120 }}>
              <Text tone="muted" variant="caption">
                {resolvedStatus === "sufficient"
                  ? t("inventory.requirement.sufficient")
                  : t("inventory.requirement.shortage")}
              </Text>
              <Text
                selectable
                tone={resolvedStatus === "sufficient" ? "success" : "warning"}
                variant="bodySmall"
              >
                {resolvedStatus === "sufficient"
                  ? t("inventory.requirement.sufficient")
                  : formatInventoryMeasurement(resolvedShortage, resolvedUnit, i18n.language) ??
                    t("inventory.requirement.unknown")}
              </Text>
            </View>
          ) : null}
        </View>
        {!compact && (onViewInventory || onPurchase) ? (
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
            {onViewInventory ? (
              <Button
                label={t("inventory.actions.viewInventory")}
                onPress={onViewInventory}
                size="sm"
                variant="secondary"
              />
            ) : null}
            {onPurchase ? (
              <Button
                label={t("inventory.actions.purchase")}
                onPress={onPurchase}
                size="sm"
                variant="secondary"
              />
            ) : null}
          </View>
        ) : null}
      </View>
    </BaseCard>
  );
}
