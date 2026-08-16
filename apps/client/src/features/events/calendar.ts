import type { EventDisplayRecord } from "@/features/events/presentation";
import { formatIsoForDateTimeInput, isValidTimeZone, localDateTimeInputToIso } from "@/utils/date-time";

export type EventCalendarView = "day" | "month" | "week";
export type CalendarDateValue = Date | string;

export type EventTimelineSection = {
  data: EventDisplayRecord[];
  dateKey: string;
  title: string;
};

type DateKeyParts = {
  day: number;
  month: number;
  year: number;
};

function pad(value: number) {
  return String(value).padStart(2, "0");
}

function parseDateKey(dateKey: string): DateKeyParts {
  const [year, month, day] = dateKey.split("-").map(Number);

  return {
    day,
    month,
    year,
  };
}

function formatDateKey(parts: DateKeyParts) {
  return `${String(parts.year).padStart(4, "0")}-${pad(parts.month)}-${pad(parts.day)}`;
}

function toUtcDateFromDateKey(dateKey: string) {
  const parts = parseDateKey(dateKey);

  return new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 12, 0, 0));
}

export function resolveEventTimeZone(
  events: EventDisplayRecord[],
  timeZone?: string | null
) {
  if (timeZone && isValidTimeZone(timeZone)) {
    return timeZone;
  }

  const eventTimeZone = events.find((event) => isValidTimeZone(event.timezone))?.timezone;

  if (eventTimeZone) {
    return eventTimeZone;
  }

  const fallback = Intl.DateTimeFormat().resolvedOptions().timeZone;

  return isValidTimeZone(fallback) ? fallback : "UTC";
}

export function getDateKeyForValue(value: CalendarDateValue | undefined, timeZone: string) {
  if (!value) {
    return formatIsoForDateTimeInput(new Date().toISOString(), timeZone).slice(0, 10);
  }

  if (value instanceof Date) {
    return formatIsoForDateTimeInput(value.toISOString(), timeZone).slice(0, 10);
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }

  const normalized = formatIsoForDateTimeInput(value, timeZone);

  return normalized ? normalized.slice(0, 10) : formatIsoForDateTimeInput(new Date().toISOString(), timeZone).slice(0, 10);
}

export function addDaysToDateKey(dateKey: string, days: number) {
  const date = toUtcDateFromDateKey(dateKey);
  date.setUTCDate(date.getUTCDate() + days);

  return formatDateKey({
    day: date.getUTCDate(),
    month: date.getUTCMonth() + 1,
    year: date.getUTCFullYear(),
  });
}

export function addMonthsToDateKey(dateKey: string, months: number) {
  const parts = parseDateKey(dateKey);
  const date = new Date(Date.UTC(parts.year, parts.month - 1 + months, 1, 12, 0, 0));

  return formatDateKey({
    day: 1,
    month: date.getUTCMonth() + 1,
    year: date.getUTCFullYear(),
  });
}

export function addWeeksToDateKey(dateKey: string, weeks: number) {
  return addDaysToDateKey(dateKey, weeks * 7);
}

export function getStartOfMonthDateKey(dateKey: string) {
  const parts = parseDateKey(dateKey);

  return formatDateKey({
    ...parts,
    day: 1,
  });
}

export function getStartOfWeekDateKey(dateKey: string, locale?: string) {
  const date = toUtcDateFromDateKey(dateKey);
  const weekStartsOnSunday = locale?.toLowerCase().startsWith("en-us");
  const currentDay = date.getUTCDay();
  const offset = weekStartsOnSunday ? currentDay : (currentDay + 6) % 7;
  date.setUTCDate(date.getUTCDate() - offset);

  return formatDateKey({
    day: date.getUTCDate(),
    month: date.getUTCMonth() + 1,
    year: date.getUTCFullYear(),
  });
}

export function getMonthGridDateKeys(dateKey: string, locale?: string) {
  const monthStart = getStartOfMonthDateKey(dateKey);
  const gridStart = getStartOfWeekDateKey(monthStart, locale);

  return Array.from({ length: 42 }, (_, index) => addDaysToDateKey(gridStart, index));
}

export function getWeekDateKeys(dateKey: string, locale?: string) {
  const start = getStartOfWeekDateKey(dateKey, locale);

  return Array.from({ length: 7 }, (_, index) => addDaysToDateKey(start, index));
}

export function getDayBoundsForDateKey(dateKey: string, timeZone: string) {
  const start = localDateTimeInputToIso(`${dateKey}T00:00`, timeZone);
  const end = localDateTimeInputToIso(`${addDaysToDateKey(dateKey, 1)}T00:00`, timeZone);

  return {
    end,
    start,
  };
}

export function eventOccursOnDateKey(
  event: Pick<EventDisplayRecord, "endsAt" | "startsAt">,
  dateKey: string,
  timeZone: string
) {
  const bounds = getDayBoundsForDateKey(dateKey, timeZone);

  if (!bounds.start || !bounds.end) {
    return false;
  }

  const startTime = new Date(event.startsAt).getTime();
  const endTime = new Date(event.endsAt ?? event.startsAt).getTime();

  return startTime < new Date(bounds.end).getTime() && endTime >= new Date(bounds.start).getTime();
}

