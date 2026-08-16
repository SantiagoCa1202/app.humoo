import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { MemberAvailabilityBadge } from "@/components/patterns/member-availability-badge";
import { WorkloadSummary } from "@/components/patterns/workload-summary";
import { Avatar } from "@/components/primitives/avatar";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import {
  buildWorkloadSummary,
  getMemberRoleLabel,
  getMemberStationName,
  getMemberTeamName,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MemberCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  member: TeamStaffMemberRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showAvailability?: boolean;
  showRole?: boolean;
  showStatus?: boolean;
  showWorkload?: boolean;
};

export function MemberCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  member,
  onPress,
  selected = false,
  showAvailability = true,
  showRole = true,
  showStatus = true,
  showWorkload = true,
}: MemberCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const role = getMemberRoleLabel(member);
  const teamName = getMemberTeamName(member);
  const stationName = getMemberStationName(member);
  const workload = buildWorkloadSummary(member.workload);
  const metadata: EntityCardMetadataItem[] = [
    teamName
      ? {
          label: t("teamStaff.labels.team"),
          value: teamName,
        }
      : null,
    stationName
      ? {
          label: t("teamStaff.labels.station"),
          value: stationName,
        }
      : null,
    showWorkload && typeof workload.totalAssignments === "number"
      ? {
          label: t("teamStaff.labels.totalAssignments"),
          value: t("teamStaff.metrics.assignments", {
            count: workload.totalAssignments,
          }),
        }
      : null,
    showWorkload && typeof workload.utilization === "number"
      ? {
          label: t("teamStaff.labels.utilization"),
          value: `${Math.round(workload.utilization)}%`,
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("teamStaff.member.cardAccessibilityLabel", {
          name: member.name ?? t("teamStaff.member.fallbackName"),
        })
      }
      disabled={disabled}
      leading={
        <Avatar
          name={member.name ?? t("teamStaff.member.fallbackName")}
          size={compact ? "sm" : "md"}
          source={member.source}
          variant="neutral"
        />
      }
      metadata={compact ? metadata.slice(0, 2) : metadata}
      onPress={onPress}
      selected={selected}
      subtitle={
        showRole && role ? (
          <Text selectable tone="secondary" variant="bodySmall">
            {role.translationKey ? t(role.translationKey) : role.value}
          </Text>
        ) : undefined
      }
      title={member.name ?? t("teamStaff.member.fallbackName")}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {actions}
          {showAvailability ? (
            <MemberAvailabilityBadge availability={member.availability} size="sm" />
          ) : null}
          {showStatus && member.status ? (
            <StatusBadge namespace="workspaceMembers" size="sm" status={member.status} />
          ) : null}
        </View>
      }
      variant="elevated"
    >
      {showWorkload && typeof workload.totalAssignments === "number" ? (
        <View
          style={{
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[3],
          }}
        >
          <Text tone="muted" variant="caption">
            {t("teamStaff.labels.workload")}
          </Text>
          <Text selectable variant="bodySmall">
            {t("teamStaff.metrics.assignments", {
              count: workload.totalAssignments,
            })}
          </Text>
          {typeof workload.taskCount === "number" ? (
            <Text selectable tone="secondary" variant="caption">
              {t("teamStaff.metrics.tasks", { count: workload.taskCount })}
            </Text>
          ) : null}
          {typeof workload.prepItemCount === "number" ? (
            <Text selectable tone="secondary" variant="caption">
              {t("teamStaff.metrics.prepItems", { count: workload.prepItemCount })}
            </Text>
          ) : null}
        </View>
      ) : null}
    </EntityCard>
  );
}
