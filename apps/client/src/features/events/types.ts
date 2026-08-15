export type EventStatus =
  | "draft"
  | "tentative"
  | "confirmed"
  | "in_production"
  | "completed"
  | "cancelled";

export type EventPriority = "low" | "normal" | "high" | "urgent";

export type EventRecord = {
  id: string;
  name: string;
  eventNumber: string | null;
  startsAt: string;
  endsAt: string | null;
  timezone: string;
  guestCountExpected: number | null;
  guestCountConfirmed: number | null;
  serviceType: string | null;
  eventType: string | null;
  status: EventStatus;
  priority: EventPriority;
  notes: string | null;
  createdAt: string | null;
};

export type EventsCursorPage = {
  data: EventRecord[];
  path: string;
  per_page: number;
  next_cursor: string | null;
  next_page_url: string | null;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

export type CreateEventInput = {
  name: string;
  startsAt: string;
  endsAt: string | null;
  timezone: string;
  status: EventStatus;
  priority: EventPriority;
  guestCountExpected: number | null;
  serviceType: string | null;
  eventType: string | null;
  notes: string | null;
};
