import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import {
  formatInventoryDateTime,
  getInventoryMovementItemName,
  getInventoryMovementLocationLabel,
  getInventoryMovementQuantityLabel,
  getInventoryMovementResultingLabel,
  getInventoryMovementTone,
  getInventoryMovementTranslationKey,
  type InventoryItemRecord,
  type InventoryLocationReference,
  type InventoryMovementRecord,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

export type StockMovementCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  item?: InventoryItemRecord | null;
  location?: InventoryLocationReference | null;
  movement: InventoryMovementRecord;
  onPress?: () => void | Promise<void>;
};

export function StockMovementCard({
  accessibilityLabel,
  compact = false,
  item,
  location,
  movement,
  onPress,
}: StockMovementCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const name = getInventoryMovementItemName(movement, item) ?? t("inventory.item.fallbackName");
  const quantityLabel =
    getInventoryMovementQuantityLabel(movement, i18n.language) ??
    t("inventory.labels.unknownStock");
  const movementLabel = t(getInventoryMovementTranslationKey(movement.type));
  const timestamp =
    formatInventoryDateTime(movement.occurredAt ?? movement.createdAt, i18n.language) ??
    null;
  const locationLabel =
    getInventoryMovementLocationLabel({
      ...movement,
      location: location ?? movement.location,
    }) ?? null;
  const resultingLabel = getInventoryMovementResultingLabel(movement, i18n.language);
  const actorLabel = movement.createdBy?.name?.trim() ?? null;
  const secondaryParts = [timestamp, locationLabel, actorLabel].filter(Boolean);

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.movements.cardAccessibilityLabel", {
          item: name,
          type: movementLabel,
          quantity: quantityLabel,
        })
      }
      onPress={onPress}
      padding={compact ? "md" : "lg"}
      radius="md"
      variant="muted"
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
              {name}
            </Text>
            <Text selectable tone="secondary" variant="bodySmall">
              {quantityLabel}
            </Text>
            {secondaryParts.length ? (
              <Text selectable tone="muted" variant="caption">
                {secondaryParts.join(" - ")}
              </Text>
            ) : null}
          </View>
          <Badge
            label={movementLabel}
            size="sm"
            variant={getInventoryMovementTone(movement.type)}
          />
        </View>
        {!compact && (movement.reason || movement.referenceType || resultingLabel) ? (
          <View style={{ gap: theme.spacing[1] }}>
            {movement.reason ? (
              <Text selectable tone="secondary" variant="bodySmall">
                {t("inventory.movements.reasonValue", { value: movement.reason })}
              </Text>
            ) : null}
            {movement.referenceType || movement.referenceId ? (
              <Text selectable tone="muted" variant="caption">
                {t("inventory.movements.referenceValue", {
                  value: [movement.referenceType, movement.referenceId].filter(Boolean).join(" - "),
                })}
              </Text>
            ) : null}
            {resultingLabel ? (
              <Text selectable tone="primary" variant="caption">
                {t("inventory.movements.resultingBalance", { value: resultingLabel })}
              </Text>
            ) : null}
          </View>
        ) : null}
      </View>
    </BaseCard>
  );
}
