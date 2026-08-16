import { useTranslation } from "react-i18next";

import { ProgressCard, type ProgressMetric } from "@/components/patterns/progress-card";

export type PrepProgressProps = {
  accessibilityLabel?: string;
  blocked?: number | null;
  compact?: boolean;
  done?: number | null;
  inProgress?: number | null;
  showBreakdown?: boolean;
  skipped?: number | null;
  todo?: number | null;
  total?: number | null;
};

export function PrepProgress({
  accessibilityLabel,
  blocked = 0,
  compact = false,
  done = 0,
  inProgress = 0,
  showBreakdown = true,
  skipped = 0,
  todo = 0,
  total = 0,
}: PrepProgressProps) {
  const { t } = useTranslation("common");
  const resolvedBlocked = blocked ?? 0;
  const resolvedDone = done ?? 0;
  const resolvedInProgress = inProgress ?? 0;
  const resolvedSkipped = skipped ?? 0;
  const resolvedTodo = todo ?? 0;
  const safeTotal = (total ?? 0) > 0 ? (total ?? 0) : 0;
  const percentage =
    safeTotal > 0 ? Math.min(100, Math.max(0, (resolvedDone / safeTotal) * 100)) : 0;
  const metrics: ProgressMetric[] = showBreakdown
    ? [
        {
          label: t("prep.labels.todo"),
          value: t("prep.metrics.items", { count: resolvedTodo }),
        },
        {
          label: t("prep.labels.inProgress"),
          value: t("prep.metrics.items", { count: resolvedInProgress }),
        },
        {
          label: t("prep.labels.completed"),
          value: t("prep.metrics.items", { count: resolvedDone }),
        },
        {
          label: t("prep.labels.blocked"),
          tone: resolvedBlocked > 0 ? "danger" : undefined,
          value: t("prep.metrics.items", { count: resolvedBlocked }),
        },
        {
          label: t("prep.labels.skipped"),
          value: t("prep.metrics.items", { count: resolvedSkipped }),
        },
      ]
    : [];

  return (
    <ProgressCard
      accessibilityLabel={accessibilityLabel ?? t("prep.progress.accessibilityLabel")}
      completed={resolvedDone}
      metrics={compact ? metrics.slice(0, 3) : metrics}
      percentage={percentage}
      status={
        resolvedBlocked > 0
          ? "blocked"
          : resolvedDone >= safeTotal && safeTotal > 0
          ? "done"
          : "in_progress"
      }
      statusNamespace="prepTasks"
      subtitle={t("prep.progress.summary", {
        completed: resolvedDone,
        percentage: Math.round(percentage),
        total: safeTotal,
      })}
      title={t("prep.labels.progress")}
      total={safeTotal || undefined}
      variant="default"
    />
  );
}
