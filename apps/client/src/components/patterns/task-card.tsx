import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { TaskDueIndicator } from "@/components/patterns/task-due-indicator";
import { TaskPriorityBadge } from "@/components/patterns/task-priority-badge";
import { TaskStatusBadge } from "@/components/patterns/task-status-badge";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import {
  formatTaskDateTime,
  getTaskAssignedUsers,
  getTaskAssignmentLabel,
  getTaskContextLabel,
  getTaskPrimaryAssignment,
  getTaskPriority,
  getTaskStatus,
  isTaskOverdue,
  type TaskRecord,
} from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

export type TaskCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  task: TaskRecord;
  trailing?: React.ReactNode;
};

export function TaskCard({
  accessibilityLabel,
  compact = false,
  disabled = false,
  onPress,
  selected = false,
  task,
  trailing,
}: TaskCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const assignedUsers = getTaskAssignedUsers(task);
  const primaryAssignment = getTaskPrimaryAssignment(task.assignments);
  const assignmentLabel = getTaskAssignmentLabel(primaryAssignment);
  const dueLabel = task.dueAt;
  const startsLabel = formatTaskDateTime(task.startsAt, i18n.language);
  const contextLabel = getTaskContextLabel(task);
  const status = getTaskStatus(task);
  const priority = getTaskPriority(task);
  const overdue = isTaskOverdue(task);
  const metadata: EntityCardMetadataItem[] = [
    dueLabel
      ? {
          label: t("tasks.labels.due"),
          tone: overdue ? "danger" : undefined,
          value: (
            <TaskDueIndicator
              compact
              dueAt={dueLabel}
              status={status}
              timeZone={task.event?.timezone}
            />
          ),
        }
      : null,
    startsLabel
      ? {
          label: t("tasks.labels.starts"),
          value: startsLabel,
        }
      : null,
    contextLabel
      ? {
          label: t("tasks.labels.context"),
          value: contextLabel,
        }
      : null,
    assignmentLabel
      ? {
          label: t("tasks.labels.assignedTo"),
          value: assignmentLabel,
        }
      : {
          label: t("tasks.labels.assignedTo"),
          value: t("tasks.labels.unassigned"),
        },
  ].filter(Boolean) as EntityCardMetadataItem[];
  const subtitle = task.blockedReason?.trim() || task.description?.trim() || undefined;

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ??
        t("tasks.card.accessibilityLabel", {
          title: task.title,
        })
      }
      disabled={disabled}
      leading={
        assignedUsers.length ? (
          <AvatarGroup
            size={compact ? "sm" : "md"}
            users={assignedUsers.map((assignment) => ({
              name: assignment.user?.name ?? undefined,
              source: assignment.user?.source,
              variant: "neutral",
            }))}
          />
        ) : undefined
      }
      metadata={compact ? metadata.slice(0, 3) : metadata}
      onPress={onPress}
      selected={selected}
      subtitle={subtitle}
      title={task.title}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {trailing}
          {priority ? <TaskPriorityBadge priority={priority} size="sm" /> : null}
          {status ? <TaskStatusBadge size="sm" status={status} /> : null}
        </View>
      }
      variant="elevated"
    />
  );
}
