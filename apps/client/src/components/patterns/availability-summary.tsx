import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Text } from "@/components/primitives/text";
import {
  buildAvailabilitySummary,
  getAvailabilityGroups,
  type AvailabilitySummaryRecord,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type AvailabilitySummaryProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  members?: TeamStaffMemberRecord[];
  onMemberPress?: (member: TeamStaffMemberRecord) => void;
  period?: string | null;
  summary?: AvailabilitySummaryRecord | null;
};

export function AvailabilitySummary({
  accessibilityLabel,
  compact = false,
  members = [],
  period,
  summary,
}: AvailabilitySummaryProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedSummary = summary ?? buildAvailabilitySummary(members, period);
  const groups = getAvailabilityGroups(members);
  const metrics: SummaryMetric[] = [
    typeof resolvedSummary.total === "number"
      ? {
          label: t("teamStaff.labels.members"),
          value: t("teamStaff.metrics.members", { count: resolvedSummary.total }),
        }
      : null,
    typeof resolvedSummary.available === "number"
      ? {
          label: t("teamStaff.availability.available"),
          tone: "success",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.available),
        }
      : null,
    typeof resolvedSummary.unavailable === "number"
      ? {
          label: t("teamStaff.availability.unavailable"),
          tone: "warning",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.unavailable),
        }
      : null,
    typeof resolvedSummary.onShift === "number"
      ? {
          label: t("teamStaff.availability.on_shift"),
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.onShift),
        }
      : null,
    typeof resolvedSummary.offShift === "number"
      ? {
          label: t("teamStaff.availability.off_shift"),
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.offShift),
        }
      : null,
    typeof resolvedSummary.busy === "number"
      ? {
          label: t("teamStaff.availability.busy"),
          tone: "warning",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.busy),
        }
      : null,
    typeof resolvedSummary.unknown === "number"
      ? {
          label: t("teamStaff.availability.unknown"),
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.unknown),
        }
      : null,
  ].filter(Boolean) as SummaryMetric[];

  const renderGroup = (
    label: string,
    items: TeamStaffMemberRecord[]
  ) =>
    items.length ? (
      <View style={{ gap: theme.spacing[2] }}>
        <Text tone="muted" variant="caption">
          {label}
        </Text>
        <AvatarGroup
          max={compact ? 4 : 6}
          size={compact ? "sm" : "md"}
          users={items.map((member) => ({
            name: member.name ?? undefined,
            source: member.source,
            variant: "neutral",
          }))}
        />
      </View>
    ) : null;

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.availabilitySummary.accessibilityLabel")}
      metrics={compact ? metrics.slice(0, 4) : metrics}
      subtitle={resolvedSummary.periodLabel ?? undefined}
      title={t("teamStaff.availabilitySummary.title")}
      trailing={undefined}
      variant="elevated"
    >
      <View />
      {members.length ? (
        <View style={{ gap: theme.spacing[3] }}>
          {renderGroup(t("teamStaff.availability.available"), groups.available)}
          {renderGroup(t("teamStaff.availability.on_shift"), groups.onShift)}
          {renderGroup(t("teamStaff.availability.unavailable"), groups.unavailable)}
          {renderGroup(t("teamStaff.availability.unknown"), groups.unknown)}
        </View>
      ) : null}
    </SummaryCard>
  );
}
