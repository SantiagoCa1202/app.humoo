import type { ImageSourcePropType } from "react-native";

import type { TaskStatus } from "@/theme/status-config";

export type { TaskStatus } from "@/theme/status-config";

export type TaskPriority = "low" | "normal" | "high" | "urgent";

export type TaskAssignmentStatus =
  | "assigned"
  | "accepted"
  | "declined"
  | "completed"
  | "cancelled";

export type TaskUserReference = {
  id?: string | null;
  name?: string | null;
  source?: ImageSourcePropType;
};

export type TaskEventReference = {
  id?: string | null;
  name?: string | null;
  startsAt?: string | null;
  timezone?: string | null;
};

export type TaskTeamReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
  type?: string | null;
};

export type TaskStationReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
  team?: TaskTeamReference | null;
  teamId?: string | null;
  type?: string | null;
};

export type TaskAssignmentRecord = {
  acceptedAt?: string | null;
  assignedAt?: string | null;
  completedAt?: string | null;
  id?: string | null;
  isPrimary?: boolean | null;
  membershipId?: string | null;
  roleLabel?: string | null;
  status?: TaskAssignmentStatus | null;
  user?: TaskUserReference | null;
};

export type TaskRecord = {
  assignments?: TaskAssignmentRecord[] | null;
  blockedReason?: string | null;
  completedAt?: string | null;
  completedBy?: TaskUserReference | null;
  createdAt?: string | null;
  createdBy?: TaskUserReference | null;
  description?: string | null;
  dueAt?: string | null;
  event?: TaskEventReference | null;
  eventId?: string | null;
  id: string | null;
  metadata?: Record<string, unknown> | null;
  priority?: TaskPriority | null;
  source?: string | null;
  sourceId?: string | null;
  sourceType?: string | null;
  startsAt?: string | null;
  station?: TaskStationReference | null;
  stationId?: string | null;
  status?: TaskStatus | null;
  team?: TaskTeamReference | null;
  teamId?: string | null;
  title: string;
  type?: string | null;
  updatedAt?: string | null;
  updatedBy?: TaskUserReference | null;
  version?: number | null;
};

export type TaskSummaryRecord = {
  assigned?: number | null;
  blocked?: number | null;
  cancelled?: number | null;
  done?: number | null;
  inProgress?: number | null;
  overdue?: number | null;
  todo?: number | null;
  total?: number | null;
  unassigned?: number | null;
};
