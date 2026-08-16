import { Fragment, useMemo } from "react";
import { SectionList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import { EmptyState } from "@/components/patterns/empty-state";
import { EventListItem } from "@/components/patterns/event-list-item";
import {
  buildTimelineSections,
  formatTimelineTimeLabel,
  getDateKeyForValue,
  getEventsForDateKey,
  resolveEventTimeZone,
  sortEventsChronologically,
  type CalendarDateValue,
} from "@/features/events/calendar";
import type { EventDisplayRecord } from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventTimelineProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  date?: CalendarDateValue;
  empty?: React.ReactNode;
  events: EventDisplayRecord[];
  groupByDate?: boolean;
  loading?: boolean;
  onEventPress?: (event: EventDisplayRecord) => void;
  selectedEventId?: string | null;
  timeZone?: string;
};

function EventTimelineSkeleton({ compact = false }: { compact?: boolean }) {
  return (
    <View style={{ gap: 12 }}>
      {Array.from({ length: compact ? 3 : 4 }, (_, index) => (
        <View key={`timeline-skeleton-${index}`} style={{ gap: 8 }}>
          <Skeleton height={14} variant="text" width={80} />
          <SkeletonText gap={8} lineHeight={14} lines={3} />
        </View>
      ))}
    </View>
  );
}

export function EventTimeline({
  accessibilityLabel,
  compact = false,
  date,
  empty,
  events,
  groupByDate = false,
  loading = false,
  onEventPress,
  selectedEventId,
  timeZone,
}: EventTimelineProps) {
  const { i18n, t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTimeZone = resolveEventTimeZone(events, timeZone);
  const normalizedEvents = useMemo(() => {
    if (date) {
      const dateKey = getDateKeyForValue(date, resolvedTimeZone);
      return getEventsForDateKey(events, dateKey, resolvedTimeZone);
    }

    return sortEventsChronologically(events);
  }, [date, events, resolvedTimeZone]);
  const sections = useMemo(
    () =>
      groupByDate
        ? buildTimelineSections(normalizedEvents, i18n.language, resolvedTimeZone, t)
        : [
            {
              data: normalizedEvents,
              dateKey: date ? getDateKeyForValue(date, resolvedTimeZone) : "timeline",
              title: "",
            },
          ],
    [date, groupByDate, i18n.language, normalizedEvents, resolvedTimeZone, t]
  );

  if (loading) {
    return <EventTimelineSkeleton compact={compact} />;
  }

  if (normalizedEvents.length === 0) {
    return (
      <>
        {empty ?? (
          <EmptyState
            accessibilityLabel={accessibilityLabel ?? t("events.timeline.accessibilityLabel")}
            compact
            description={t("events.calendar.empty.description")}
            title={t("events.calendar.empty.title")}
          />
        )}
      </>
    );
  }

  return (
    <SectionList
      accessibilityLabel={accessibilityLabel ?? t("events.timeline.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      keyExtractor={(item) => item.id}
      renderItem={({ item, index, section }) => (
        <Fragment>
          <View
            style={{
              alignItems: "flex-start",
              flexDirection: "row",
              gap: theme.spacing[3],
            }}
          >
            <View style={{ minWidth: compact ? 64 : 84, paddingTop: theme.spacing[2] }}>
              <Text selectable tone="secondary" variant={compact ? "caption" : "bodySmall"}>
                {formatTimelineTimeLabel(item, i18n.language, resolvedTimeZone)}
              </Text>
            </View>
            <View style={{ flex: 1, gap: theme.spacing[2] }}>
              <EventListItem
                event={item}
                onPress={onEventPress ? () => onEventPress(item) : undefined}
                selected={selectedEventId === item.id}
                showStatus
              />
            </View>
          </View>
          {index < section.data.length - 1 ? <Divider spacing="none" /> : null}
        </Fragment>
      )}
      renderSectionHeader={({ section }) =>
        groupByDate ? (
          <View
            style={{
              backgroundColor: theme.colors.background.surface,
              paddingBottom: theme.spacing[2],
              paddingTop: theme.spacing[1],
            }}
          >
            <Text tone="primary" variant={compact ? "caption" : "label"}>
              {section.title}
            </Text>
          </View>
        ) : null
      }
      scrollEnabled={false}
      sections={sections}
      stickySectionHeadersEnabled={false}
    />
  );
}
