import { useMemo, useState } from "react";
import { Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { EventTimeline } from "@/components/patterns/event-timeline";
import { Button } from "@/components/primitives/button";
import { Chip } from "@/components/primitives/chip";
import { Divider } from "@/components/primitives/divider";
import { IconButton } from "@/components/primitives/icon-button";
import { Skeleton } from "@/components/primitives/skeleton";
import { Text } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import {
  addDaysToDateKey,
  addMonthsToDateKey,
  addWeeksToDateKey,
  formatCalendarDayLabel,
  formatCalendarPeriodLabel,
  formatCalendarWeekdayLabel,
  getDateKeyForValue,
  getEventsForDateKey,
  getMonthGridDateKeys,
  getWeekDateKeys,
  resolveEventTimeZone,
  type CalendarDateValue,
  type EventCalendarView,
} from "@/features/events/calendar";
import type { EventDisplayRecord } from "@/features/events";
import { humooBreakpoints } from "@/theme";
import { getStatusMetadata, getSemanticToneAppearance } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventCalendarProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  events: EventDisplayRecord[];
  loading?: boolean;
  maxDate?: CalendarDateValue;
  minDate?: CalendarDateValue;
  onEventPress?: (event: EventDisplayRecord) => void;
  onSelectedDateChange?: (date: string) => void;
  onViewChange?: (view: EventCalendarView) => void;
  selectedDate?: CalendarDateValue;
  selectedEventId?: string | null;
  timeZone?: string;
  view?: EventCalendarView;
};

function MonthViewSkeleton() {
  return (
    <View style={{ gap: 12 }}>
      {Array.from({ length: 6 }, (_, row) => (
        <View key={`month-skeleton-${row}`} style={{ flexDirection: "row", gap: 8 }}>
          {Array.from({ length: 7 }, (_, column) => (
            <Skeleton key={`month-skeleton-${row}-${column}`} height={80} width={44} />
          ))}
        </View>
      ))}
    </View>
  );
}

function MonthEventPill({
  compact,
  event,
  onPress,
}: {
  compact: boolean;
  event: EventDisplayRecord;
  onPress?: () => void;
}) {
  const { theme } = useAppTheme();
  const metadata = getStatusMetadata(event.status, "events");
  const appearance = getSemanticToneAppearance(theme, metadata.tone);

  return (
    <Pressable
      accessibilityLabel={event.name}
      accessibilityRole={onPress ? "button" : "text"}
      onPress={onPress}
      style={{
        backgroundColor: appearance.background,
        borderColor: appearance.border,
        borderCurve: "continuous",
        borderRadius: theme.radius.sm,
        borderWidth: 1,
        paddingHorizontal: theme.spacing[1],
        paddingVertical: theme.spacing[1],
      }}
    >
      <Text
        numberOfLines={1}
        variant="caption"
        style={{ color: appearance.accent }}
      >
        {event.name}
      </Text>
    </Pressable>
  );
}

