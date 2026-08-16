import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { EventStatusBadge } from "@/components/patterns/event-status-badge";
import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventStaff,
  type EventDisplayRecord,
} from "@/features/events";

export type EventSummaryMetric = SummaryMetric & {
  id?: string;
};

export type EventSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  event: EventDisplayRecord;
  metrics?: EventSummaryMetric[];
};

export function EventSummaryCard({
  accessibilityLabel,
  compact = false,
  event,
  metrics,
}: EventSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const guests = formatEventGuestCount(event.guestCountExpected, i18n.language);
  const schedule = formatEventDateRange(event, i18n.language);
  const staff = getEventStaff(event);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          guests
            ? {
                id: "guests",
                label: t("events.labels.guests"),
                value: guests,
              }
            : null,
          staff.length
            ? {
                id: "staff",
                label: t("events.labels.staff"),
                value: new Intl.NumberFormat(i18n.language).format(staff.length),
              }
            : null,
          {
            id: "time",
            label: t("events.labels.time"),
            value: schedule,
            tone: "default" as const,
          },
        ].filter(Boolean) as EventSummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("events.summary.accessibilityLabel")}
      metrics={compact ? resolvedMetrics.slice(0, 2) : resolvedMetrics}
      subtitle={schedule}
      title={event.name}
      trailing={<EventStatusBadge size="sm" status={event.status} />}
      variant="elevated"
    />
  );
}
