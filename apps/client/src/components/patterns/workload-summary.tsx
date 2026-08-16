import { useTranslation } from "react-i18next";

import { ProgressCard, type ProgressMetric } from "@/components/patterns/progress-card";
import {
  buildWorkloadSummary,
  type WorkloadSummaryRecord,
} from "@/features/team-staff";

export type WorkloadSummaryProps = WorkloadSummaryRecord & {
  accessibilityLabel?: string;
  compact?: boolean;
  subtitle?: React.ReactNode;
  title?: React.ReactNode;
};

export function WorkloadSummary({
  accessibilityLabel,
  blocked,
  capacity,
  compact = false,
  completed,
  inProgress,
  overloaded,
  prepItemCount,
  subtitle,
  taskCount,
  title,
  totalAssignments,
  utilization,
}: WorkloadSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const summary = buildWorkloadSummary({
    blocked,
    capacity,
    completed,
    inProgress,
    overloaded,
    prepItemCount,
    taskCount,
    totalAssignments,
    utilization,
  });
  const metrics: ProgressMetric[] = [
    {
      label: t("teamStaff.labels.tasks"),
      value: new Intl.NumberFormat(i18n.language).format(summary.taskCount ?? 0),
    },
    {
      label: t("teamStaff.labels.prepItems"),
      value: new Intl.NumberFormat(i18n.language).format(summary.prepItemCount ?? 0),
    },
    typeof summary.totalAssignments === "number"
      ? {
          label: t("teamStaff.labels.totalAssignments"),
          value: new Intl.NumberFormat(i18n.language).format(summary.totalAssignments),
        }
      : null,
    typeof summary.completed === "number"
      ? {
          label: t("teamStaff.labels.completed"),
          value: new Intl.NumberFormat(i18n.language).format(summary.completed),
        }
      : null,
    typeof summary.inProgress === "number"
      ? {
          label: t("teamStaff.labels.inProgress"),
          value: new Intl.NumberFormat(i18n.language).format(summary.inProgress),
        }
      : null,
    typeof summary.blocked === "number"
      ? {
          label: t("teamStaff.labels.blocked"),
          tone: summary.blocked > 0 ? "warning" : undefined,
          value: new Intl.NumberFormat(i18n.language).format(summary.blocked),
        }
      : null,
  ].filter(Boolean) as ProgressMetric[];

  return (
    <ProgressCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.workload.accessibilityLabel")}
      completed={typeof summary.completed === "number" ? summary.completed : undefined}
      metrics={compact ? metrics.slice(0, 4) : metrics}
      percentage={summary.utilization ?? undefined}
      subtitle={subtitle}
      title={title ?? t("teamStaff.workload.title")}
      total={summary.capacity ?? summary.totalAssignments ?? undefined}
      variant="elevated"
    />
  );
}
