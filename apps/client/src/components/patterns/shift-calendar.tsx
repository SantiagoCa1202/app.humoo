import { useMemo, useState } from "react";
import { Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ShiftCard } from "@/components/patterns/shift-card";
import { Button } from "@/components/primitives/button";
import { Chip } from "@/components/primitives/chip";
import { Divider } from "@/components/primitives/divider";
import { IconButton } from "@/components/primitives/icon-button";
import { Skeleton } from "@/components/primitives/skeleton";
import { Text } from "@/components/primitives/text";
import {
  addDaysToDateKey,
  addMonthsToDateKey,
  addWeeksToDateKey,
  formatCalendarPeriodLabel,
  formatCalendarWeekdayLabel,
  getDateKeyForValue,
  getMonthGridDateKeys,
  getShiftsForDateKey,
  getWeekDateKeys,
  resolveShiftTimeZone,
  type CalendarDateValue,
  type MemberShiftRecord,
  type ShiftCalendarView,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { humooBreakpoints } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ShiftCalendarProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  loading?: boolean;
  members?: TeamStaffMemberRecord[];
  onDateChange?: (dateKey: string) => void;
  onMemberPress?: (member: TeamStaffMemberRecord) => void;
  onShiftPress?: (shift: MemberShiftRecord) => void;
  onViewChange?: (view: ShiftCalendarView) => void;
  selectedDate?: CalendarDateValue;
  selectedShiftId?: string | null;
  shifts: MemberShiftRecord[];
  timeZone?: string;
  view?: ShiftCalendarView;
};

function MonthViewSkeleton() {
  return (
    <View style={{ gap: 12 }}>
      {Array.from({ length: 6 }, (_, row) => (
        <View key={`shift-month-skeleton-${row}`} style={{ flexDirection: "row", gap: 8 }}>
          {Array.from({ length: 7 }, (_, column) => (
            <Skeleton key={`shift-month-skeleton-${row}-${column}`} height={80} width={44} />
          ))}
        </View>
      ))}
    </View>
  );
}

