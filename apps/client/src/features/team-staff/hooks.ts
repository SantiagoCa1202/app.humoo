import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import {
  createShift,
  createStation,
  createTeam,
  deleteShift,
  deleteStation,
  deleteTeam,
  getAvailability,
  getShift,
  getShifts,
  getStations,
  getTeam,
  getTeams,
  mapWorkspaceMemberToTeamStaffMember,
  updateAvailability,
  updateShift,
  updateStation,
  updateTeam,
  updateTeamMembers,
} from "@/features/team-staff/api";
import type {
  AvailabilityEditorValues,
  ShiftEditorValues,
  StationEditorValues,
  TeamEditorValues,
} from "@/features/team-staff/forms";
import type {
  AvailabilityRecordBundle,
  MemberShiftRecord,
  StationRecord,
  StaffingConflictRecord,
  TeamRecord,
  TeamStaffMemberRecord,
} from "@/features/team-staff/types";
import { listWorkspaceMembers } from "@/features/workspace";
import { useWorkspace } from "@/features/workspace";

function getApiContext(sessionToken: string | null | undefined, workspaceId: string | null | undefined) {
  if (!sessionToken || !workspaceId) {
    throw new Error("No active workspace session.");
  }

  return { sessionToken, workspaceId };
}

function normalizeString(value?: string | null) {
  const trimmed = value?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : "";
}

function buildWorkload(shifts: MemberShiftRecord[]) {
  const activeShiftCount = shifts.filter((shift) =>
    shift.status === "scheduled" ||
    shift.status === "confirmed" ||
    shift.status === "in_progress"
  ).length;

  return {
    assigned: activeShiftCount,
    capacity: shifts.length > 0 ? Math.max(activeShiftCount, 1) : null,
    totalAssignments: shifts.length,
    utilization: activeShiftCount > 0 ? 100 : 0,
  };
}

function mergeStaffMembers(
  workspaceMembers: TeamStaffMemberRecord[],
  availabilityBundles: AvailabilityRecordBundle[],
  shifts: MemberShiftRecord[],
  teams: TeamRecord[]
) {
  const memberMap = new Map<string, TeamStaffMemberRecord>(
    workspaceMembers.map((member) => [member.id, member])
  );

  availabilityBundles.forEach((bundle) => {
    const existing = memberMap.get(bundle.member.id) ?? bundle.member;
    memberMap.set(bundle.member.id, {
      ...existing,
      availability: bundle.records[0] ?? existing.availability ?? null,
      availabilityRules: bundle.rules,
    });
  });

  const shiftsByMember = new Map<string, MemberShiftRecord[]>();
  shifts.forEach((shift) => {
    if (!shift.membershipId) {
      return;
    }

    const current = shiftsByMember.get(shift.membershipId) ?? [];
    current.push(shift);
    shiftsByMember.set(shift.membershipId, current);
  });

  const teamByMember = new Map<string, TeamRecord>();
  teams.forEach((team) => {
    team.members.forEach((member) => {
      teamByMember.set(member.id, team);
    });
  });

  Array.from(memberMap.values()).forEach((member) => {
    const memberShifts = shiftsByMember.get(member.id) ?? [];
    const team = teamByMember.get(member.id);
    const currentShiftStation =
      memberShifts.find((shift) => shift.station)?.station ??
      member.station ??
      null;

    memberMap.set(member.id, {
      ...member,
      shifts: memberShifts,
      station: currentShiftStation,
      stationId: currentShiftStation?.id ?? member.stationId ?? null,
      team: team
        ? {
            description: team.description ?? null,
            id: team.id,
            key: team.key ?? null,
            leadMembershipId: team.leadMembershipId ?? null,
            memberCount: team.memberCount ?? team.members.length,
            members: team.members,
            name: team.name,
            status: team.status ?? null,
            type: team.type ?? null,
          }
        : member.team ?? null,
      teamId: team?.id ?? member.teamId ?? null,
      workload: buildWorkload(memberShifts),
    });
  });

  return Array.from(memberMap.values()).sort((left, right) =>
    (left.name ?? "").localeCompare(right.name ?? "", undefined, { sensitivity: "base" })
  );
}

function attachStationMembers(
  stations: StationRecord[],
  members: TeamStaffMemberRecord[]
) {
  return stations.map((station) => ({
    ...station,
    members: members.filter((member) => member.stationId === station.id),
    workload: {
      ...(station.workload ?? {}),
      assigned: members.filter((member) => member.stationId === station.id).length,
      totalAssignments: members.filter((member) => member.stationId === station.id).length,
    },
  }));
}

