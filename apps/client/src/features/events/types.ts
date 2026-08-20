import type { ImageSourcePropType } from "react-native";

import type { AvatarProps } from "@/components/primitives/avatar";
import type { WorkspaceMemberStatus } from "@/theme/status-config";

export type EventStatus =
  | "draft"
  | "tentative"
  | "confirmed"
  | "in_production"
  | "completed"
  | "cancelled";

export type EventPriority = "low" | "normal" | "high" | "urgent";

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

export type EventRecord = {
  id: string;
  workspaceId: string;
  eventGroupId: string | null;
  clientId: string | null;
  contactId: string | null;
  venueId: string | null;
  leadMembershipId: string | null;
  name: string;
  eventNumber: string | null;
  description: string | null;
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
  version: number;
  cancelledAt: string | null;
  completedAt: string | null;
  eventGroup: string | null;
  client: EventNamedValue | EventClientValue | null;
  contact: EventContactValue | null;
  responsibleMembers?: EventStaffMemberValue[];
  staff?: EventStaffMemberValue[];
  tags?: EventTagValue[];
  venue: EventNamedValue | EventVenueValue | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type EventsCursorPage = {
  data: EventRecord[];
  path: string;
  perPage: number;
  nextCursor: string | null;
  nextPageUrl: string | null;
  prevCursor: string | null;
  prevPageUrl: string | null;
};

export type EventMutationInput = {
  clientId: string | null;
  contactId: string | null;
  eventGroupId: string | null;
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
  venueId: string | null;
};

export type CreateEventInput = EventMutationInput;

export type UpdateEventInput = EventMutationInput & {
  version: number;
};
