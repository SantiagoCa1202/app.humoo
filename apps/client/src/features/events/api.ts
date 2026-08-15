import { apiRequest } from "@/api/client";
import type { AppSession } from "@/auth/types";

import type {
  CreateEventInput,
  EventRecord,
  EventsCursorPage,
} from "@/features/events/types";

type ApiEvent = {
  id: string;
  name: string;
  event_number: string | null;
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
  created_at: string | null;
};

type ApiEventsCursorPage = Omit<EventsCursorPage, "data"> & {
  data: ApiEvent[];
};

type StoreEventResponse = {
  data: ApiEvent;
};

export async function listEvents(session: AppSession): Promise<EventsCursorPage> {
  const auth = requireApiSession(session);
  const response = await apiRequest<ApiEventsCursorPage>("/api/v1/events", {
    authToken: auth.token,
    workspaceId: auth.workspaceId,
  });

  return {
    ...response,
    data: response.data.map(mapEvent),
  };
}

export async function createEvent(
  session: AppSession,
  input: CreateEventInput
): Promise<EventRecord> {
  const auth = requireApiSession(session);
  const response = await apiRequest<StoreEventResponse>("/api/v1/events", {
    method: "POST",
    authToken: auth.token,
    workspaceId: auth.workspaceId,
    body: JSON.stringify({
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
    }),
  });

  return mapEvent(response.data);
}

function requireApiSession(session: AppSession): {
  token: string;
  workspaceId: string;
} {
  const token = session.token;
  const workspaceId = session.currentWorkspace?.id;

  if (session.mode !== "api" || !token || !workspaceId) {
    throw new Error("The API event module requires a real authenticated workspace session.");
  }

  return {
    token,
    workspaceId,
  };
}

function mapEvent(event: ApiEvent): EventRecord {
  return {
    id: event.id,
    name: event.name,
    eventNumber: event.event_number,
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
    createdAt: event.created_at,
  };
}
