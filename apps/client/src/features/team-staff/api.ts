import { apiRequest } from "@/api/client";
import type {
  AvailabilityEditorValues,
  ShiftEditorValues,
  StationEditorValues,
  TeamEditorValues,
} from "@/features/team-staff/forms";
import type {
  AvailabilityRecordBundle,
  AvailabilityRuleRecord,
  MemberAvailabilityRecord,
  MemberShiftRecord,
  StaffingConflictRecord,
  StationRecord,
  TeamRecord,
  TeamReference,
  TeamStaffEventReference,
  TeamStaffMemberRecord,
  TeamStaffRoleReference,
} from "@/features/team-staff/types";
import type { WorkspaceMember } from "@/features/workspace";

type ApiRole = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
};

type ApiTeam = {
  description?: string | null;
  id: string;
  key?: string | null;
  lead_membership_id?: string | null;
  member_count?: number | null;
  members?: ApiMember[] | null;
  name: string;
  status?: string | null;
  type?: string | null;
};

type ApiStation = {
  capacity?: number | null;
  description?: string | null;
  id: string;
  key?: string | null;
  name: string;
  position?: number | null;
  status?: string | null;
  team?: ApiTeamReference | null;
  team_id?: string | null;
  type?: string | null;
};

type ApiTeamReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
};

type ApiEventReference = {
  id?: string | null;
  name?: string | null;
  starts_at?: string | null;
  timezone?: string | null;
};

type ApiMember = {
  email?: string | null;
  id: string;
  joined_at?: string | null;
  name?: string | null;
  role?: ApiRole | null;
  status?: string | null;
  team_joined_at?: string | null;
  teams?: ApiTeamReference[] | null;
  user?: {
    email?: string | null;
    id?: string | null;
    name?: string | null;
  } | null;
  user_id?: string | null;
};

type ApiAvailability = {
  available?: boolean | null;
  ends_at?: string | null;
  id?: string | null;
  notes?: string | null;
  source?: string | null;
  starts_at?: string | null;
  timezone?: string | null;
  type?: string | null;
};

type ApiAvailabilityRule = {
  active?: boolean | null;
  available?: boolean | null;
  day_of_week: number;
  effective_from?: string | null;
  effective_until?: string | null;
  ends_at: string;
  id?: string | null;
  membership_id?: string | null;
  starts_at: string;
  timezone?: string | null;
};

type ApiAvailabilityBundle = {
  member: ApiMember;
  records: ApiAvailability[];
  rules: ApiAvailabilityRule[];
};

type ApiConflict = {
  assigned_staff?: number | null;
  details?: Record<string, unknown> | null;
  id: string;
  member?: ApiMember | null;
  membership_id?: string | null;
  message?: string | null;
  related_shift?: ApiShift | null;
  required_staff?: number | null;
  resolved?: boolean | null;
  severity?: StaffingConflictRecord["severity"] | null;
  shift?: ApiShift | null;
  shift_id?: string | null;
  station?: ApiStation | null;
  type: StaffingConflictRecord["type"];
};

type ApiShift = {
  break_minutes?: number | null;
  conflicts?: ApiConflict[] | null;
  created_at?: string | null;
  ends_at?: string | null;
  event?: ApiEventReference | null;
  event_id?: string | null;
  id: string;
  member?: ApiMember | null;
  membership_id?: string | null;
  notes?: string | null;
  role?: string | null;
  starts_at?: string | null;
  station?: ApiStation | null;
  station_id?: string | null;
  status?: MemberShiftRecord["status"] | null;
  team?: ApiTeamReference | null;
  team_id?: string | null;
  timezone?: string | null;
  updated_at?: string | null;
};

function normalizeMemberStatus(status?: string | null): TeamStaffMemberRecord["status"] {
  if (status === "pending") {
    return "invited";
  }

  if (status === "suspended" || status === "removed" || status === "inactive") {
    return "inactive";
  }

  if (status === "active") {
    return "active";
  }

  return null;
}

function mapRole(role?: ApiRole | null): TeamStaffRoleReference | null {
  if (!role) {
    return null;
  }

  return {
    id: role.id ?? null,
    key: role.key ?? null,
    name: role.name ?? null,
    translationKey: role.key?.trim() ? `teamStaff.roles.${role.key.trim()}` : null,
  };
}

function mapTeamReference(team?: ApiTeamReference | null): TeamReference | null {
  if (!team) {
    return null;
  }

  return {
    id: team.id ?? null,
    key: team.key ?? null,
    name: team.name ?? null,
    status: team.status ?? null,
  };
}

