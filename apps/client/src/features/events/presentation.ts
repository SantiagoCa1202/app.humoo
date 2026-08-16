import type { ImageSourcePropType } from "react-native";

import type { AvatarProps } from "@/components/primitives/avatar";
import type { EventRecord } from "@/features/events/types";

export type EventNamedValue =
  | string
  | {
      id?: string;
      label?: string | null;
      name?: string | null;
    };

export type EventTagValue =
  | string
  | {
      id?: string;
      label: string;
    };

export type EventMemberValue = {
  id?: string;
  name?: string | null;
  source?: ImageSourcePropType;
  status?: AvatarProps["status"];
  variant?: AvatarProps["variant"];
};

export type EventDisplayRecord = EventRecord & {
  beoReference?: string | null;
  client?: EventNamedValue | null;
  eventGroup?: string | null;
  menuReference?: string | null;
  responsibleMembers?: EventMemberValue[];
  staff?: EventMemberValue[];
  tags?: EventTagValue[];
  venue?: EventNamedValue | null;
};

export function getEventNamedValue(value?: EventNamedValue | null) {
  if (!value) {
    return null;
  }

  if (typeof value === "string") {
    return value;
  }

  return value.name?.trim() || value.label?.trim() || null;
}

export function getEventTagLabel(tag: EventTagValue) {
  return typeof tag === "string" ? tag : tag.label;
}

export function getEventStaff(event: EventDisplayRecord) {
  return event.responsibleMembers?.length
    ? event.responsibleMembers
    : event.staff?.length
    ? event.staff
    : [];
}

export function formatEventDateRange(
  event: Pick<EventDisplayRecord, "startsAt" | "endsAt" | "timezone">,
  locale?: string
) {
  const start = new Date(event.startsAt);

  if (Number.isNaN(start.getTime())) {
    return event.startsAt;
  }

  const options: Intl.DateTimeFormatOptions = {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: event.timezone,
  };

  try {
    const formatter = new Intl.DateTimeFormat(locale, options);

    if (event.endsAt) {
      const end = new Date(event.endsAt);

      if (!Number.isNaN(end.getTime()) && typeof formatter.formatRange === "function") {
        return formatter.formatRange(start, end);
      }
    }

    return formatter.format(start);
  } catch {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(start);
  }
}

export function formatEventGuestCount(
  guestCountExpected: number | null,
  locale?: string
) {
  if (guestCountExpected === null || guestCountExpected === undefined) {
    return null;
  }

  return new Intl.NumberFormat(locale).format(guestCountExpected);
}

