import type { ImageSourcePropType } from "react-native";

import type { AvatarProps } from "@/components/primitives/avatar";
import type { ComparisonChange } from "@/components/patterns/comparison-card";
import type { EventRecord } from "@/features/events/types";
import type { WorkspaceMemberStatus } from "@/theme/status-config";

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
  presence?: AvatarProps["status"];
  source?: ImageSourcePropType;
  variant?: AvatarProps["variant"];
};

export type EventAddressValue = {
  address?: string | null;
  addressLine1?: string | null;
  addressLine2?: string | null;
  city?: string | null;
  country?: string | null;
  postalCode?: string | null;
  region?: string | null;
};

export type EventContactValue = {
  email?: string | null;
  id?: string;
  name?: string | null;
  organization?: string | null;
  phone?: string | null;
  role?: string | null;
  roleTranslationKey?: string | null;
  source?: ImageSourcePropType;
  title?: string | null;
};

export type EventVenueValue = {
  address?: EventAddressValue | null;
  contact?: EventContactValue | null;
  id?: string;
  name?: string | null;
  notes?: string | null;
  room?: string | null;
  summary?: string | null;
};

export type EventClientValue = {
  company?: string | null;
  contact?: EventContactValue | null;
  email?: string | null;
  id?: string;
  metadata?: string | null;
  name?: string | null;
  organization?: string | null;
  phone?: string | null;
  source?: ImageSourcePropType;
};

export type EventStaffMemberValue = EventMemberValue & {
  assignment?: string | null;
  membershipStatus?: WorkspaceMemberStatus;
  role?: string | null;
  roleTranslationKey?: string | null;
  workspaceMembershipId?: string | null;
};

export type EventConflictType =
  | "version_conflict"
  | "remote_update"
  | "beo_change"
  | "stale_data";

export type EventConflictChange = ComparisonChange;

export type EventDisplayRecord = EventRecord & {
  beoReference?: string | null;
  client?: EventNamedValue | EventClientValue | null;
  contact?: EventContactValue | null;
  eventGroup?: string | null;
  menuReference?: string | null;
  responsibleMembers?: EventStaffMemberValue[];
  staff?: EventStaffMemberValue[];
  tags?: EventTagValue[];
  venue?: EventNamedValue | EventVenueValue | null;
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

function isEventClientValue(
  client?: EventNamedValue | EventClientValue | null
): client is EventClientValue {
  return Boolean(
    client &&
      typeof client !== "string" &&
      ("company" in client ||
        "organization" in client ||
        "contact" in client ||
        "email" in client ||
        "phone" in client ||
        "metadata" in client ||
        "source" in client)
  );
}

function isEventVenueValue(
  venue?: EventNamedValue | EventVenueValue | null
): venue is EventVenueValue {
  return Boolean(
    venue &&
      typeof venue !== "string" &&
      ("address" in venue || "contact" in venue || "room" in venue || "summary" in venue)
  );
}

export function getEventContactName(contact?: EventContactValue | null) {
  return contact?.name?.trim() || null;
}

export function getEventContactRole(contact?: EventContactValue | null) {
  return contact?.title?.trim() || contact?.role?.trim() || null;
}

export function getEventContactOrganization(contact?: EventContactValue | null) {
  return contact?.organization?.trim() || null;
}

export function getEventClientName(
  client?: EventNamedValue | EventClientValue | null
) {
  if (!client) {
    return null;
  }

  if (!isEventClientValue(client)) {
    return getEventNamedValue(client as EventNamedValue);
  }

  return client.name?.trim() || client.organization?.trim() || client.company?.trim() || null;
}

export function getEventClientOrganization(
  client?: EventNamedValue | EventClientValue | null
) {
  if (!isEventClientValue(client)) {
    return null;
  }

  return client.organization?.trim() || client.company?.trim() || null;
}

export function getEventVenueName(venue?: EventNamedValue | EventVenueValue | null) {
  if (!venue) {
    return null;
  }

  if (!isEventVenueValue(venue)) {
    return getEventNamedValue(venue as EventNamedValue);
  }

  return venue.name?.trim() || null;
}

export function formatEventAddress(address?: EventAddressValue | null) {
  if (!address) {
    return [];
  }

  const primaryLine = address.address?.trim()
    ? [address.address.trim()]
    : [address.addressLine1?.trim(), address.addressLine2?.trim()].filter(Boolean);
  const locality = [address.city?.trim(), address.region?.trim(), address.postalCode?.trim()]
    .filter(Boolean)
    .join(", ");
  const lines = [...primaryLine, locality || null, address.country?.trim() || null].filter(
    Boolean
  );

  return lines as string[];
}

export function getEventVenueSummary(venue?: EventNamedValue | EventVenueValue | null) {
  if (!isEventVenueValue(venue)) {
    return null;
  }

  return venue.summary?.trim() || venue.notes?.trim() || null;
}

export function getEventVenueRoom(venue?: EventNamedValue | EventVenueValue | null) {
  if (!isEventVenueValue(venue)) {
    return null;
  }

  return venue.room?.trim() || null;
}

export function getEventVenueContact(venue?: EventNamedValue | EventVenueValue | null) {
  if (!isEventVenueValue(venue)) {
    return null;
  }

  return venue.contact ?? null;
}

export function getEventVenueAddress(venue?: EventNamedValue | EventVenueValue | null) {
  if (!isEventVenueValue(venue)) {
    return [];
  }

  return formatEventAddress(venue.address);
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

export function getEventStaffRole(member?: EventStaffMemberValue | null) {
  return member?.assignment?.trim() || member?.role?.trim() || null;
}
