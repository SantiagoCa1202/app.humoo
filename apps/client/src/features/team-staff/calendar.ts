import {
  addDaysToDateKey,
  addMonthsToDateKey,
  addWeeksToDateKey,
  formatCalendarPeriodLabel,
  formatCalendarWeekdayLabel,
  getDateKeyForValue,
  getMonthGridDateKeys,
  getWeekDateKeys,
  type CalendarDateValue,
} from "@/features/events/calendar";
import type { MemberShiftRecord } from "@/features/team-staff/types";

export type ShiftCalendarView = "day" | "week" | "month";

export function sortShiftsChronologically(shifts: MemberShiftRecord[]) {
  return [...shifts].sort((left, right) => {
    const leftStart = new Date(left.startsAt ?? "").getTime();
    const rightStart = new Date(right.startsAt ?? "").getTime();

    if (leftStart !== rightStart) {
      return leftStart - rightStart;
    }

    const leftEnd = new Date(left.endsAt ?? left.startsAt ?? "").getTime();
    const rightEnd = new Date(right.endsAt ?? right.startsAt ?? "").getTime();

    return leftEnd - rightEnd;
  });
}

export function shiftOccursOnDateKey(
  shift: Pick<MemberShiftRecord, "startsAt" | "endsAt">,
  dateKey: string,
  timeZone: string
) {
  const nextDateKey = addDaysToDateKey(dateKey, 1);
  const startOfDay = new Date(`${dateKey}T00:00:00`);
  const endOfDay = new Date(`${nextDateKey}T00:00:00`);
  const shiftStart = new Date(shift.startsAt ?? "").getTime();
  const shiftEnd = new Date(shift.endsAt ?? shift.startsAt ?? "").getTime();

  if (Number.isNaN(shiftStart) || Number.isNaN(shiftEnd)) {
    return false;
  }

  const zonedStart = getDateKeyForValue(startOfDay, timeZone);
  const zonedEnd = getDateKeyForValue(endOfDay, timeZone);

  return (
    getDateKeyForValue(new Date(shiftStart), timeZone) <= zonedEnd &&
    getDateKeyForValue(new Date(shiftEnd), timeZone) >= zonedStart
  );
}

export function getShiftsForDateKey(
  shifts: MemberShiftRecord[],
  dateKey: string,
  timeZone: string
) {
  return sortShiftsChronologically(
    shifts.filter((shift) => shiftOccursOnDateKey(shift, dateKey, timeZone))
  );
}

export function resolveShiftTimeZone(
  shifts: MemberShiftRecord[],
  timeZone?: string | null
) {
  if (timeZone) {
    return timeZone;
  }

  return (
    shifts.find((shift) => shift.timezone?.trim())?.timezone?.trim() ??
    Intl.DateTimeFormat().resolvedOptions().timeZone ??
    "UTC"
  );
}

export {
  addDaysToDateKey,
  addMonthsToDateKey,
  addWeeksToDateKey,
  formatCalendarPeriodLabel,
  formatCalendarWeekdayLabel,
  getDateKeyForValue,
  getMonthGridDateKeys,
  getWeekDateKeys,
};

export type { CalendarDateValue };
