import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { PrepAssignment } from "@/components/patterns/prep-assignment";
import { PrepStatusBadge } from "@/components/patterns/prep-status-badge";
import {
  formatPrepDateTime,
  formatPrepQuantity,
  getPrepPrimaryAssignment,
  type PrepItemRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepItemProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  item: PrepItemRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showActions?: boolean;
  showAssignment?: boolean;
  showDue?: boolean;
  showQuantity?: boolean;
};

export function PrepItem({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  item,
  onPress,
  selected = false,
  showActions = true,
  showAssignment = true,
  showDue = true,
  showQuantity = true,
}: PrepItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const assignment = getPrepPrimaryAssignment(item.assignments);
  const quantityLabel = formatPrepQuantity(item.quantity, item.unit, i18n.language);
  const dueLabel = formatPrepDateTime(item.dueAt, i18n.language);
  const metadata: EntityCardMetadataItem[] = [
    showQuantity && quantityLabel
      ? {
          label: t("prep.labels.quantity"),
          value: quantityLabel,
        }
      : null,
    item.recipeName?.trim()
      ? {
          label: t("prep.labels.recipe"),
          value: item.recipeName.trim(),
        }
      : null,
    showDue && dueLabel
      ? {
          label: t("prep.labels.due"),
          value: dueLabel,
        }
      : null,
    item.blockedReason?.trim()
      ? {
          label: t("prep.labels.blockedReason"),
          tone: "danger",
          value: item.blockedReason.trim(),
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("prep.item.accessibilityLabel")}
      disabled={disabled}
      eyebrow={
        showAssignment ? (
          <PrepAssignment assignment={assignment} compact={compact} />
        ) : undefined
      }
      metadata={compact ? metadata.slice(0, 2) : metadata}
      onPress={onPress}
      selected={selected}
      subtitle={item.description?.trim() || item.notes?.trim() || undefined}
      title={item.title}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {showActions ? actions : null}
          {item.status ? <PrepStatusBadge namespace="prepTasks" size="sm" status={item.status} /> : null}
        </View>
      }
      variant="default"
    />
  );
}
