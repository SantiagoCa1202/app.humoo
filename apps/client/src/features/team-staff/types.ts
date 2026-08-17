import type { ImageSourcePropType } from "react-native";

import type { PrepItemRecord } from "@/features/prep";
import type { TaskRecord } from "@/features/tasks";
import type { ShiftStatus } from "@/theme/status-config";
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

export type TeamStaffEventReference = {
  id?: string | null;
  name?: string | null;
  startsAt?: string | null;
  timezone?: string | null;
};

export type StationReference = {
  id?: string | null;
  description?: string | null;
  key?: string | null;
  name?: string | null;
  position?: number | null;
  status?: string | null;
  team?: TeamReference | null;
  teamId?: string | null;
  type?: string | null;
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
  event?: TeamStaffEventReference | null;
  eventId?: string | null;
  id?: string | null;
  member?: TeamStaffMemberRecord | null;
  membershipId?: string | null;
  notes?: string | null;
  role?: string | null;
  startsAt?: string | null;
  station?: StationReference | null;
  stationId?: string | null;
  status?: ShiftStatus | null;
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

export type StationRecord = {
  description?: string | null;
  id: string | null;
  key?: string | null;
  members?: TeamStaffMemberRecord[] | null;
  name: string;
  position?: number | null;
  status?: "active" | "inactive" | (string & {}) | null;
  team?: TeamReference | null;
  teamId?: string | null;
  type?: string | null;
  workload?: WorkloadSummaryRecord | null;
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

export type AvailabilitySummaryRecord = TeamStaffSummaryRecord & {
  busy?: number | null;
  offShift?: number | null;
  periodLabel?: string | null;
  unknown?: number | null;
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

export type StaffingConflictSeverity = "info" | "warning" | "critical";

export type StaffingConflictRecord = {
  assignedStaff?: number | null;
  details?: Record<string, unknown> | null;
  event?: TeamStaffEventReference | null;
  id: string;
  member?: TeamStaffMemberRecord | null;
  membershipId?: string | null;
  message?: string | null;
  missingStaff?: number | null;
  relatedShift?: MemberShiftRecord | null;
  requiredStaff?: number | null;
  resolved?: boolean | null;
  severity?: StaffingConflictSeverity | null;
  shift?: MemberShiftRecord | null;
  shiftId?: string | null;
  station?: StationRecord | null;
  type:
    | "overlap"
    | "unavailable"
    | "overtime"
    | "station_capacity"
    | "event_overlap"
    | (string & {});
};
