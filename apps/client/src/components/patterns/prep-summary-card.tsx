import { useTranslation } from "react-i18next";

import { ProgressCard, type ProgressMetric } from "@/components/patterns/progress-card";
import {
  calculatePrepProgressPercentage,
  getPrepProgress,
  getPrepRemainingCount,
  getPrepListStatus,
  type PrepDisplayRecord,
  type PrepListProgressRecord,
} from "@/features/prep";

export type PrepSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  metrics?: ProgressMetric[];
  prepList: PrepDisplayRecord;
  progress?: PrepListProgressRecord | null;
};

export function PrepSummaryCard({
  accessibilityLabel,
  compact = false,
  metrics,
  prepList,
  progress,
}: PrepSummaryCardProps) {
  const { t } = useTranslation("common");
  const status = getPrepListStatus(prepList);
  const resolvedProgress = getPrepProgress(prepList, progress);
  const remaining = getPrepRemainingCount(resolvedProgress);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          typeof resolvedProgress.total === "number"
            ? {
                label: t("prep.labels.items"),
                value: t("prep.metrics.items", { count: resolvedProgress.total }),
              }
            : null,
          typeof resolvedProgress.completed === "number"
            ? {
                label: t("prep.labels.completed"),
                value: t("prep.metrics.items", { count: resolvedProgress.completed }),
              }
            : null,
          typeof remaining === "number"
            ? {
                label: t("prep.labels.remaining"),
                value: t("prep.metrics.items", { count: remaining }),
              }
            : null,
          typeof resolvedProgress.inProgress === "number"
            ? {
                label: t("prep.labels.inProgress"),
                value: t("prep.metrics.items", { count: resolvedProgress.inProgress }),
              }
            : null,
          typeof resolvedProgress.blocked === "number"
            ? {
                label: t("prep.labels.blocked"),
                tone: resolvedProgress.blocked > 0 ? "danger" : undefined,
                value: t("prep.metrics.items", { count: resolvedProgress.blocked }),
              }
            : null,
          typeof resolvedProgress.assignedStaffCount === "number"
            ? {
                label: t("prep.labels.assigned"),
                value: t("prep.metrics.staff", { count: resolvedProgress.assignedStaffCount }),
              }
            : null,
          typeof resolvedProgress.unassigned === "number"
            ? {
                label: t("prep.labels.unassigned"),
                value: t("prep.metrics.items", { count: resolvedProgress.unassigned }),
              }
            : null,
        ].filter(Boolean) as ProgressMetric[];

  return (
    <ProgressCard
      accessibilityLabel={accessibilityLabel ?? t("prep.summary.accessibilityLabel")}
      completed={resolvedProgress.completed ?? undefined}
      metrics={compact ? resolvedMetrics.slice(0, 4) : resolvedMetrics}
      percentage={calculatePrepProgressPercentage(resolvedProgress)}
      status={status ?? undefined}
      statusNamespace="prepLists"
      subtitle={prepList.event?.name?.trim() || undefined}
      title={prepList.name}
      total={resolvedProgress.total ?? undefined}
      variant="elevated"
    />
  );
}
