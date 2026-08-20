import { apiRequest } from "@/api/client";
import type {
  EventClientValue,
  EventContactValue,
  EventRecord,
  EventVenueValue,
} from "@/features/events/types";
import type {
  CreateEventInput,
  EventsCursorPage,
  UpdateEventInput,
} from "@/features/events/types";

type ApiEvent = {
  id: string;
  workspace_id: string;
  event_group_id: string | null;
  client_id: string | null;
  contact_id: string | null;
  venue_id: string | null;
  lead_membership_id: string | null;
  name: string;
  event_number: string | null;
  description: string | null;
  starts_at: string;
  ends_at: string | null;
  timezone: string;
  guest_count_expected: number | null;
  guest_count_confirmed: number | null;
  service_type: string | null;
  event_type: string | null;
  status: EventRecord["status"];
  priority: EventRecord["priority"];
  notes: string | null;
  version: number;
  cancelled_at: string | null;
  completed_at: string | null;
  event_group: {
    id: string;
    name: string;
    status: string | null;
  } | null;
  client: {
    id: string | null;
    name: string | null;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    status: string | null;
    primary_contact: {
      id: string;
      display_name: string | null;
      full_name: string;
      email: string | null;
      phone: string | null;
      job_title: string | null;
      contact_type: string | null;
      is_primary: boolean;
    } | null;
  } | null;
  contact: {
    id: string | null;
    client_id: string | null;
    display_name: string | null;
    full_name: string | null;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    contact_type: string | null;
    organization: string | null;
  } | null;
  venue: {
    id: string | null;
    name: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country_code: string | null;
    timezone: string | null;
    contact_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    notes: string | null;
  } | null;
  created_at: string | null;
  updated_at: string | null;
};

