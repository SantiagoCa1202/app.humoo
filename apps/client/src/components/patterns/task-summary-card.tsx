import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { TaskStatusBadge } from "@/components/patterns/task-status-badge";
import {
  buildTaskSummary,
  type TaskRecord,
  type TaskSummaryRecord,
} from "@/features/tasks";

export type TaskSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  metrics?: SummaryMetric[];
  summary?: TaskSummaryRecord | null;
  tasks?: TaskRecord[];
  title?: React.ReactNode;
};

export function TaskSummaryCard({
  accessibilityLabel,
  compact = false,
  metrics,
  summary,
  tasks,
  title,
}: TaskSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const resolvedSummary = summary ?? (tasks ? buildTaskSummary(tasks) : null);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          typeof resolvedSummary?.total === "number"
            ? {
                label: t("tasks.labels.total"),
                value: t("tasks.metrics.tasks", { count: resolvedSummary.total }),
              }
            : null,
          typeof resolvedSummary?.todo === "number"
            ? {
                label: t("tasks.status.todo"),
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.todo),
              }
            : null,
          typeof resolvedSummary?.inProgress === "number"
            ? {
                label: t("tasks.status.in_progress"),
                value: new Intl.NumberFormat(i18n.language).format(
                  resolvedSummary.inProgress
                ),
              }
            : null,
          typeof resolvedSummary?.blocked === "number"
            ? {
                label: t("tasks.status.blocked"),
                tone: resolvedSummary.blocked > 0 ? "danger" : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.blocked),
              }
            : null,
          typeof resolvedSummary?.done === "number"
            ? {
                label: t("tasks.status.done"),
                tone: "success" as const,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.done),
              }
            : null,
          typeof resolvedSummary?.overdue === "number"
            ? {
                label: t("tasks.labels.overdue"),
                tone: resolvedSummary.overdue > 0 ? "danger" : undefined,
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.overdue),
              }
            : null,
          typeof resolvedSummary?.assigned === "number"
            ? {
                label: t("tasks.labels.assigned"),
                value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.assigned),
              }
            : null,
          typeof resolvedSummary?.unassigned === "number"
            ? {
                label: t("tasks.labels.unassigned"),
                value: new Intl.NumberFormat(i18n.language).format(
                  resolvedSummary.unassigned
                ),
              }
            : null,
        ].filter(Boolean) as SummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("tasks.summary.accessibilityLabel")}
      metrics={compact ? resolvedMetrics.slice(0, 4) : resolvedMetrics}
      subtitle={
        resolvedSummary?.total
          ? t("tasks.metrics.tasks", { count: resolvedSummary.total })
          : undefined
      }
      title={title ?? t("tasks.summary.title")}
      trailing={
        typeof resolvedSummary?.done === "number" ? (
          <TaskStatusBadge showDot={false} size="sm" status="done" />
        ) : undefined
      }
      variant="elevated"
    />
  );
}
