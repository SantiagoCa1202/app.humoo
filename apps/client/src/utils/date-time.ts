type LocalDateTimeParts = {
  day: number;
  hour: number;
  minute: number;
  month: number;
  year: number;
};

const formatterCache = new Map<string, Intl.DateTimeFormat>();
const LOCAL_DATE_TIME_PATTERN =
  /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/;

function getFormatter(timeZone: string) {
  const cacheKey = timeZone;
  const cached = formatterCache.get(cacheKey);

  if (cached) {
    return cached;
  }

  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  });

  formatterCache.set(cacheKey, formatter);

  return formatter;
}

function getZonedParts(date: Date, timeZone: string): LocalDateTimeParts {
  const formatter = getFormatter(timeZone);
  const formattedParts = formatter.formatToParts(date);

  const values = formattedParts.reduce<Partial<Record<string, number>>>(
    (result, part) => {
      if (part.type === "year" || part.type === "month" || part.type === "day") {
        result[part.type] = Number(part.value);
      }

      if (part.type === "hour" || part.type === "minute") {
        result[part.type] = Number(part.value);
      }

      return result;
    },
    {}
  );

  return {
    day: values.day ?? 1,
    hour: values.hour ?? 0,
    minute: values.minute ?? 0,
    month: values.month ?? 1,
    year: values.year ?? 1970,
  };
}

function getComparableTimestamp(parts: LocalDateTimeParts) {
  return Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, 0);
}

export function isValidTimeZone(timeZone?: string | null) {
  if (!timeZone) {
    return false;
  }

  try {
    new Intl.DateTimeFormat("en-US", { timeZone }).format(new Date());
    return true;
  } catch {
    return false;
  }
}

export function parseLocalDateTimeInput(value: string) {
  const match = LOCAL_DATE_TIME_PATTERN.exec(value.trim());

  if (!match) {
    return null;
  }

  const [, year, month, day, hour, minute] = match;

  return {
    day: Number(day),
    hour: Number(hour),
    minute: Number(minute),
    month: Number(month),
    year: Number(year),
  } satisfies LocalDateTimeParts;
}

export function formatIsoForDateTimeInput(value?: string | null, timeZone?: string | null) {
  if (!value || !timeZone || !isValidTimeZone(timeZone)) {
    return "";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  const parts = getZonedParts(date, timeZone);
  const year = String(parts.year).padStart(4, "0");
  const month = String(parts.month).padStart(2, "0");
  const day = String(parts.day).padStart(2, "0");
  const hour = String(parts.hour).padStart(2, "0");
  const minute = String(parts.minute).padStart(2, "0");

  return `${year}-${month}-${day}T${hour}:${minute}`;
}

export function localDateTimeInputToIso(value: string, timeZone: string) {
  const parsed = parseLocalDateTimeInput(value);

  if (!parsed || !isValidTimeZone(timeZone)) {
    return null;
  }

  const desiredTimestamp = getComparableTimestamp(parsed);
  let utcGuess = desiredTimestamp;

  for (let iteration = 0; iteration < 4; iteration += 1) {
    const zonedGuess = getZonedParts(new Date(utcGuess), timeZone);
    const zonedTimestamp = getComparableTimestamp(zonedGuess);
    const difference = desiredTimestamp - zonedTimestamp;

    utcGuess += difference;

    if (difference === 0) {
      break;
    }
  }

  const resolved = new Date(utcGuess);

  if (Number.isNaN(resolved.getTime())) {
    return null;
  }

  return resolved.toISOString();
}

export function formatDateTimePreview(
  value?: string | null,
  locale?: string,
  timeZone?: string | null
) {
  if (!value || !timeZone || !isValidTimeZone(timeZone)) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone,
    }).format(date);
  } catch {
    return null;
  }
}

export function getNextRoundedIsoDateTime(hoursAhead = 2) {
  const nextDate = new Date();
  nextDate.setMinutes(0, 0, 0);
  nextDate.setHours(nextDate.getHours() + hoursAhead);
  return nextDate.toISOString();
}