type ApiEventsCursorPage = {
  data: ApiEvent[];
  path: string;
  per_page: number;
  next_cursor: string | null;
  next_page_url: string | null;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

type EventResponse = {
  data: ApiEvent;
};

export type EventListFilters = {
  clientId?: string | null;
  cursor?: string | null;
  dateFrom?: string | null;
  dateTo?: string | null;
  perPage?: number;
  search?: string;
  serviceType?: string | null;
  statuses?: EventRecord["status"][];
  venueId?: string | null;
};

export async function listEvents(
  authToken: string,
  workspaceId: string,
  filters: EventListFilters = {}
): Promise<EventsCursorPage> {
  const response = await apiRequest<ApiEventsCursorPage>("/events", {
    authToken,
    query: {
      cursor: filters.cursor ?? undefined,
      date_from: filters.dateFrom ?? undefined,
      date_to: filters.dateTo ?? undefined,
      client_id: filters.clientId ?? undefined,
      per_page: filters.perPage ?? undefined,
      search: filters.search?.trim() || undefined,
      service_type: filters.serviceType ?? undefined,
      status: filters.statuses?.length ? filters.statuses : undefined,
      venue_id: filters.venueId ?? undefined,
    },
    workspaceId,
  });

  return {
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
    data: response.data.map(mapEvent),
  };
}

export async function getEvent(
  authToken: string,
  workspaceId: string,
  eventId: string
): Promise<EventRecord> {
  const response = await apiRequest<EventResponse>(`/events/${eventId}`, {
    authToken,
    workspaceId,
  });

  return mapEvent(response.data);
}

export async function createEvent(
  authToken: string,
  workspaceId: string,
  input: CreateEventInput
): Promise<EventRecord> {
  const response = await apiRequest<EventResponse>("/events", {
    method: "POST",
    authToken,
    workspaceId,
    body: JSON.stringify({
      client_id: input.clientId,
      contact_id: input.contactId,
      event_group_id: input.eventGroupId,
      name: input.name,
      starts_at: input.startsAt,
      ends_at: input.endsAt,
      timezone: input.timezone,
      status: input.status,
      priority: input.priority,
      guest_count_expected: input.guestCountExpected,
      service_type: input.serviceType,
      event_type: input.eventType,
      notes: input.notes,
      venue_id: input.venueId,
    }),
  });

  return mapEvent(response.data);
}

export async function updateEvent(
  authToken: string,
  workspaceId: string,
  eventId: string,
  input: UpdateEventInput
): Promise<EventRecord> {
  const response = await apiRequest<EventResponse>(`/events/${eventId}`, {
    method: "PATCH",
    authToken,
    workspaceId,
    body: JSON.stringify({
      client_id: input.clientId,
      contact_id: input.contactId,
      event_group_id: input.eventGroupId,
      ends_at: input.endsAt,
      event_type: input.eventType,
      guest_count_expected: input.guestCountExpected,
      name: input.name,
      notes: input.notes,
      priority: input.priority,
      service_type: input.serviceType,
      starts_at: input.startsAt,
      status: input.status,
      timezone: input.timezone,
      venue_id: input.venueId,
      version: input.version,
    }),
  });

  return mapEvent(response.data);
}

export async function deleteEvent(
  authToken: string,
  workspaceId: string,
  eventId: string
): Promise<void> {
  await apiRequest<null>(`/events/${eventId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

export function coerceEventRecord(value: unknown): EventRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapEvent(value as ApiEvent);
}

function mapEvent(event: ApiEvent): EventRecord {
  return {
    id: event.id,
    workspaceId: event.workspace_id,
    eventGroupId: event.event_group_id,
    clientId: event.client_id,
    contactId: event.contact_id,
    venueId: event.venue_id,
    leadMembershipId: event.lead_membership_id,
    name: event.name,
    eventNumber: event.event_number,
    description: event.description,
    startsAt: event.starts_at,
    endsAt: event.ends_at,
    timezone: event.timezone,
    guestCountExpected: event.guest_count_expected,
    guestCountConfirmed: event.guest_count_confirmed,
    serviceType: event.service_type,
    eventType: event.event_type,
    status: event.status,
    priority: event.priority,
    notes: event.notes,
    version: event.version,
    cancelledAt: event.cancelled_at,
    completedAt: event.completed_at,
    eventGroup: event.event_group?.name ?? null,
    client: mapClient(event.client),
    contact: mapContact(event.contact),
    venue: mapVenue(event.venue),
    createdAt: event.created_at,
    updatedAt: event.updated_at,
  };
}

function mapClient(
  client: ApiEvent["client"]
): EventClientValue | null {
  if (!client) {
    return null;
  }

  return {
    company: client.company_name,
    contact: client.primary_contact
      ? {
          email: client.primary_contact.email,
          id: client.primary_contact.id,
          name: client.primary_contact.display_name ?? client.primary_contact.full_name,
          organization: client.company_name ?? client.name,
          phone: client.primary_contact.phone,
          role: client.primary_contact.contact_type,
          title: client.primary_contact.job_title,
        }
      : null,
    email: client.email,
    id: client.id ?? undefined,
    metadata: client.status,
    name: client.name,
    organization: client.company_name,
    phone: client.phone,
  };
}

function mapContact(
  contact: ApiEvent["contact"]
): EventContactValue | null {
  if (!contact) {
    return null;
  }

  return {
    email: contact.email,
    id: contact.id ?? undefined,
    name: contact.display_name ?? contact.full_name,
    organization: contact.organization,
    phone: contact.phone,
    role: contact.contact_type,
    title: contact.job_title,
  };
}

function mapVenue(
  venue: ApiEvent["venue"]
): EventVenueValue | null {
  if (!venue) {
    return null;
  }

  const summary = [venue.city, venue.state, venue.timezone].filter(Boolean).join(" • ");

  return {
    address: {
      addressLine1: venue.address_line_1,
      addressLine2: venue.address_line_2,
      city: venue.city,
      country: venue.country_code,
      postalCode: venue.postal_code,
      region: venue.state,
    },
    contact:
      venue.contact_name || venue.contact_email || venue.contact_phone
        ? {
            email: venue.contact_email,
            name: venue.contact_name,
            phone: venue.contact_phone,
          }
        : null,
    id: venue.id ?? undefined,
    name: venue.name,
    notes: venue.notes,
    summary: summary || null,
  };
}