export function sortEventsChronologically(events: EventDisplayRecord[]) {
  return [...events].sort((left, right) => {
    const leftStart = new Date(left.startsAt).getTime();
    const rightStart = new Date(right.startsAt).getTime();

    if (leftStart !== rightStart) {
      return leftStart - rightStart;
    }

    const leftEnd = new Date(left.endsAt ?? left.startsAt).getTime();
    const rightEnd = new Date(right.endsAt ?? right.startsAt).getTime();

    return leftEnd - rightEnd;
  });
}

export function getEventsForDateKey(
  events: EventDisplayRecord[],
  dateKey: string,
  timeZone: string
) {
  return sortEventsChronologically(
    events.filter((event) => eventOccursOnDateKey(event, dateKey, timeZone))
  );
}

export function getEventsForRange(
  events: EventDisplayRecord[],
  dateKeys: string[],
  timeZone: string
) {
  const seenIds = new Set<string>();

  return sortEventsChronologically(
    events.filter((event) => {
      if (seenIds.has(event.id)) {
        return false;
      }

      const occurs = dateKeys.some((dateKey) => eventOccursOnDateKey(event, dateKey, timeZone));

      if (occurs) {
        seenIds.add(event.id);
      }

      return occurs;
    })
  );
}

export function formatCalendarDayLabel(dateKey: string, locale: string, timeZone: string) {
  const start = localDateTimeInputToIso(`${dateKey}T12:00`, timeZone);

  if (!start) {
    return dateKey;
  }

  return new Intl.DateTimeFormat(locale, {
    day: "numeric",
  }).format(new Date(start));
}

export function formatCalendarWeekdayLabel(
  dateKey: string,
  locale: string,
  timeZone: string,
  compact = false
) {
  const start = localDateTimeInputToIso(`${dateKey}T12:00`, timeZone);

  if (!start) {
    return dateKey;
  }

  return new Intl.DateTimeFormat(locale, {
    weekday: compact ? "narrow" : "short",
  }).format(new Date(start));
}

export function formatCalendarPeriodLabel(
  dateKey: string,
  locale: string,
  timeZone: string,
  view: EventCalendarView
) {
  const start = localDateTimeInputToIso(`${dateKey}T12:00`, timeZone);

  if (!start) {
    return dateKey;
  }

  const startDate = new Date(start);

  if (view === "day") {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "full",
      timeZone,
    }).format(startDate);
  }

  if (view === "week") {
    const endKey = addDaysToDateKey(getStartOfWeekDateKey(dateKey, locale), 6);
    const end = localDateTimeInputToIso(`${endKey}T12:00`, timeZone);

    if (end) {
      const formatter = new Intl.DateTimeFormat(locale, {
        month: "short",
        day: "numeric",
        timeZone,
      });

      if (typeof formatter.formatRange === "function") {
        return formatter.formatRange(startDate, new Date(end));
      }
    }
  }

  return new Intl.DateTimeFormat(locale, {
    month: "long",
    year: "numeric",
    timeZone,
  }).format(startDate);
}

export function formatTimelineTimeLabel(
  event: EventDisplayRecord,
  locale: string,
  timeZone: string
) {
  const start = new Date(event.startsAt);

  if (Number.isNaN(start.getTime())) {
    return event.startsAt;
  }

  return new Intl.DateTimeFormat(locale, {
    hour: "numeric",
    minute: "2-digit",
    timeZone,
  }).format(start);
}

export function getRelativeDateLabel(
  dateKey: string,
  locale: string,
  timeZone: string,
  t: (key: string) => string
) {
  const todayKey = getDateKeyForValue(new Date(), timeZone);

  if (dateKey === todayKey) {
    return t("events.calendar.relative.today");
  }

  if (dateKey === addDaysToDateKey(todayKey, 1)) {
    return t("events.calendar.relative.tomorrow");
  }

  if (dateKey === addDaysToDateKey(todayKey, -1)) {
    return t("events.calendar.relative.yesterday");
  }

  const start = localDateTimeInputToIso(`${dateKey}T12:00`, timeZone);

  if (!start) {
    return dateKey;
  }

  return new Intl.DateTimeFormat(locale, {
    dateStyle: "full",
    timeZone,
  }).format(new Date(start));
}

export function buildTimelineSections(
  events: EventDisplayRecord[],
  locale: string,
  timeZone: string,
  t: (key: string) => string
) {
  const grouped = new Map<string, EventDisplayRecord[]>();

  sortEventsChronologically(events).forEach((event) => {
    const dateKey = getDateKeyForValue(event.startsAt, timeZone);
    const existing = grouped.get(dateKey) ?? [];
    existing.push(event);
    grouped.set(dateKey, existing);
  });

  return Array.from(grouped.entries()).map(([dateKey, sectionEvents]) => ({
    data: sectionEvents,
    dateKey,
    title: getRelativeDateLabel(dateKey, locale, timeZone, t),
  }));
}
