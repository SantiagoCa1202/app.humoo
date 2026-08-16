import { useWindowDimensions, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { MemberCard } from "@/components/patterns/member-card";
import { PrepItem } from "@/components/patterns/prep-item";
import { TaskListItem } from "@/components/patterns/task-list-item";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { Divider } from "@/components/primitives/divider";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import type { PrepItemRecord } from "@/features/prep";
import type { TaskRecord } from "@/features/tasks";
import {
  createAssignmentBoardItemsFromPrepItems,
  createAssignmentBoardItemsFromTasks,
  groupAssignmentBoardItemsByMember,
  type AssignmentBoardItem,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

export type AssignmentBoardProps = {
  accessibilityLabel?: string;
  assignments?: AssignmentBoardItem[];
  compact?: boolean;
  disabled?: boolean;
  error?: React.ReactNode;
  loading?: boolean;
  members: TeamStaffMemberRecord[];
  onAssign?: (input: {
    entityId: string;
    membershipId: string;
    type: AssignmentBoardItem["type"];
  }) => void | Promise<void>;
  onMemberPress?: (member: TeamStaffMemberRecord) => void;
  onPrepItemPress?: (item: PrepItemRecord) => void;
  onTaskPress?: (task: TaskRecord) => void;
  onUnassign?: (input: {
    entityId: string;
    membershipId: string | null;
    type: AssignmentBoardItem["type"];
  }) => void | Promise<void>;
  prepItems?: PrepItemRecord[];
  selectedAssignmentId?: string | null;
  selectedMemberId?: string | null;
  tasks?: TaskRecord[];
};

function AssignmentBoardSkeleton() {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: 3 }).map((_, index) => (
        <BaseCard key={`assignment-board-skeleton-${index}`} padding="md" variant="muted">
          <SkeletonText lines={3} />
        </BaseCard>
      ))}
    </View>
  );
}

export function AssignmentBoard({
  accessibilityLabel,
  assignments,
  compact = false,
  disabled = false,
  error,
  loading = false,
  members,
  onAssign,
  onMemberPress,
  onPrepItemPress,
  onTaskPress,
  onUnassign,
  prepItems,
  selectedAssignmentId,
  selectedMemberId,
  tasks,
}: AssignmentBoardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const isWide = width >= 1100;
  const resolvedAssignments =
    assignments ??
    [
      ...createAssignmentBoardItemsFromTasks(tasks ?? []),
      ...createAssignmentBoardItemsFromPrepItems(prepItems ?? []),
    ];
  const grouped = groupAssignmentBoardItemsByMember(members, resolvedAssignments);

  if (loading && resolvedAssignments.length === 0) {
    return <AssignmentBoardSkeleton />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("teamStaff.assignmentBoard.errorTitle")}
      />
    );
  }

  if (members.length === 0 && resolvedAssignments.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("teamStaff.assignmentBoard.emptyDescription")}
        title={t("teamStaff.assignmentBoard.emptyTitle")}
      />
    );
  }

  const renderAssignment = (item: AssignmentBoardItem) => (
    <View key={item.id} style={{ gap: theme.spacing[2] }}>
      <Text tone="muted" variant="caption">
        {item.type === "task"
          ? t("teamStaff.labels.task")
          : t("teamStaff.labels.prepItem")}
      </Text>
      {item.type === "task" && item.task ? (
        <TaskListItem
          onPress={onTaskPress ? () => void onTaskPress(item.task as TaskRecord) : undefined}
          selected={selectedAssignmentId === item.id}
          task={item.task as TaskRecord}
        />
      ) : item.prepItem ? (
        <PrepItem
          compact
          item={item.prepItem as PrepItemRecord}
          onPress={
            onPrepItemPress ? () => void onPrepItemPress(item.prepItem as PrepItemRecord) : undefined
          }
          selected={selectedAssignmentId === item.id}
          showActions={false}
        />
      ) : null}
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {onAssign && selectedMemberId && !item.assigneeMembershipId ? (
          <Button
            disabled={disabled}
            label={t("teamStaff.actions.assign")}
            onPress={() =>
              void onAssign({
                entityId: item.entityId,
                membershipId: selectedMemberId,
                type: item.type,
              })
            }
            size="sm"
            variant="secondary"
          />
        ) : null}
        {onUnassign && item.assigneeMembershipId ? (
          <Button
            disabled={disabled}
            label={t("teamStaff.actions.unassign")}
            onPress={() =>
              void onUnassign({
                entityId: item.entityId,
                membershipId: item.assigneeMembershipId ?? null,
                type: item.type,
              })
            }
            size="sm"
            variant="ghost"
          />
        ) : null}
      </View>
    </View>
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.assignmentBoard.accessibilityLabel")}
      style={{
        flexDirection: isWide ? "row" : "column",
        flexWrap: isWide ? "wrap" : "nowrap",
        gap: theme.spacing[4],
      }}
    >
      {grouped.assignedGroups.map((group) => (
        <View
          key={group.id}
          style={{
            flexBasis: isWide ? "48%" : "100%",
            flexGrow: 1,
            minWidth: isWide ? 360 : "100%",
          }}
        >
          <BaseCard padding="md" variant="elevated">
            <View style={{ gap: theme.spacing[3] }}>
              <MemberCard
                compact={compact}
                member={group.member}
                onPress={onMemberPress ? () => void onMemberPress(group.member) : undefined}
                selected={selectedMemberId === group.member.id}
              />
              <Divider spacing="none" />
              {group.items.length ? (
                <View style={{ gap: theme.spacing[3] }}>
                  {group.items.map((item, index) => (
                    <View key={item.id} style={{ gap: theme.spacing[3] }}>
                      {renderAssignment(item)}
                      {index < group.items.length - 1 ? <Divider spacing="none" /> : null}
                    </View>
                  ))}
                </View>
              ) : (
                <Text tone="muted" variant="bodySmall">
                  {t("teamStaff.assignmentBoard.emptyMember")}
                </Text>
              )}
            </View>
          </BaseCard>
        </View>
      ))}
      <View
        style={{
          flexBasis: isWide ? "48%" : "100%",
          flexGrow: 1,
          minWidth: isWide ? 360 : "100%",
        }}
      >
        <BaseCard padding="md" variant="muted">
          <View style={{ gap: theme.spacing[3] }}>
            <Text variant="title">{t("teamStaff.labels.unassigned")}</Text>
            <Divider spacing="none" />
            {grouped.unassignedItems.length ? (
              <View style={{ gap: theme.spacing[3] }}>
                {grouped.unassignedItems.map((item, index) => (
                  <View key={item.id} style={{ gap: theme.spacing[3] }}>
                    {renderAssignment(item)}
                    {index < grouped.unassignedItems.length - 1 ? (
                      <Divider spacing="none" />
                    ) : null}
                  </View>
                ))}
              </View>
            ) : (
              <Text tone="muted" variant="bodySmall">
                {t("teamStaff.assignmentBoard.emptyUnassigned")}
              </Text>
            )}
          </View>
        </BaseCard>
      </View>
    </View>
  );
}
