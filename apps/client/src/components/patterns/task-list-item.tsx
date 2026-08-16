import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { TaskDueIndicator } from "@/components/patterns/task-due-indicator";
import { TaskPriorityBadge } from "@/components/patterns/task-priority-badge";
import { TaskStatusBadge } from "@/components/patterns/task-status-badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import {
  formatTaskDateTime,
  getTaskAssignmentLabel,
  getTaskPrimaryAssignment,
  getTaskPriority,
  getTaskStatus,
  type TaskRecord,
} from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

export type TaskListItemProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showPriority?: boolean;
  showStatus?: boolean;
  task: TaskRecord;
};

export function TaskListItem({
  accessibilityLabel,
  disabled = false,
  onPress,
  selected = false,
  showPriority = true,
  showStatus = true,
  task,
}: TaskListItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getTaskStatus(task);
  const priority = getTaskPriority(task);
  const assignmentLabel = getTaskAssignmentLabel(getTaskPrimaryAssignment(task.assignments));
  const dueLabel = formatTaskDateTime(task.dueAt, i18n.language);
  const secondary = [
    assignmentLabel
      ? t("tasks.secondary.assignedTo", { value: assignmentLabel })
      : t("tasks.labels.unassigned"),
    dueLabel
      ? t("tasks.labels.dueValue", {
          value: dueLabel,
        })
      : null,
  ]
    .filter(Boolean)
    .join(" - ");

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("tasks.listItem.accessibilityLabel", {
          title: task.title,
        })
      }
      disabled={disabled}
      onPress={onPress}
      padding="md"
      radius="md"
      selected={selected}
      variant="muted"
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          <Text numberOfLines={2} selectable variant="title">
            {task.title}
          </Text>
          {secondary ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {secondary}
            </Text>
          ) : null}
          {task.dueAt ? (
            <TaskDueIndicator
              compact
              dueAt={task.dueAt}
              status={status}
              timeZone={task.event?.timezone}
            />
          ) : null}
        </View>
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {showPriority && priority ? <TaskPriorityBadge priority={priority} size="sm" /> : null}
          {showStatus && status ? <TaskStatusBadge size="sm" status={status} /> : null}
        </View>
      </View>
    </BaseCard>
  );
}