function mapStation(station: ApiStation): StationRecord {
  return {
    capacity: station.capacity ?? null,
    description: station.description ?? null,
    id: station.id,
    key: station.key ?? null,
    members: null,
    name: station.name,
    position: station.position ?? null,
    status: station.status ?? null,
    team: mapTeamReference(station.team),
    teamId: station.team_id ?? station.team?.id ?? null,
    type: station.type ?? null,
    workload: station.capacity
      ? {
          capacity: station.capacity ?? null,
        }
      : null,
  };
}

function mapEvent(event?: ApiEventReference | null): TeamStaffEventReference | null {
  if (!event) {
    return null;
  }

  return {
    id: event.id ?? null,
    name: event.name ?? null,
    startsAt: event.starts_at ?? null,
    timezone: event.timezone ?? null,
  };
}

function mapAvailabilityRecord(record: ApiAvailability): MemberAvailabilityRecord {
  return {
    available: record.available ?? null,
    endsAt: record.ends_at ?? null,
    id: record.id ?? null,
    notes: record.notes ?? null,
    source: record.source ?? null,
    startsAt: record.starts_at ?? null,
    timezone: record.timezone ?? null,
    type: record.type ?? null,
  };
}

function mapAvailabilityRule(rule: ApiAvailabilityRule): AvailabilityRuleRecord {
  return {
    active: rule.active ?? null,
    available: rule.available ?? null,
    dayOfWeek: rule.day_of_week,
    effectiveFrom: rule.effective_from ?? null,
    effectiveUntil: rule.effective_until ?? null,
    endsAt: rule.ends_at,
    id: rule.id ?? null,
    membershipId: rule.membership_id ?? null,
    startsAt: rule.starts_at,
    timezone: rule.timezone ?? null,
  };
}

function mapMember(
  member: ApiMember,
  overrides: Partial<TeamStaffMemberRecord> = {}
): TeamStaffMemberRecord {
  const primaryTeam = member.teams?.[0] ? mapTeamReference(member.teams[0]) : null;

  return {
    availability: overrides.availability ?? null,
    availabilityRules: overrides.availabilityRules ?? null,
    email: member.email ?? member.user?.email ?? null,
    id: member.id,
    joinedAt: member.joined_at ?? member.team_joined_at ?? null,
    name: member.name ?? member.user?.name ?? null,
    role: mapRole(member.role),
    station: overrides.station ?? null,
    stationId: overrides.stationId ?? null,
    status: normalizeMemberStatus(member.status),
    team: overrides.team ?? primaryTeam,
    teamId: overrides.teamId ?? primaryTeam?.id ?? null,
    userId: member.user_id ?? member.user?.id ?? null,
    workload: overrides.workload ?? null,
    shifts: overrides.shifts ?? null,
  };
}

function mapConflict(conflict: ApiConflict): StaffingConflictRecord {
  return {
    assignedStaff: conflict.assigned_staff ?? null,
    details: conflict.details ?? null,
    id: conflict.id,
    member: conflict.member ? mapMember(conflict.member) : null,
    membershipId: conflict.membership_id ?? null,
    message: conflict.message ?? null,
    relatedShift: conflict.related_shift ? mapShift(conflict.related_shift) : null,
    requiredStaff: conflict.required_staff ?? null,
    resolved: conflict.resolved ?? null,
    severity: conflict.severity ?? null,
    shift: conflict.shift ? mapShift(conflict.shift) : null,
    shiftId: conflict.shift_id ?? null,
    station: conflict.station ? mapStation(conflict.station) : null,
    type: conflict.type,
  };
}

function mapShift(shift: ApiShift): MemberShiftRecord {
  return {
    breakMinutes: shift.break_minutes ?? null,
    conflicts: shift.conflicts?.map(mapConflict) ?? [],
    createdAt: shift.created_at ?? null,
    endsAt: shift.ends_at ?? null,
    event: mapEvent(shift.event),
    eventId: shift.event_id ?? shift.event?.id ?? null,
    id: shift.id,
    member: shift.member ? mapMember(shift.member) : null,
    membershipId: shift.membership_id ?? shift.member?.id ?? null,
    notes: shift.notes ?? null,
    role: shift.role ?? null,
    startsAt: shift.starts_at ?? null,
    station: shift.station ? mapStation(shift.station) : null,
    stationId: shift.station_id ?? shift.station?.id ?? null,
    status: shift.status ?? null,
    team: mapTeamReference(shift.team),
    teamId: shift.team_id ?? shift.team?.id ?? null,
    timezone: shift.timezone ?? null,
    updatedAt: shift.updated_at ?? null,
  };
}

