import type { ImageSourcePropType } from "react-native";

import type { PrepItemRecord } from "@/features/prep";
import type { TaskRecord } from "@/features/tasks";
import type { WorkspaceMemberStatus } from "@/theme/status-config";

export type MemberAvailabilityStatus =
  | "available"
  | "unavailable"
  | "busy"
  | "away"
  | "on_shift"
  | "off_shift";

export type TeamStaffRoleReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  translationKey?: string | null;
};

export type TeamReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
};

export type StationReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
  team?: TeamReference | null;
};

export type MemberAvailabilityRecord = {
  id?: string | null;
  available?: boolean | null;
  endsAt?: string | null;
  notes?: string | null;
  startsAt?: string | null;
  status?: MemberAvailabilityStatus | null;
  timezone?: string | null;
  type?: string | null;
};

export type MemberShiftRecord = {
  endsAt?: string | null;
  eventId?: string | null;
  id?: string | null;
  role?: string | null;
  startsAt?: string | null;
  station?: StationReference | null;
  stationId?: string | null;
  status?: string | null;
  team?: TeamReference | null;
  teamId?: string | null;
  timezone?: string | null;
};

export type WorkloadSummaryRecord = {
  assigned?: number | null;
  blocked?: number | null;
  capacity?: number | null;
  completed?: number | null;
  inProgress?: number | null;
  overloaded?: boolean | null;
  prepItemCount?: number | null;
  taskCount?: number | null;
  totalAssignments?: number | null;
  utilization?: number | null;
};

export type TeamStaffMemberRecord = {
  availability?: MemberAvailabilityRecord | null;
  email?: string | null;
  id: string;
  joinedAt?: string | null;
  name?: string | null;
  role?: TeamStaffRoleReference | null;
  source?: ImageSourcePropType;
  station?: StationReference | null;
  stationId?: string | null;
  status?: WorkspaceMemberStatus | null;
  team?: TeamReference | null;
  teamId?: string | null;
  userId?: string | null;
  workload?: WorkloadSummaryRecord | null;
  shifts?: MemberShiftRecord[] | null;
};

export type TeamStaffSummaryRecord = {
  active?: number | null;
  assigned?: number | null;
  available?: number | null;
  invited?: number | null;
  onShift?: number | null;
  overloaded?: number | null;
  total?: number | null;
  unavailable?: number | null;
};

export type AssignmentBoardItemType = "task" | "prep_item";

export type AssignmentBoardItem = {
  assigneeMembershipId?: string | null;
  dueAt?: string | null;
  entityId: string;
  id: string;
  prepItem?: PrepItemRecord | null;
  priority?: string | null;
  status?: string | null;
  task?: TaskRecord | null;
  title: string;
  type: AssignmentBoardItemType;
};