function uniqueConflicts(shifts: MemberShiftRecord[]) {
  const map = new Map<string, StaffingConflictRecord>();

  shifts.forEach((shift) => {
    shift.conflicts?.forEach((conflict) => {
      if (!map.has(conflict.id)) {
        map.set(conflict.id, conflict);
      }
    });
  });

  return Array.from(map.values());
}

export const teamStaffKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "team-staff"] as const;
  },
  members(workspaceId: string) {
    return [...this.workspace(workspaceId), "members"] as const;
  },
  teams(workspaceId: string) {
    return [...this.workspace(workspaceId), "teams"] as const;
  },
  team(workspaceId: string, teamId: string) {
    return [...this.teams(workspaceId), teamId] as const;
  },
  stations(workspaceId: string) {
    return [...this.workspace(workspaceId), "stations"] as const;
  },
  availability(
    workspaceId: string,
    filters: { from?: string | null; membershipId?: string | null; to?: string | null } = {}
  ) {
    return [
      ...this.workspace(workspaceId),
      "availability",
      {
        from: normalizeString(filters.from),
        membershipId: normalizeString(filters.membershipId),
        to: normalizeString(filters.to),
      },
    ] as const;
  },
  shifts(
    workspaceId: string,
    filters: {
      eventId?: string | null;
      from?: string | null;
      membershipId?: string | null;
      stationId?: string | null;
      status?: string | null;
      teamId?: string | null;
      to?: string | null;
    } = {}
  ) {
    return [
      ...this.workspace(workspaceId),
      "shifts",
      {
        eventId: normalizeString(filters.eventId),
        from: normalizeString(filters.from),
        membershipId: normalizeString(filters.membershipId),
        stationId: normalizeString(filters.stationId),
        status: normalizeString(filters.status),
        teamId: normalizeString(filters.teamId),
        to: normalizeString(filters.to),
      },
    ] as const;
  },
  shift(workspaceId: string, shiftId: string) {
    return [...this.workspace(workspaceId), "shift", shiftId] as const;
  },
};

export function useWorkspaceStaffMembers() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      const members = await listWorkspaceMembers(context.sessionToken, context.workspaceId);

      return members.map(mapWorkspaceMemberToTeamStaffMember);
    },
    queryKey:
      workspaceId
        ? teamStaffKeys.members(workspaceId)
        : ["workspace", "no-workspace", "team-staff", "members"],
    retry: 1,
  });
}

export function useTeams() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getTeams(context.sessionToken, context.workspaceId);
    },
    queryKey:
      workspaceId
        ? teamStaffKeys.teams(workspaceId)
        : ["workspace", "no-workspace", "team-staff", "teams"],
    retry: 1,
  });
}

export function useTeam(teamId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(teamId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getTeam(context.sessionToken, context.workspaceId, teamId!);
    },
    queryKey:
      workspaceId && teamId
        ? teamStaffKeys.team(workspaceId, teamId)
        : ["workspace", "no-workspace", "team-staff", "team"],
    retry: 1,
  });
}

export function useStations() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getStations(context.sessionToken, context.workspaceId);
    },
    queryKey:
      workspaceId
        ? teamStaffKeys.stations(workspaceId)
        : ["workspace", "no-workspace", "team-staff", "stations"],
    retry: 1,
  });
}

export function useAvailability(
  filters: { from?: string | null; membershipId?: string | null; to?: string | null } = {}
) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getAvailability(context.sessionToken, context.workspaceId, filters);
    },
    queryKey:
      workspaceId
        ? teamStaffKeys.availability(workspaceId, filters)
        : ["workspace", "no-workspace", "team-staff", "availability"],
    retry: 1,
  });
}

export function useShifts(
  filters: {
    eventId?: string | null;
    from?: string | null;
    membershipId?: string | null;
    stationId?: string | null;
    status?: string | null;
    teamId?: string | null;
    to?: string | null;
  } = {}
) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getShifts(context.sessionToken, context.workspaceId, filters);
    },
    queryKey:
      workspaceId
        ? teamStaffKeys.shifts(workspaceId, filters)
        : ["workspace", "no-workspace", "team-staff", "shifts"],
    retry: 1,
  });
}

