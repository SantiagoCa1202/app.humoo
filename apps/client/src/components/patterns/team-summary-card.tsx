import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import {
  buildMemberSummary,
  type TeamStaffMemberRecord,
  type TeamStaffSummaryRecord,
} from "@/features/team-staff";

export type TeamSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  members?: TeamStaffMemberRecord[];
  summary?: TeamStaffSummaryRecord | null;
  title?: React.ReactNode;
};

export function TeamSummaryCard({
  accessibilityLabel,
  compact = false,
  members,
  summary,
  title,
}: TeamSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const resolvedSummary = summary ?? (members ? buildMemberSummary(members) : null);
  const metrics: SummaryMetric[] = [
    typeof resolvedSummary?.total === "number"
      ? {
          label: t("teamStaff.labels.members"),
          value: t("teamStaff.metrics.members", { count: resolvedSummary.total }),
        }
      : null,
    typeof resolvedSummary?.active === "number"
      ? {
          label: t("status.active"),
          tone: "success",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.active),
        }
      : null,
    typeof resolvedSummary?.invited === "number"
      ? {
          label: t("status.invited"),
          tone: "primary",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.invited),
        }
      : null,
    typeof resolvedSummary?.available === "number"
      ? {
          label: t("teamStaff.availability.available"),
          tone: "success",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.available),
        }
      : null,
    typeof resolvedSummary?.unavailable === "number"
      ? {
          label: t("teamStaff.availability.unavailable"),
          tone: "warning",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.unavailable),
        }
      : null,
    typeof resolvedSummary?.assigned === "number"
      ? {
          label: t("teamStaff.labels.assigned"),
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.assigned),
        }
      : null,
    typeof resolvedSummary?.overloaded === "number"
      ? {
          label: t("teamStaff.labels.overloaded"),
          tone: "warning",
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.overloaded),
        }
      : null,
    typeof resolvedSummary?.onShift === "number"
      ? {
          label: t("teamStaff.availability.on_shift"),
          value: new Intl.NumberFormat(i18n.language).format(resolvedSummary.onShift),
        }
      : null,
  ].filter(Boolean) as SummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.summary.accessibilityLabel")}
      metrics={compact ? metrics.slice(0, 4) : metrics}
      title={title ?? t("teamStaff.summary.title")}
      variant="elevated"
    />
  );
}