function mapTeam(team: ApiTeam): TeamRecord {
  const members = (team.members ?? []).map((member) =>
    mapMember(member, {
      team: {
        description: team.description ?? null,
        id: team.id,
        key: team.key ?? null,
        leadMembershipId: team.lead_membership_id ?? null,
        memberCount: team.member_count ?? null,
        name: team.name,
        status: team.status ?? null,
        type: team.type ?? null,
      },
      teamId: team.id,
    })
  );

  return {
    description: team.description ?? null,
    id: team.id,
    key: team.key ?? null,
    leadMembershipId: team.lead_membership_id ?? null,
    memberCount: team.member_count ?? members.length,
    members,
    name: team.name,
    status: team.status ?? null,
    type: team.type ?? null,
  };
}

function buildTeamPayload(values: TeamEditorValues) {
  return {
    description: values.description?.trim() || null,
    key: values.key?.trim() || null,
    lead_membership_id: values.leadMembershipId ?? null,
    member_ids: values.memberIds,
    name: values.name.trim(),
    status: values.status ?? "active",
    type: values.type?.trim() || null,
  };
}

function buildStationPayload(values: StationEditorValues) {
  return {
    capacity: values.capacity ?? null,
    description: values.description?.trim() || null,
    key: values.key?.trim() || null,
    name: values.name.trim(),
    position: values.position ?? 0,
    status: values.status ?? "active",
    team_id: values.teamId ?? null,
    type: values.type?.trim() || null,
  };
}

function buildAvailabilityPayload(values: AvailabilityEditorValues) {
  return {
    records: (values.records ?? []).map((record) => ({
      ends_at: record.endsAt,
      notes: record.notes?.trim() || null,
      source: record.source?.trim() || "user",
      starts_at: record.startsAt,
      timezone: record.timezone ?? "UTC",
      type: record.type ?? (record.available === false ? "unavailable" : "available"),
      available: record.available ?? true,
    })),
    rules: (values.rules ?? []).map((rule) => ({
      active: rule.active ?? true,
      available: rule.available ?? true,
      day_of_week: rule.dayOfWeek,
      effective_from: rule.effectiveFrom ?? null,
      effective_until: rule.effectiveUntil ?? null,
      ends_at: rule.endsAt,
      starts_at: rule.startsAt,
      timezone: rule.timezone ?? "UTC",
    })),
  };
}

function buildShiftPayload(values: ShiftEditorValues) {
  return {
    break_minutes: values.breakMinutes ?? 0,
    ends_at: values.endsAt,
    event_id: values.eventId ?? null,
    membership_id: values.membershipId,
    notes: values.notes?.trim() || null,
    role: values.role?.trim() || null,
    starts_at: values.startsAt,
    station_id: values.stationId ?? null,
    status: values.status ?? "scheduled",
    team_id: values.teamId ?? null,
    timezone: values.timezone ?? "UTC",
  };
}

export function mapWorkspaceMemberToTeamStaffMember(
  member: WorkspaceMember
): TeamStaffMemberRecord {
  const role = member.role
    ? {
        id: member.role.id,
        key: member.role.key,
        name: member.role.name,
        translationKey: member.role.key?.trim() ? `teamStaff.roles.${member.role.key.trim()}` : null,
      }
    : null;

  return {
    availability: null,
    availabilityRules: [],
    email: member.user?.email ?? null,
    id: member.id,
    joinedAt: member.joinedAt,
    name: member.user?.name ?? null,
    role,
    station: null,
    stationId: null,
    status: normalizeMemberStatus(member.status),
    team: null,
    teamId: null,
    userId: member.userId,
    workload: null,
    shifts: [],
  };
}

export async function getTeams(authToken: string, workspaceId: string): Promise<TeamRecord[]> {
  const response = await apiRequest<{ data: ApiTeam[] }>("/teams", {
    authToken,
    workspaceId,
  });

  return response.data.map(mapTeam);
}

export async function getTeam(authToken: string, workspaceId: string, teamId: string): Promise<TeamRecord> {
  const response = await apiRequest<{ data: ApiTeam }>(`/teams/${teamId}`, {
    authToken,
    workspaceId,
  });

  return mapTeam(response.data);
}

export async function createTeam(authToken: string, workspaceId: string, values: TeamEditorValues): Promise<TeamRecord> {
  const response = await apiRequest<{ data: ApiTeam }>("/teams", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildTeamPayload(values)),
    workspaceId,
  });

  return mapTeam(response.data);
}