export function useShift(shiftId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(shiftId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getShift(context.sessionToken, context.workspaceId, shiftId!);
    },
    queryKey:
      workspaceId && shiftId
        ? teamStaffKeys.shift(workspaceId, shiftId)
        : ["workspace", "no-workspace", "team-staff", "shift"],
    retry: 1,
  });
}

export function useTeamStaffDirectory(
  filters: { from?: string | null; membershipId?: string | null; teamId?: string | null; to?: string | null } = {}
) {
  const membersQuery = useWorkspaceStaffMembers();
  const teamsQuery = useTeams();
  const stationsQuery = useStations();
  const availabilityQuery = useAvailability({
    from: filters.from ?? null,
    membershipId: filters.membershipId ?? null,
    to: filters.to ?? null,
  });
  const shiftsQuery = useShifts({
    from: filters.from ?? null,
    membershipId: filters.membershipId ?? null,
    teamId: filters.teamId ?? null,
    to: filters.to ?? null,
  });

  const members = useMemo(
    () =>
      mergeStaffMembers(
        membersQuery.data ?? [],
        availabilityQuery.data ?? [],
        shiftsQuery.data ?? [],
        teamsQuery.data ?? []
      ),
    [availabilityQuery.data, membersQuery.data, shiftsQuery.data, teamsQuery.data]
  );
  const stations = useMemo(
    () => attachStationMembers(stationsQuery.data ?? [], members),
    [members, stationsQuery.data]
  );
  const conflicts = useMemo(
    () => uniqueConflicts(shiftsQuery.data ?? []),
    [shiftsQuery.data]
  );

  return {
    availabilityQuery,
    conflicts,
    isLoading:
      membersQuery.isLoading ||
      teamsQuery.isLoading ||
      stationsQuery.isLoading ||
      availabilityQuery.isLoading ||
      shiftsQuery.isLoading,
    members,
    membersQuery,
    shifts: shiftsQuery.data ?? [],
    shiftsQuery,
    stations,
    stationsQuery,
    teams: teamsQuery.data ?? [],
    teamsQuery,
  };
}

function invalidateWorkspace(queryClient: ReturnType<typeof useQueryClient>, workspaceId: string) {
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: teamStaffKeys.workspace(workspaceId) }),
    queryClient.invalidateQueries({ queryKey: ["workspace", workspaceId, "members"] }),
  ]);
}

export function useCreateTeam() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: TeamEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createTeam(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useUpdateTeam(teamId: string) {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: TeamEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateTeam(context.sessionToken, context.workspaceId, teamId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await queryClient.invalidateQueries({ queryKey: teamStaffKeys.team(workspaceId, teamId) });
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useUpdateTeamMembers(teamId: string) {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (payload: { leadMembershipId?: string | null; memberIds: string[] }) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateTeamMembers(
        context.sessionToken,
        context.workspaceId,
        teamId,
        payload.memberIds,
        payload.leadMembershipId
      );
    },
    onSuccess: async () => {
      if (workspaceId) {
        await queryClient.invalidateQueries({ queryKey: teamStaffKeys.team(workspaceId, teamId) });
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useDeleteTeam() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (teamId: string) => {
      const context = getApiContext(session?.token, workspaceId);
      return deleteTeam(context.sessionToken, context.workspaceId, teamId);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useCreateStation() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: StationEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createStation(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useUpdateStation(stationId: string) {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: StationEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateStation(context.sessionToken, context.workspaceId, stationId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useDeleteStation() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (stationId: string) => {
      const context = getApiContext(session?.token, workspaceId);
      return deleteStation(context.sessionToken, context.workspaceId, stationId);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useUpdateAvailability(membershipId: string) {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: AvailabilityEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateAvailability(context.sessionToken, context.workspaceId, membershipId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useCreateShift() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: ShiftEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createShift(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useUpdateShift(shiftId: string) {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: ShiftEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateShift(context.sessionToken, context.workspaceId, shiftId, values);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await queryClient.invalidateQueries({ queryKey: teamStaffKeys.shift(workspaceId, shiftId) });
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}

export function useDeleteShift() {
  const queryClient = useQueryClient();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (shiftId: string) => {
      const context = getApiContext(session?.token, workspaceId);
      return deleteShift(context.sessionToken, context.workspaceId, shiftId);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await invalidateWorkspace(queryClient, workspaceId);
      }
    },
  });
}
