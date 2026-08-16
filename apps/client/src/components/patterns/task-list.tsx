import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { TaskListItem } from "@/components/patterns/task-list-item";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  sortTasks,
  TASK_STATUS_ORDER,
  type TaskRecord,
  type TaskStatus,
} from "@/features/tasks";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type TaskRow =
  | { id: string; status: TaskStatus; type: "header" }
  | { id: string; task: TaskRecord; type: "item" };

export type TaskListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByStatus?: boolean;
  loading?: boolean;
  onEndReached?: () => void;
  onItemPress?: (task: TaskRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedTaskId?: string | null;
  tasks: TaskRecord[];
};

function TaskListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`task-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="30%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="24%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

function createRows(tasks: TaskRecord[], groupByStatus: boolean): TaskRow[] {
  const orderedTasks = sortTasks(tasks);

  if (!groupByStatus) {
    return orderedTasks.map((task) => ({
      id: task.id ?? `${task.title}-${task.dueAt ?? "task"}`,
      task,
      type: "item" as const,
    }));
  }

  return TASK_STATUS_ORDER.flatMap((status) => {
    const matchingTasks = orderedTasks.filter((task) => (task.status ?? "todo") === status);

    if (!matchingTasks.length) {
      return [];
    }

    return [
      {
        id: `task-status-${status}`,
        status,
        type: "header" as const,
      },
      ...matchingTasks.map((task) => ({
        id: task.id ?? `${task.title}-${status}`,
        task,
        type: "item" as const,
      })),
    ];
  });
}

export function TaskList({
  accessibilityLabel,
  compact = false,
  error,
  groupByStatus = false,
  loading = false,
  onEndReached,
  onItemPress,
  onRefresh,
  refreshing = false,
  selectedTaskId,
  tasks,
}: TaskListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const rows = createRows(tasks, groupByStatus);

  if (loading && tasks.length === 0) {
    return <TaskListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("tasks.error.title")}
      />
    );
  }

  if (tasks.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("tasks.empty.description")}
        title={t("tasks.empty.title")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("tasks.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={rows}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) =>
        item.type === "header" ? (
          <Text tone="muted" variant="overline">
            {t(getStatusTranslationKey(item.status, "tasks"))}
          </Text>
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            <TaskListItem
              onPress={onItemPress ? () => void onItemPress(item.task) : undefined}
              selected={selectedTaskId === item.task.id}
              showPriority={!compact}
              task={item.task}
            />
            <Divider spacing="none" />
          </View>
        )
      }
    />
  );
}
