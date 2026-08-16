import type { SemanticStatusTone } from "@/theme/status-config";

import type {
  AssignmentBoardItem,
  MemberAvailabilityRecord,
  MemberAvailabilityStatus,
  TeamStaffMemberRecord,
  TeamStaffSummaryRecord,
  WorkloadSummaryRecord,
} from "@/features/team-staff/types";
import { getPrepPrimaryAssignment, type PrepItemRecord } from "@/features/prep";
import { getTaskPrimaryAssignment, type TaskRecord } from "@/features/tasks";

export const MEMBER_AVAILABILITY_CONFIG: Record<
  MemberAvailabilityStatus,
  { tone: SemanticStatusTone; translationKey: string }
> = {
  available: {
    tone: "success",
    translationKey: "teamStaff.availability.available",
  },
  away: {
    tone: "info",
    translationKey: "teamStaff.availability.away",
  },
  busy: {
    tone: "warning",
    translationKey: "teamStaff.availability.busy",
  },
  off_shift: {
    tone: "neutral",
    translationKey: "teamStaff.availability.off_shift",
  },
  on_shift: {
    tone: "primary",
    translationKey: "teamStaff.availability.on_shift",
  },
  unavailable: {
    tone: "neutral",
    translationKey: "teamStaff.availability.unavailable",
  },
};

function normalizeAvailabilityType(type?: string | null) {
  const normalized = type?.trim().toLowerCase();
  return normalized || null;
}

export function getMemberAvailabilityStatus(
  availability?: MemberAvailabilityRecord | null
): MemberAvailabilityStatus | null {
  if (availability?.status) {
    return availability.status;
  }

  const type = normalizeAvailabilityType(availability?.type);

  if (type === "available") {
    return "available";
  }

  if (type === "unavailable" || type === "time_off") {
    return "unavailable";
  }

  if (type === "preferred") {
    return "away";
  }

  if (availability?.available === true) {
    return "available";
  }

  if (availability?.available === false) {
    return "unavailable";
  }

  return null;
}

export function getMemberAvailabilityMetadata(
  availability?: MemberAvailabilityRecord | null
) {
  const status = getMemberAvailabilityStatus(availability);

  if (!status) {
    return null;
  }

  return MEMBER_AVAILABILITY_CONFIG[status];
}

export function getMemberRoleLabel(member?: TeamStaffMemberRecord | null) {
  if (member?.role?.translationKey) {
    return {
      translationKey: member.role.translationKey,
      value: null,
    };
  }

  if (member?.role?.name?.trim()) {
    return {
      translationKey: null,
      value: member.role.name.trim(),
    };
  }

  if (member?.role?.key?.trim()) {
    return {
      translationKey: `teamStaff.roles.${member.role.key.trim()}`,
      value: null,
    };
  }

  return null;
}

export function getMemberTeamName(member?: TeamStaffMemberRecord | null) {
  return member?.team?.name?.trim() ?? null;
}

export function getMemberStationName(member?: TeamStaffMemberRecord | null) {
  return member?.station?.name?.trim() ?? null;
}

export function buildMemberSummary(
  members: TeamStaffMemberRecord[]
): TeamStaffSummaryRecord {
  return members.reduce<TeamStaffSummaryRecord>(
    (summary, member) => {
      const availability = getMemberAvailabilityStatus(member.availability);
      const workload = member.workload;
      const onShift = member.shifts?.some((shift) =>
        shift.status === "scheduled" ||
        shift.status === "confirmed" ||
        shift.status === "in_progress"
      );

      return {
        active:
          (summary.active ?? 0) + (member.status === "active" ? 1 : 0),
        assigned:
          (summary.assigned ?? 0) + (typeof workload?.assigned === "number" ? workload.assigned > 0 ? 1 : 0 : typeof workload?.totalAssignments === "number" ? workload.totalAssignments > 0 ? 1 : 0 : 0),
        available:
          (summary.available ?? 0) + (availability === "available" || availability === "on_shift" ? 1 : 0),
        invited:
          (summary.invited ?? 0) + (member.status === "invited" ? 1 : 0),
        onShift: (summary.onShift ?? 0) + (onShift ? 1 : 0),
        overloaded:
          (summary.overloaded ?? 0) + (member.workload?.overloaded ? 1 : 0),
        total: (summary.total ?? 0) + 1,
        unavailable:
          (summary.unavailable ?? 0) +
          (availability === "unavailable" || availability === "away" || availability === "busy"
            ? 1
            : 0),
      };
    },
    {
      active: 0,
      assigned: 0,
      available: 0,
      invited: 0,
      onShift: 0,
      overloaded: 0,
      total: 0,
      unavailable: 0,
    }
  );
}

