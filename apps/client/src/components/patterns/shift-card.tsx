import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { CardHeader } from "@/components/primitives/card-header";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import {
  formatShiftDateRange,
  type MemberShiftRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ShiftCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  shift: MemberShiftRecord;
};

export function ShiftCard({
  accessibilityLabel,
  compact = false,
  disabled = false,
  onPress,
  selected = false,
  shift,
}: ShiftCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const title =
    shift.member?.name?.trim() ??
    shift.event?.name?.trim() ??
    t("teamStaff.shift.fallbackTitle");
  const subtitle = [
    formatShiftDateRange(shift, i18n.language),
    shift.station?.name?.trim() ?? shift.team?.name?.trim() ?? null,
  ]
    .filter(Boolean)
    .join(" - ");

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("teamStaff.shift.cardAccessibilityLabel", {
          title,
        })
      }
      disabled={disabled}
      onPress={onPress}
      padding={compact ? "md" : "lg"}
      radius="md"
      selected={selected}
      variant="muted"
    >
      <CardHeader
        subtitle={subtitle}
        title={title}
        trailing={
          shift.status ? (
            <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
              <StatusBadge namespace="shifts" size="sm" status={shift.status} />
            </View>
          ) : undefined
        }
      >
        {shift.role?.trim() ? (
          <Text tone="muted" variant="caption">
            {shift.role.trim()}
          </Text>
        ) : null}
      </CardHeader>
    </BaseCard>
  );
}
