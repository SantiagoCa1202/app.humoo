import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AvatarGroup } from "@/components/primitives/avatar-group";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import {
  buildWorkloadSummary,
  type StationRecord,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type StationCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  members?: TeamStaffMemberRecord[];
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  station: StationRecord;
};

export function StationCard({
  accessibilityLabel,
  actions,
  compact = false,
  disabled = false,
  members,
  onPress,
  selected = false,
  station,
}: StationCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedMembers = members ?? station.members ?? [];
  const workload = buildWorkloadSummary(station.workload);
  let stationStatus: "active" | "inactive" | null = null;

  if (station.status === "active") {
    stationStatus = "active";
  } else if (station.status === "inactive") {
    stationStatus = "inactive";
  }

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("teamStaff.station.cardAccessibilityLabel", {
          name: station.name,
        })
      }
      disabled={disabled}
      onPress={onPress}
      padding={compact ? "md" : "lg"}
      selected={selected}
      variant="elevated"
    >
      <CardHeader
        subtitle={station.description?.trim() || station.team?.name?.trim() || undefined}
        title={station.name}
        trailing={
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            {actions}
            {stationStatus ? (
              <StatusBadge
                namespace="workspaceMembers"
                size="sm"
                status={stationStatus}
              />
            ) : null}
          </View>
        }
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {resolvedMembers.length ? (
            <View style={{ gap: theme.spacing[2] }}>
              <Text tone="muted" variant="caption">
                {t("teamStaff.labels.members")}
              </Text>
              <AvatarGroup
                max={compact ? 3 : 5}
                size={compact ? "sm" : "md"}
                users={resolvedMembers.map((member) => ({
                  name: member.name ?? undefined,
                  source: member.source,
                  variant: "neutral",
                }))}
              />
            </View>
          ) : null}
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
            {station.team?.name?.trim() ? (
              <View style={{ gap: theme.spacing[1], minWidth: 120 }}>
                <Text tone="muted" variant="caption">
                  {t("teamStaff.labels.team")}
                </Text>
                <Text selectable variant="bodySmall">
                  {station.team.name.trim()}
                </Text>
              </View>
            ) : null}
            {typeof workload.totalAssignments === "number" ? (
              <View style={{ gap: theme.spacing[1], minWidth: 120 }}>
                <Text tone="muted" variant="caption">
                  {t("teamStaff.labels.totalAssignments")}
                </Text>
                <Text selectable variant="bodySmall">
                  {t("teamStaff.metrics.assignments", {
                    count: workload.totalAssignments,
                  })}
                </Text>
              </View>
            ) : null}
          </View>
        </View>
      </CardContent>
    </BaseCard>
  );
}