export function buildWorkloadSummary(
  values?: WorkloadSummaryRecord | null
): WorkloadSummaryRecord {
  const taskCount = values?.taskCount ?? 0;
  const prepItemCount = values?.prepItemCount ?? 0;
  const totalAssignments =
    values?.totalAssignments ?? values?.assigned ?? taskCount + prepItemCount;

  let utilization =
    typeof values?.utilization === "number" && Number.isFinite(values.utilization)
      ? values.utilization
      : null;

  if (
    utilization === null &&
    typeof values?.capacity === "number" &&
    values.capacity > 0
  ) {
    utilization = (totalAssignments / values.capacity) * 100;
  }

  if (typeof utilization === "number") {
    utilization = Math.max(0, Math.min(100, utilization));
  }

  return {
    ...values,
    prepItemCount,
    taskCount,
    totalAssignments,
    utilization,
  };
}

export function groupMembersByTeam(members: TeamStaffMemberRecord[]) {
  const groups = new Map<string, TeamStaffMemberRecord[]>();

  members.forEach((member) => {
    const key = member.team?.id ?? member.team?.name?.trim() ?? "__no-team__";
    const current = groups.get(key) ?? [];
    current.push(member);
    groups.set(key, current);
  });

  return Array.from(groups.entries()).map(([key, items]) => ({
    id: key,
    label: items[0]?.team?.name?.trim() ?? null,
    members: items,
  }));
}

export function groupMembersByRole(members: TeamStaffMemberRecord[]) {
  const groups = new Map<string, TeamStaffMemberRecord[]>();

  members.forEach((member) => {
    const key =
      member.role?.id ??
      member.role?.key?.trim() ??
      member.role?.name?.trim() ??
      "__no-role__";
    const current = groups.get(key) ?? [];
    current.push(member);
    groups.set(key, current);
  });

  return Array.from(groups.entries()).map(([key, items]) => ({
    id: key,
    label: items[0]?.role?.name?.trim() ?? null,
    roleKey: items[0]?.role?.key?.trim() ?? null,
    members: items,
  }));
}

export function createAssignmentBoardItemsFromTasks(tasks: TaskRecord[] = []) {
  return tasks.map<AssignmentBoardItem>((task) => ({
    assigneeMembershipId:
      getTaskPrimaryAssignment(task.assignments)?.membershipId ?? null,
    dueAt: task.dueAt ?? null,
    entityId: task.id ?? task.title,
    id: `task-${task.id ?? task.title}`,
    priority: task.priority ?? null,
    status: task.status ?? null,
    task,
    title: task.title,
    type: "task",
  }));
}

export function createAssignmentBoardItemsFromPrepItems(
  items: PrepItemRecord[] = []
) {
  return items.map<AssignmentBoardItem>((item) => ({
    assigneeMembershipId:
      getPrepPrimaryAssignment(item.assignments)?.membershipId ?? null,
    dueAt: item.dueAt ?? null,
    entityId: item.id ?? item.clientId ?? item.title,
    id: `prep-${item.id ?? item.clientId ?? item.title}`,
    prepItem: item,
    priority: item.priority ?? null,
    status: item.status ?? null,
    title: item.title,
    type: "prep_item",
  }));
}

export function groupAssignmentBoardItemsByMember(
  members: TeamStaffMemberRecord[],
  items: AssignmentBoardItem[]
) {
  const memberMap = new Map(members.map((member) => [member.id, member]));
  const assignedGroups = members.map((member) => ({
    id: member.id,
    items: items.filter((item) => item.assigneeMembershipId === member.id),
    member,
  }));

  const unassignedItems = items.filter(
    (item) => !item.assigneeMembershipId || !memberMap.has(item.assigneeMembershipId)
  );

  return {
    assignedGroups,
    unassignedItems,
  };
}