export function EventCalendar({
  accessibilityLabel,
  compact = false,
  disabled = false,
  events,
  loading = false,
  maxDate,
  minDate,
  onEventPress,
  onSelectedDateChange,
  onViewChange,
  selectedDate,
  selectedEventId,
  timeZone,
  view,
}: EventCalendarProps) {
  const { i18n, t } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const resolvedCompact = compact || width < humooBreakpoints.md;
  const resolvedTimeZone = resolveEventTimeZone(events, timeZone);
  const [internalView, setInternalView] = useState<EventCalendarView>(
    resolvedCompact ? "day" : "month"
  );
  const currentView = view ?? internalView;
  const selectedDateKey = getDateKeyForValue(selectedDate, resolvedTimeZone);
  const todayKey = getDateKeyForValue(new Date(), resolvedTimeZone);
  const minDateKey = minDate ? getDateKeyForValue(minDate, resolvedTimeZone) : null;
  const maxDateKey = maxDate ? getDateKeyForValue(maxDate, resolvedTimeZone) : null;
  const monthDateKeys = useMemo(
    () => getMonthGridDateKeys(selectedDateKey, i18n.language),
    [i18n.language, selectedDateKey]
  );
  const weekDateKeys = useMemo(
    () => getWeekDateKeys(selectedDateKey, i18n.language),
    [i18n.language, selectedDateKey]
  );
  const periodLabel = formatCalendarPeriodLabel(
    currentView === "month"
      ? monthDateKeys[7] ?? selectedDateKey
      : currentView === "week"
      ? weekDateKeys[0] ?? selectedDateKey
      : selectedDateKey,
    i18n.language,
    resolvedTimeZone,
    currentView
  );

  const changeView = (nextView: EventCalendarView) => {
    onViewChange?.(nextView);

    if (!view) {
      setInternalView(nextView);
    }
  };

  const changeDate = (nextDateKey: string) => {
    if (minDateKey && nextDateKey < minDateKey) {
      return;
    }

    if (maxDateKey && nextDateKey > maxDateKey) {
      return;
    }

    onSelectedDateChange?.(nextDateKey);
  };

  const stepPeriod = (direction: -1 | 1) => {
    const nextDateKey =
      currentView === "month"
        ? addMonthsToDateKey(selectedDateKey, direction)
        : currentView === "week"
        ? addWeeksToDateKey(selectedDateKey, direction)
        : addDaysToDateKey(selectedDateKey, direction);

    changeDate(nextDateKey);
  };

  const previousDateKey =
    currentView === "month"
      ? addMonthsToDateKey(selectedDateKey, -1)
      : currentView === "week"
      ? addWeeksToDateKey(selectedDateKey, -1)
      : addDaysToDateKey(selectedDateKey, -1);
  const nextDateKey =
    currentView === "month"
      ? addMonthsToDateKey(selectedDateKey, 1)
      : currentView === "week"
      ? addWeeksToDateKey(selectedDateKey, 1)
      : addDaysToDateKey(selectedDateKey, 1);
  const previousDisabled = disabled || Boolean(minDateKey && previousDateKey < minDateKey);
  const nextDisabled = disabled || Boolean(maxDateKey && nextDateKey > maxDateKey);
  const todayDisabled =
    disabled ||
    Boolean((minDateKey && todayKey < minDateKey) || (maxDateKey && todayKey > maxDateKey));

  const selectedDayEvents = getEventsForDateKey(events, selectedDateKey, resolvedTimeZone);

  const renderMonthView = () => (
    <View style={{ gap: theme.spacing[2] }}>
      <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
        {weekDateKeys.map((dateKey) => (
          <View key={`weekday-${dateKey}`} style={{ flex: 1 }}>
            <Text
              tone="muted"
              variant="caption"
              style={{ textAlign: "center" }}
            >
              {formatCalendarWeekdayLabel(dateKey, i18n.language, resolvedTimeZone, true)}
            </Text>
          </View>
        ))}
      </View>
      {Array.from({ length: 6 }, (_, weekIndex) => {
        const row = monthDateKeys.slice(weekIndex * 7, weekIndex * 7 + 7);

        return (
          <View key={`calendar-week-${weekIndex}`} style={{ flexDirection: "row", gap: theme.spacing[2] }}>
            {row.map((dateKey) => {
              const dayEvents = getEventsForDateKey(events, dateKey, resolvedTimeZone);
              const visibleEvents = dayEvents.slice(0, resolvedCompact ? 1 : 2);
              const remaining = Math.max(dayEvents.length - visibleEvents.length, 0);
              const isSelected = dateKey === selectedDateKey;
              const isToday = dateKey === todayKey;
              const dayParts = dateKey.split("-")[2];
              const isCurrentMonth = dateKey.slice(0, 7) === selectedDateKey.slice(0, 7);
              const isDisabled =
                disabled ||
                Boolean((minDateKey && dateKey < minDateKey) || (maxDateKey && dateKey > maxDateKey));

              return (
                <Pressable
                  accessibilityHint={t("events.calendar.accessibility.selectDateHint")}
                  accessibilityLabel={t("events.calendar.accessibility.date", {
                    date: formatCalendarPeriodLabel(dateKey, i18n.language, resolvedTimeZone, "day"),
                  })}
                  accessibilityRole="button"
                  disabled={isDisabled}
                  key={dateKey}
                  onPress={() => changeDate(dateKey)}
                  style={{
                    backgroundColor: isSelected
                      ? theme.colors.brand.soft
                      : isToday
                      ? theme.colors.background.subtle
                      : theme.colors.background.surface,
                    borderColor: isSelected
                      ? theme.colors.brand.primary
                      : isToday
                      ? theme.colors.brand.primary
                      : theme.colors.border.default,
                    borderCurve: "continuous",
                    borderRadius: theme.radius.md,
                    borderWidth: 1,
                    flex: 1,
                    gap: theme.spacing[1],
                    minHeight: resolvedCompact ? theme.spacing[16] : theme.spacing[16] + theme.spacing[4],
                    opacity: isDisabled ? 0.65 : 1,
                    padding: theme.spacing[2],
                  }}
                >
                  <Text
                    tone={isCurrentMonth ? (isSelected ? "primary" : "default") : "muted"}
                    variant={resolvedCompact ? "caption" : "bodySmall"}
                    style={{ textAlign: "right" }}
                  >
                    {Number(dayParts)}
                  </Text>
                  <View style={{ gap: theme.spacing[1] }}>
                    {visibleEvents.map((event) => (
                      <MonthEventPill
                        compact={resolvedCompact}
                        event={event}
                        key={event.id}
                        onPress={onEventPress ? () => onEventPress(event) : undefined}
                      />
                    ))}
                    {remaining > 0 ? (
                      <Text tone="secondary" variant="caption">
                        {t("events.calendar.more", { count: remaining })}
                      </Text>
                    ) : null}
                  </View>
                </Pressable>
              );
            })}
          </View>
        );
      })}
    </View>
  );

  const renderWeekView = () => (
    <ScrollView horizontal={!resolvedCompact} showsHorizontalScrollIndicator={false}>
      <View
        style={{
          flexDirection: resolvedCompact ? "column" : "row",
          gap: theme.spacing[3],
          minWidth: "100%",
        }}
      >
        {weekDateKeys.map((dateKey) => {
          const dayEvents = getEventsForDateKey(events, dateKey, resolvedTimeZone);

          return (
            <View
              key={`week-day-${dateKey}`}
              style={{
                flex: 1,
                gap: theme.spacing[2],
                minWidth: resolvedCompact ? "100%" : 260,
              }}
            >
              <Pressable
                disabled={disabled}
                accessibilityRole="button"
                onPress={() => changeDate(dateKey)}
                style={{
                  gap: theme.spacing[1],
                  opacity: disabled ? 0.7 : 1,
                }}
              >
                <Text tone={dateKey === selectedDateKey ? "primary" : "secondary"} variant="label">
                  {formatCalendarWeekdayLabel(dateKey, i18n.language, resolvedTimeZone)}
                </Text>
                <Text variant="bodySmall">
                  {formatCalendarPeriodLabel(dateKey, i18n.language, resolvedTimeZone, "day")}
                </Text>
              </Pressable>
              <Divider spacing="none" />
              <EventTimeline
                compact
                date={dateKey}
                events={dayEvents}
                onEventPress={onEventPress}
                selectedEventId={selectedEventId}
                timeZone={resolvedTimeZone}
              />
            </View>
          );
        })}
      </View>
    </ScrollView>
  );

  const renderDayView = () => (
    <EventTimeline
      compact={resolvedCompact}
      date={selectedDateKey}
      events={selectedDayEvents}
      onEventPress={onEventPress}
      selectedEventId={selectedEventId}
      timeZone={resolvedTimeZone}
    />
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("events.calendar.accessibilityLabel")}
      style={{ gap: theme.spacing[4] }}
    >
      <View
        style={{
          alignItems: "center",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[2],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
          <Tooltip content={t("events.calendar.actions.previous")}>
            <IconButton
              accessibilityLabel={t("events.calendar.actions.previous")}
              disabled={previousDisabled}
              icon={<Text variant="bodySmall">{"<"}</Text>}
              onPress={() => stepPeriod(-1)}
              size="sm"
              variant="ghost"
            />
          </Tooltip>
          <Tooltip content={t("events.calendar.actions.next")}>
            <IconButton
              accessibilityLabel={t("events.calendar.actions.next")}
              disabled={nextDisabled}
              icon={<Text variant="bodySmall">{">"}</Text>}
              onPress={() => stepPeriod(1)}
              size="sm"
              variant="ghost"
            />
          </Tooltip>
          <Button
            disabled={todayDisabled}
            label={t("events.calendar.actions.today")}
            onPress={() => changeDate(todayKey)}
            size="sm"
            variant="secondary"
          />
        </View>
        <Text selectable variant={resolvedCompact ? "title" : "h4"}>
          {periodLabel}
        </Text>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {(["month", "week", "day"] as EventCalendarView[]).map((option) => (
            <Chip
              disabled={disabled}
              key={option}
              label={t(`events.calendar.views.${option}`)}
              onPress={() => changeView(option)}
              selected={currentView === option}
              size="sm"
              variant="neutral"
            />
          ))}
        </View>
      </View>
      {loading ? (
        currentView === "month" ? (
          <MonthViewSkeleton />
        ) : (
          <EventTimeline compact={resolvedCompact} events={[]} loading />
        )
      ) : currentView === "month" ? (
        renderMonthView()
      ) : currentView === "week" ? (
        renderWeekView()
      ) : selectedDayEvents.length > 0 ? (
        renderDayView()
      ) : (
        <EmptyState
          compact
          description={t("events.calendar.empty.description")}
          title={t("events.calendar.empty.title")}
        />
      )}
    </View>
  );
}
