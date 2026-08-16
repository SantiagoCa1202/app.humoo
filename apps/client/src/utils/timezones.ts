import { isValidTimeZone } from "@/utils/date-time";

function humanizeTimeZone(timeZone: string) {
  return timeZone
    .split("/")
    .map((segment) => segment.replace(/_/g, " "))
    .join(" / ");
}

function getShortTimeZoneName(timeZone: string, locale?: string) {
  try {
    const parts = new Intl.DateTimeFormat(locale, {
      timeZone,
      timeZoneName: "short",
      hour: "2-digit",
      minute: "2-digit",
    }).formatToParts(new Date());

    return parts.find((part) => part.type === "timeZoneName")?.value ?? null;
  } catch {
    return null;
  }
}

export function getSupportedTimeZones(currentValue?: string | null) {
  const supportedValues =
    typeof Intl.supportedValuesOf === "function"
      ? Intl.supportedValuesOf("timeZone")
      : [];
  const fallback = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const candidates = [
    currentValue,
    fallback,
    "UTC",
    ...supportedValues,
  ].filter((value): value is string => Boolean(value) && isValidTimeZone(value));

  return Array.from(new Set(candidates));
}

export function buildTimeZoneOption(timeZone: string, locale?: string) {
  const shortName = getShortTimeZoneName(timeZone, locale);

  return {
    label: humanizeTimeZone(timeZone),
    metadata: shortName && shortName !== timeZone ? `${timeZone} - ${shortName}` : timeZone,
    value: timeZone,
  };
}