export function ShiftCalendar({
  accessibilityLabel,
  compact = false,
  error,
  loading = false,
  onDateChange,
  onShiftPress,
  onViewChange,
  selectedDate,
  selectedShiftId,
  shifts,
  timeZone,
  view,
}: ShiftCalendarProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const resolvedCompact = compact || width < humooBreakpoints.md;
  const resolvedTimeZone = resolveShiftTimeZone(shifts, timeZone);
  const [internalView, setInternalView] = useState<ShiftCalendarView>(
    resolvedCompact ? "day" : "week"
  );
  const currentView = view ?? internalView;
  const selectedDateKey = getDateKeyForValue(selectedDate, resolvedTimeZone);
  const todayKey = getDateKeyForValue(new Date(), resolvedTimeZone);
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

  const changeView = (nextView: ShiftCalendarView) => {
    onViewChange?.(nextView);

    if (!view) {
      setInternalView(nextView);
    }
  };

  const changeDate = (nextDateKey: string) => {
    onDateChange?.(nextDateKey);
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

  const selectedDayShifts = getShiftsForDateKey(shifts, selectedDateKey, resolvedTimeZone);

  const renderShiftList = (items: MemberShiftRecord[]) =>
    items.length ? (
      <View style={{ gap: theme.spacing[3] }}>
        {items.map((shift) => (
          <ShiftCard
            compact
            key={shift.id ?? `${shift.membershipId}-${shift.startsAt}`}
            onPress={onShiftPress ? () => void onShiftPress(shift) : undefined}
            selected={selectedShiftId === shift.id}
            shift={shift}
          />
        ))}
      </View>
    ) : (
      <EmptyState
        compact
        description={t("teamStaff.shiftCalendar.empty.description")}
        title={t("teamStaff.shiftCalendar.empty.title")}
      />
    );

  const renderMonthView = () => (
    <View style={{ gap: theme.spacing[2] }}>
      <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
        {weekDateKeys.map((dateKey) => (
          <View key={`shift-weekday-${dateKey}`} style={{ flex: 1 }}>
            <Text tone="muted" variant="caption" style={{ textAlign: "center" }}>
              {formatCalendarWeekdayLabel(dateKey, i18n.language, resolvedTimeZone, true)}
            </Text>
          </View>
        ))}
      </View>
      {Array.from({ length: 6 }, (_, weekIndex) => {
        const row = monthDateKeys.slice(weekIndex * 7, weekIndex * 7 + 7);

        return (
          <View
            key={`shift-calendar-week-${weekIndex}`}
            style={{ flexDirection: "row", gap: theme.spacing[2] }}
          >
            {row.map((dateKey) => {
              const dayShifts = getShiftsForDateKey(shifts, dateKey, resolvedTimeZone);
              const visibleShifts = dayShifts.slice(0, resolvedCompact ? 1 : 2);
              const remaining = Math.max(dayShifts.length - visibleShifts.length, 0);
              const isSelected = dateKey === selectedDateKey;
              const isToday = dateKey === todayKey;

              return (
                <Pressable
                  accessibilityHint={t("teamStaff.shiftCalendar.accessibility.selectDateHint")}
                  accessibilityLabel={t("teamStaff.shiftCalendar.accessibility.date", {
                    date: formatCalendarPeriodLabel(
                      dateKey,
                      i18n.language,
                      resolvedTimeZone,
                      "day"
                    ),
                  })}
                  accessibilityRole="button"
                  key={dateKey}
                  onPress={() => changeDate(dateKey)}
                  style={{
                    backgroundColor: isSelected
                      ? theme.colors.brand.soft
                      : isToday
                      ? theme.colors.background.subtle
                      : theme.colors.background.surface,
                    borderColor: isSelected || isToday
                      ? theme.colors.brand.primary
                      : theme.colors.border.default,
                    borderCurve: "continuous",
                    borderRadius: theme.radius.md,
                    borderWidth: 1,
                    flex: 1,
                    gap: theme.spacing[1],
                    minHeight: theme.spacing[16],
                    padding: theme.spacing[2],
                  }}
                >
                  <Text
                    tone={isSelected ? "primary" : "default"}
                    variant={resolvedCompact ? "caption" : "bodySmall"}
                    style={{ textAlign: "right" }}
                  >
                    {Number(dateKey.split("-")[2])}
                  </Text>
                  <View style={{ gap: theme.spacing[1] }}>
                    {visibleShifts.map((shift) => (
                      <Text
                        key={shift.id ?? `${shift.membershipId}-${shift.startsAt}`}
                        numberOfLines={1}
                        tone="secondary"
                        variant="caption"
                      >
                        {shift.member?.name?.trim() ??
                          shift.station?.name?.trim() ??
                          t("teamStaff.shift.fallbackTitle")}
                      </Text>
                    ))}
                    {remaining > 0 ? (
                      <Text tone="muted" variant="caption">
                        {t("teamStaff.shiftCalendar.more", { count: remaining })}
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
          const dayShifts = getShiftsForDateKey(shifts, dateKey, resolvedTimeZone);

          return (
            <View
              key={`shift-day-${dateKey}`}
              style={{
                flex: 1,
                gap: theme.spacing[2],
                minWidth: resolvedCompact ? "100%" : 260,
              }}
            >
              <Pressable
                accessibilityRole="button"
                onPress={() => changeDate(dateKey)}
                style={{ gap: theme.spacing[1] }}
              >
                <Text tone={dateKey === selectedDateKey ? "primary" : "secondary"} variant="label">
                  {formatCalendarWeekdayLabel(dateKey, i18n.language, resolvedTimeZone)}
                </Text>
                <Text variant="bodySmall">
                  {formatCalendarPeriodLabel(dateKey, i18n.language, resolvedTimeZone, "day")}
                </Text>
              </Pressable>
              <Divider spacing="none" />
              {renderShiftList(dayShifts)}
            </View>
          );
        })}
      </View>
    </ScrollView>
  );

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("teamStaff.shiftCalendar.errorTitle")}
      />
    );
  }

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.shiftCalendar.accessibilityLabel")}
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
          <IconButton
            accessibilityLabel={t("teamStaff.shiftCalendar.actions.previous")}
            icon={<Text variant="bodySmall">{"<"}</Text>}
            onPress={() => stepPeriod(-1)}
            size="sm"
            variant="ghost"
          />
          <IconButton
            accessibilityLabel={t("teamStaff.shiftCalendar.actions.next")}
            icon={<Text variant="bodySmall">{">"}</Text>}
            onPress={() => stepPeriod(1)}
            size="sm"
            variant="ghost"
          />
          <Button
            label={t("teamStaff.shiftCalendar.actions.today")}
            onPress={() => changeDate(todayKey)}
            size="sm"
            variant="secondary"
          />
        </View>
        <Text selectable variant={resolvedCompact ? "title" : "h4"}>
          {periodLabel}
        </Text>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {(["month", "week", "day"] as ShiftCalendarView[]).map((option) => (
            <Chip
              key={option}
              label={t(`teamStaff.shiftCalendar.views.${option}`)}
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
          <View style={{ gap: theme.spacing[3] }}>
            <Skeleton height={theme.spacing[16]} />
            <Skeleton height={theme.spacing[16]} />
          </View>
        )
      ) : currentView === "month" ? (
        renderMonthView()
      ) : currentView === "week" ? (
        renderWeekView()
      ) : renderShiftList(selectedDayShifts)}
    </View>
  );
}
