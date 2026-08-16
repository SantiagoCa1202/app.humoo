import { useWindowDimensions, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { MemberCard } from "@/components/patterns/member-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  groupMembersByRole,
  groupMembersByTeam,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type TeamRosterProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByRole?: boolean;
  groupByTeam?: boolean;
  loading?: boolean;
  members: TeamStaffMemberRecord[];
  onMemberPress?: (member: TeamStaffMemberRecord) => void;
  onRefresh?: () => void;
  selectedMemberId?: string | null;
};

function TeamRosterSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`member-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <SkeletonText lines={2} />
        </BaseCard>
      ))}
    </View>
  );
}

export function TeamRoster({
  accessibilityLabel,
  compact = false,
  error,
  groupByRole = false,
  groupByTeam = false,
  loading = false,
  members,
  onMemberPress,
  selectedMemberId,
}: TeamRosterProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const isWide = width >= 960;
  const groups: Array<{
    id: string;
    label: string | null;
    members: TeamStaffMemberRecord[];
    roleKey?: string | null;
  }> | null = groupByTeam
    ? groupMembersByTeam(members)
    : groupByRole
    ? groupMembersByRole(members)
    : null;

  if (loading && members.length === 0) {
    return <TeamRosterSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("teamStaff.roster.errorTitle")}
      />
    );
  }

  if (members.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("teamStaff.roster.emptyDescription")}
        title={t("teamStaff.roster.emptyTitle")}
      />
    );
  }

  const renderMembers = (items: TeamStaffMemberRecord[]) => (
    <View
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        gap: theme.spacing[3],
      }}
    >
      {items.map((member) => (
        <View
          key={member.id}
          style={{
            flexBasis: isWide ? "48%" : "100%",
            flexGrow: 1,
            minWidth: isWide ? 320 : "100%",
          }}
        >
          <MemberCard
            compact={compact}
            member={member}
            onPress={onMemberPress ? () => void onMemberPress(member) : undefined}
            selected={selectedMemberId === member.id}
          />
        </View>
      ))}
    </View>
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.roster.accessibilityLabel")}
      style={{ gap: theme.spacing[4] }}
    >
      {groups
        ? groups.map((group, index) => (
            <View key={group.id} style={{ gap: theme.spacing[3] }}>
              <Text tone="muted" variant="overline">
                {groupByRole
                  ? group.roleKey
                    ? t(`teamStaff.roles.${group.roleKey}`, {
                        defaultValue: group.label ?? t("teamStaff.labels.unassigned"),
                      })
                    : group.label ?? t("teamStaff.labels.unassigned")
                  : group.label ?? t("teamStaff.labels.unassigned")}
              </Text>
              {renderMembers(group.members)}
              {index < groups.length - 1 ? <Divider spacing="none" /> : null}
            </View>
          ))
        : renderMembers(members)}
    </View>
  );
}
