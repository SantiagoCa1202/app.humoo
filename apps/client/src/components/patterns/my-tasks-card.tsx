import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { TaskListItem } from "@/components/patterns/task-list-item";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { type TaskRecord } from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MyTasksCardProps = {
  accessibilityLabel?: string;
  emptyActionLabel?: string;
  maxItems?: number;
  onEmptyAction?: () => void | Promise<void>;
  onItemPress?: (task: TaskRecord) => void;
  onViewAllPress?: () => void | Promise<void>;
  selectedTaskId?: string | null;
  tasks: TaskRecord[];
  title?: React.ReactNode;
};

export function MyTasksCard({
  accessibilityLabel,
  emptyActionLabel,
  maxItems = 3,
  onEmptyAction,
  onItemPress,
  onViewAllPress,
  selectedTaskId,
  tasks,
  title,
}: MyTasksCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const visibleTasks = tasks.slice(0, maxItems);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("tasks.mine.accessibilityLabel")}
      padding="lg"
      variant="elevated"
    >
      <CardHeader
        subtitle={t("tasks.metrics.tasks", { count: tasks.length })}
        title={title ?? t("tasks.mine.title")}
      />
      <CardContent topDivider>
        {visibleTasks.length ? (
          <View style={{ gap: theme.spacing[3] }}>
            {visibleTasks.map((task, index) => (
              <View key={task.id ?? `${task.title}-${index}`} style={{ gap: theme.spacing[3] }}>
                <TaskListItem
                  onPress={onItemPress ? () => void onItemPress(task) : undefined}
                  selected={selectedTaskId === task.id}
                  task={task}
                />
                {index < visibleTasks.length - 1 ? <Divider spacing="none" /> : null}
              </View>
            ))}
          </View>
        ) : (
          <EmptyState
            actionLabel={emptyActionLabel}
            compact
            description={t("tasks.mine.emptyDescription")}
            onAction={onEmptyAction}
            title={t("tasks.mine.emptyTitle")}
          />
        )}
      </CardContent>
      {onViewAllPress ? (
        <CardFooter align="between" divider padding="none">
          <Text tone="muted" variant="caption">
            {t("tasks.metrics.tasks", { count: tasks.length })}
          </Text>
          <Button
            accessibilityLabel={t("tasks.actions.viewAll")}
            label={t("tasks.actions.viewAll")}
            onPress={onViewAllPress}
            size="sm"
            variant="ghost"
          />
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