export async function updateTeam(
  authToken: string,
  workspaceId: string,
  teamId: string,
  values: TeamEditorValues
): Promise<TeamRecord> {
  const response = await apiRequest<{ data: ApiTeam }>(`/teams/${teamId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify(buildTeamPayload(values)),
    workspaceId,
  });

  return mapTeam(response.data);
}

export async function deleteTeam(authToken: string, workspaceId: string, teamId: string): Promise<void> {
  await apiRequest<void>(`/teams/${teamId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

export async function updateTeamMembers(
  authToken: string,
  workspaceId: string,
  teamId: string,
  memberIds: string[],
  leadMembershipId?: string | null
): Promise<TeamRecord> {
  const response = await apiRequest<{ data: ApiTeam }>(`/teams/${teamId}/members`, {
    method: "PUT",
    authToken,
    body: JSON.stringify({
      lead_membership_id: leadMembershipId ?? null,
      member_ids: memberIds,
    }),
    workspaceId,
  });

  return mapTeam(response.data);
}

export async function getStations(authToken: string, workspaceId: string): Promise<StationRecord[]> {
  const response = await apiRequest<{ data: ApiStation[] }>("/stations", {
    authToken,
    workspaceId,
  });

  return response.data.map(mapStation);
}

export async function createStation(
  authToken: string,
  workspaceId: string,
  values: StationEditorValues
): Promise<StationRecord> {
  const response = await apiRequest<{ data: ApiStation }>("/stations", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildStationPayload(values)),
    workspaceId,
  });

  return mapStation(response.data);
}

export async function updateStation(
  authToken: string,
  workspaceId: string,
  stationId: string,
  values: StationEditorValues
): Promise<StationRecord> {
  const response = await apiRequest<{ data: ApiStation }>(`/stations/${stationId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify(buildStationPayload(values)),
    workspaceId,
  });

  return mapStation(response.data);
}

export async function deleteStation(authToken: string, workspaceId: string, stationId: string): Promise<void> {
  await apiRequest<void>(`/stations/${stationId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

export async function getAvailability(
  authToken: string,
  workspaceId: string,
  filters: { from?: string | null; membershipId?: string | null; to?: string | null } = {}
): Promise<AvailabilityRecordBundle[]> {
  const response = await apiRequest<{ data: ApiAvailabilityBundle[] }>("/availability", {
    authToken,
    query: {
      from: filters.from ?? undefined,
      membership_id: filters.membershipId ?? undefined,
      to: filters.to ?? undefined,
    },
    workspaceId,
  });

  return response.data.map((bundle) => ({
    member: mapMember(bundle.member),
    records: bundle.records.map(mapAvailabilityRecord),
    rules: bundle.rules.map(mapAvailabilityRule),
  }));
}

export async function updateAvailability(
  authToken: string,
  workspaceId: string,
  membershipId: string,
  values: AvailabilityEditorValues
): Promise<AvailabilityRecordBundle> {
  const response = await apiRequest<{ data: ApiAvailabilityBundle }>(`/availability/${membershipId}`, {
    method: "PUT",
    authToken,
    body: JSON.stringify(buildAvailabilityPayload(values)),
    workspaceId,
  });

  return {
    member: mapMember(response.data.member),
    records: response.data.records.map(mapAvailabilityRecord),
    rules: response.data.rules.map(mapAvailabilityRule),
  };
}

export async function getShifts(
  authToken: string,
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
): Promise<MemberShiftRecord[]> {
  const response = await apiRequest<{ data: ApiShift[] }>("/shifts", {
    authToken,
    query: {
      event_id: filters.eventId ?? undefined,
      from: filters.from ?? undefined,
      membership_id: filters.membershipId ?? undefined,
      station_id: filters.stationId ?? undefined,
      status: filters.status ?? undefined,
      team_id: filters.teamId ?? undefined,
      to: filters.to ?? undefined,
    },
    workspaceId,
  });

  return response.data.map(mapShift);
}

export async function getShift(authToken: string, workspaceId: string, shiftId: string): Promise<MemberShiftRecord> {
  const response = await apiRequest<{ data: ApiShift }>(`/shifts/${shiftId}`, {
    authToken,
    workspaceId,
  });

  return mapShift(response.data);
}

export async function createShift(
  authToken: string,
  workspaceId: string,
  values: ShiftEditorValues
): Promise<MemberShiftRecord> {
  const response = await apiRequest<{ data: ApiShift }>("/shifts", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildShiftPayload(values)),
    workspaceId,
  });

  return mapShift(response.data);
}

export async function updateShift(
  authToken: string,
  workspaceId: string,
  shiftId: string,
  values: ShiftEditorValues
): Promise<MemberShiftRecord> {
  const response = await apiRequest<{ data: ApiShift }>(`/shifts/${shiftId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify(buildShiftPayload(values)),
    workspaceId,
  });

  return mapShift(response.data);
}

export async function deleteShift(authToken: string, workspaceId: string, shiftId: string): Promise<void> {
  await apiRequest<void>(`/shifts/${shiftId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}
