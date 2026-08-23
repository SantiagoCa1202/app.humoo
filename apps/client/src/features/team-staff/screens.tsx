import { router, useLocalSearchParams, type Href } from "expo-router";
import { useDeferredValue, useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { AvailabilityEditor } from "@/components/patterns/availability-editor";
import { AvailabilitySummary } from "@/components/patterns/availability-summary";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { MemberCard } from "@/components/patterns/member-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { ShiftCalendar } from "@/components/patterns/shift-calendar";
import { ShiftCard } from "@/components/patterns/shift-card";
import { ShiftEditor } from "@/components/patterns/shift-editor";
import { StaffingConflictAlert } from "@/components/patterns/staffing-conflict-alert";
import { StationCard } from "@/components/patterns/station-card";
import { StationEditor } from "@/components/patterns/station-editor";
import { TeamEditorForm } from "@/components/patterns/team-editor-form";
import { TeamRoster } from "@/components/patterns/team-roster";
import { TeamSummaryCard } from "@/components/patterns/team-summary-card";
import { Button } from "@/components/primitives/button";
import { SearchInput } from "@/components/primitives/search-input";
import { Text } from "@/components/primitives/text";
import { useEvents } from "@/features/events";
import {
  addDaysToDateKey,
  createAvailabilityEditorValues,
  createShiftEditorValues,
  createStationEditorValues,
  createTeamEditorValues,
  getDateKeyForValue,
  type AvailabilityEditorValidationErrors,
  type ShiftEditorValidationErrors,
  type StationEditorValidationErrors,
  type TeamEditorValidationErrors,
  useCreateShift,
  useCreateStation,
  useCreateTeam,
  useDeleteShift,
  useDeleteStation,
  useDeleteTeam,
  useShift,
  useTeam,
  useTeamStaffDirectory,
  useUpdateAvailability,
  useUpdateShift,
  useUpdateStation,
  useUpdateTeam,
  type AvailabilityEditorValues,
  type MemberShiftRecord,
  type StationRecord,
  type TeamEditorValues,
  type TeamStaffMemberRecord,
} from "@/features/team-staff";
import { getDayBoundsForDateKey } from "@/features/events/calendar";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";
import { useAuth } from "@/auth/useAuth";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapFormError<T extends { form?: string }>(error: unknown): T {
  if (!isApiError(error)) {
    return {
      form: error instanceof Error ? error.message : undefined,
    } as T;
  }

  return {
    form: error.message,
  } as T;
}

function filterMembers(members: TeamStaffMemberRecord[], search: string) {
  if (!search.trim()) {
    return members;
  }

  const normalizedSearch = search.trim().toLowerCase();

  return members.filter((member) => {
    const haystack = [
      member.name,
      member.email,
      member.team?.name,
      member.station?.name,
      member.role?.name,
      member.role?.key,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return haystack.includes(normalizedSearch);
  });
}

function buildMemberOptions(members: TeamStaffMemberRecord[]) {
  return members.map((member) => ({
    label: member.name ?? member.email ?? member.id,
    metadata: member.email ?? undefined,
    value: member.id,
  }));
}

function buildTeamInitialValues(team?: {
  description?: string | null;
  id: string;
  key?: string | null;
  leadMembershipId?: string | null;
  members: TeamStaffMemberRecord[];
  name: string;
  status?: string | null;
  type?: string | null;
} | null): Partial<TeamEditorValues> | undefined {
  if (!team) {
    return undefined;
  }

  return {
    description: team.description ?? null,
    id: team.id,
    key: team.key ?? null,
    leadMembershipId: team.leadMembershipId ?? null,
    memberIds: team.members.map((member) => member.id),
    members: team.members,
    name: team.name,
    status: team.status ?? "active",
    type: team.type ?? null,
  };
}

function TeamScreenActions() {
  const { t } = useTranslation("app");

  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[2] }}>
      <Button
        label={t("teamStaff.moduleActions.stations")}
        onPress={() => router.push(routes.app.stations)}
        size="sm"
        variant="secondary"
      />
      <Button
        label={t("teamStaff.moduleActions.availability")}
        onPress={() => router.push(routes.app.availability)}
        size="sm"
        variant="ghost"
      />
      <Button
        label={t("teamStaff.moduleActions.shifts")}
        onPress={() => router.push(routes.app.shifts)}
        size="sm"
        variant="ghost"
      />
    </View>
  );
}

export function TeamRosterScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const canViewStaff = hasPermission("members.view");
  const canManageStaff = hasPermission("members.manage");
  const directory = useTeamStaffDirectory();
  const createTeamMutation = useCreateTeam();
  const [search, setSearch] = useState("");
  const [creating, setCreating] = useState(false);
  const [validationErrors, setValidationErrors] = useState<TeamEditorValidationErrors>();
  const deferredSearch = useDeferredValue(search);
  const filteredMembers = useMemo(
    () => filterMembers(directory.members, deferredSearch),
    [deferredSearch, directory.members]
  );

  if (!canViewStaff) {
    return (
      <AppShell subtitle={t("teamStaff.roster.subtitle")} title={t("teamStaff.roster.title")}>
        <ForbiddenState title={t("teamStaff.roster.forbiddenTitle")} />
      </AppShell>
    );
  }

  if (directory.isLoading && directory.members.length === 0 && directory.teams.length === 0) {
    return (
      <AppShell subtitle={t("teamStaff.roster.subtitle")} title={t("teamStaff.roster.title")}>
        <LoadingState title={t("teamStaff.roster.loadingTitle")} />
      </AppShell>
    );
  }

  if (directory.membersQuery.error || directory.teamsQuery.error) {
    return (
      <AppShell subtitle={t("teamStaff.roster.subtitle")} title={t("teamStaff.roster.title")}>
        <ErrorState
          detail={directory.membersQuery.error?.message ?? directory.teamsQuery.error?.message}
          title={t("teamStaff.roster.errorTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("teamStaff.roster.subtitle")} title={t("teamStaff.roster.title")}>
      <SectionCard
        action={<TeamScreenActions />}
        description={t("teamStaff.roster.cardDescription")}
        title={t("teamStaff.roster.cardTitle")}
      >
        <View style={{ gap: spacing[4] }}>
          <SearchInput
            onChangeText={setSearch}
            placeholder={t("teamStaff.roster.searchPlaceholder", { ns: "common" })}
            value={search}
          />
          <TeamSummaryCard members={filteredMembers} />
          <AvailabilitySummary members={filteredMembers} />
        </View>
      </SectionCard>

      <SectionCard
        action={
          canManageStaff ? (
            <Button
              label={t("teamStaff.teamEditor.actions.create", { ns: "common" })}
              onPress={() => {
                setValidationErrors(undefined);
                setCreating((current) => !current);
              }}
              size="sm"
              variant="secondary"
            />
          ) : undefined
        }
        description={t("teamStaff.roster.listDescription")}
        title={t("teamStaff.roster.listTitle")}
      >
        <View style={{ gap: spacing[4] }}>
          {creating ? (
            <TeamEditorForm
              memberOptions={buildMemberOptions(directory.members)}
              onCancel={() => setCreating(false)}
              onSubmit={async (values) => {
                try {
                  setValidationErrors(undefined);
                  await createTeamMutation.mutateAsync(values);
                  setCreating(false);
                } catch (error) {
                  setValidationErrors(mapFormError<TeamEditorValidationErrors>(error));
                }
              }}
              submitting={createTeamMutation.isPending}
              validationErrors={validationErrors}
            />
          ) : null}
          <TeamRoster
            groupByTeam
            members={filteredMembers}
            onMemberPress={(member) => {
              if (member.teamId) {
                router.push({
                  params: { teamId: member.teamId },
                  pathname: routes.app.teamDetail,
                } as Href);
              }
            }}
          />
          {directory.teams.length ? (
            <View style={{ gap: spacing[3] }}>
              {directory.teams.map((team) => (
                <View key={team.id} style={{ gap: spacing[2] }}>
                  <Text variant="label">{team.name}</Text>
                  <Button
                    label={t("teamStaff.roster.openTeam", { name: team.name })}
                    onPress={() =>
                      router.push({
                        params: { teamId: team.id },
                        pathname: routes.app.teamDetail,
                      } as Href)
                    }
                    size="sm"
                    variant="ghost"
                  />
                </View>
              ))}
            </View>
          ) : (
            <EmptyState
              actionLabel={
                canManageStaff ? t("teamStaff.teamEditor.actions.create", { ns: "common" }) : undefined
              }
              onAction={canManageStaff ? () => setCreating(true) : undefined}
              title={t("teamStaff.roster.emptyTeamsTitle", { ns: "common" })}
            />
          )}
        </View>
      </SectionCard>
    </AppShell>
  );
}

export function TeamDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const params = useLocalSearchParams<{ teamId?: string | string[] }>();
  const teamId = resolveRouteParam(params.teamId);
  const { hasPermission } = useWorkspace();
  const canViewStaff = hasPermission("members.view");
  const canManageStaff = hasPermission("members.manage");
  const teamQuery = useTeam(teamId);
  const directory = useTeamStaffDirectory({ teamId: teamId ?? null });
  const updateTeamMutation = useUpdateTeam(teamId ?? "");
  const deleteTeamMutation = useDeleteTeam();
  const [editing, setEditing] = useState(false);
  const [validationErrors, setValidationErrors] = useState<TeamEditorValidationErrors>();

  if (!canViewStaff) {
    return (
      <AppShell subtitle={t("teamStaff.detail.subtitle")} title={t("teamStaff.detail.title")}>
        <ForbiddenState title={t("teamStaff.detail.forbiddenTitle")} />
      </AppShell>
    );
  }

  if (!teamId) {
    return (
      <AppShell subtitle={t("teamStaff.detail.subtitle")} title={t("teamStaff.detail.title")}>
        <EmptyState title={t("teamStaff.detail.missingTitle")} />
      </AppShell>
    );
  }

  if (teamQuery.isLoading || directory.isLoading) {
    return (
      <AppShell subtitle={t("teamStaff.detail.subtitle")} title={t("teamStaff.detail.title")}>
        <LoadingState title={t("teamStaff.detail.loadingTitle")} />
      </AppShell>
    );
  }

  if (teamQuery.error || !teamQuery.data) {
    return (
      <AppShell subtitle={t("teamStaff.detail.subtitle")} title={t("teamStaff.detail.title")}>
        <ErrorState detail={teamQuery.error?.message} title={t("teamStaff.detail.errorTitle")} />
      </AppShell>
    );
  }

  const team = teamQuery.data;
  const conflicts = directory.conflicts.filter((conflict) => conflict.shift?.teamId === team.id);

  return (
    <AppShell subtitle={t("teamStaff.detail.subtitle")} title={team.name}>
      <SectionCard
        action={
          canManageStaff ? (
            <View style={{ flexDirection: "row", gap: spacing[2] }}>
              <Button
                label={editing ? t("teamStaff.actions.cancel", { ns: "common" }) : t("teamStaff.detail.editAction")}
                onPress={() => setEditing((current) => !current)}
                size="sm"
                variant="secondary"
              />
              <Button
                label={t("teamStaff.detail.deleteAction")}
                onPress={async () => {
                  await deleteTeamMutation.mutateAsync(team.id);
                  router.replace(routes.app.teamRoster);
                }}
                size="sm"
                variant="ghost"
              />
            </View>
          ) : undefined
        }
        description={team.description ?? t("teamStaff.detail.cardDescription")}
        title={team.name}
      >
        <View style={{ gap: spacing[4] }}>
          <TeamSummaryCard members={team.members} />
          {conflicts[0] ? <StaffingConflictAlert conflict={conflicts[0]} /> : null}
          <TeamRoster members={team.members} />
          {editing ? (
            <TeamEditorForm
              initialValues={buildTeamInitialValues(team)}
              memberOptions={buildMemberOptions(directory.members)}
              mode="edit"
              onCancel={() => setEditing(false)}
              onSubmit={async (values) => {
                try {
                  setValidationErrors(undefined);
                  await updateTeamMutation.mutateAsync(values);
                  setEditing(false);
                } catch (error) {
                  setValidationErrors(mapFormError<TeamEditorValidationErrors>(error));
                }
              }}
              submitting={updateTeamMutation.isPending}
              validationErrors={validationErrors}
            />
          ) : null}
        </View>
      </SectionCard>
    </AppShell>
  );
}

export function StationsScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const canViewStaff = hasPermission("members.view");
  const canManageStaff = hasPermission("members.manage");
  const directory = useTeamStaffDirectory();
  const createStationMutation = useCreateStation();
  const deleteStationMutation = useDeleteStation();
  const [editingStation, setEditingStation] = useState<StationRecord | null>(null);
  const updateStationMutation = useUpdateStation(editingStation?.id ?? "");
  const [creating, setCreating] = useState(false);
  const [validationErrors, setValidationErrors] = useState<StationEditorValidationErrors>();

  if (!canViewStaff) {
    return (
      <AppShell subtitle={t("teamStaff.stations.subtitle")} title={t("teamStaff.stations.title")}>
        <ForbiddenState title={t("teamStaff.stations.forbiddenTitle")} />
      </AppShell>
    );
  }

  if (directory.isLoading) {
    return (
      <AppShell subtitle={t("teamStaff.stations.subtitle")} title={t("teamStaff.stations.title")}>
        <LoadingState title={t("teamStaff.stations.loadingTitle")} />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("teamStaff.stations.subtitle")} title={t("teamStaff.stations.title")}>
      <SectionCard
        action={
          canManageStaff ? (
            <Button
              label={t("teamStaff.actions.createStation", { ns: "common" })}
              onPress={() => {
                setEditingStation(null);
                setCreating((current) => !current);
              }}
              size="sm"
              variant="secondary"
            />
          ) : undefined
        }
        description={t("teamStaff.stations.cardDescription")}
        title={t("teamStaff.stations.cardTitle")}
      >
        <View style={{ gap: spacing[4] }}>
          {creating ? (
            <StationEditor
              initialValues={createStationEditorValues()}
              onCancel={() => setCreating(false)}
              onSubmit={async (values) => {
                try {
                  setValidationErrors(undefined);
                  await createStationMutation.mutateAsync(values);
                  setCreating(false);
                } catch (error) {
                  setValidationErrors(mapFormError<StationEditorValidationErrors>(error));
                }
              }}
              submitting={createStationMutation.isPending}
              teamOptions={directory.teams.map((team) => ({
                label: team.name,
                value: team.id,
              }))}
              validationErrors={validationErrors}
            />
          ) : null}
          {editingStation ? (
            <StationEditor
              initialValues={editingStation}
              mode="edit"
              onCancel={() => setEditingStation(null)}
              onSubmit={async (values) => {
                try {
                  setValidationErrors(undefined);
                  await updateStationMutation.mutateAsync(values);
                  setEditingStation(null);
                } catch (error) {
                  setValidationErrors(mapFormError<StationEditorValidationErrors>(error));
                }
              }}
              submitting={updateStationMutation.isPending}
              teamOptions={directory.teams.map((team) => ({
                label: team.name,
                value: team.id,
              }))}
              validationErrors={validationErrors}
            />
          ) : null}
          {directory.stations.length ? (
            <View style={{ gap: spacing[3] }}>
              {directory.stations.map((station) => (
                <StationCard
                  actions={
                    canManageStaff ? (
                      <View style={{ flexDirection: "row", gap: spacing[2] }}>
                        <Button
                          label={t("teamStaff.detail.editAction")}
                          onPress={() => {
                            setCreating(false);
                            setEditingStation(station);
                          }}
                          size="sm"
                          variant="ghost"
                        />
                        <Button
                          label={t("teamStaff.detail.deleteAction")}
                          onPress={() => void deleteStationMutation.mutateAsync(station.id ?? "")}
                          size="sm"
                          variant="ghost"
                        />
                      </View>
                    ) : undefined
                  }
                  key={station.id ?? station.name}
                  station={station}
                />
              ))}
            </View>
          ) : (
            <EmptyState title={t("teamStaff.stations.emptyTitle")} />
          )}
        </View>
      </SectionCard>
    </AppShell>
  );
}

export function AvailabilityScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { activeWorkspace, hasPermission } = useWorkspace();
  const canViewStaff = hasPermission("members.view");
  const canManageStaff = hasPermission("members.manage");
  const timeZone = activeWorkspace?.timezone ?? "UTC";
  const directory = useTeamStaffDirectory();
  const [selectedMembershipId, setSelectedMembershipId] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<AvailabilityEditorValidationErrors>();
  const selectedMember =
    directory.members.find((member) => member.id === selectedMembershipId) ??
    directory.members[0] ??
    null;
  const updateAvailabilityMutation = useUpdateAvailability(selectedMember?.id ?? "");

  useEffect(() => {
    if (!selectedMembershipId && directory.members[0]) {
      setSelectedMembershipId(directory.members[0].id);
    }
  }, [directory.members, selectedMembershipId]);

  if (!canViewStaff) {
    return (
      <AppShell subtitle={t("teamStaff.availability.subtitle")} title={t("teamStaff.availability.title")}>
        <ForbiddenState title={t("teamStaff.availability.forbiddenTitle")} />
      </AppShell>
    );
  }

  if (directory.isLoading) {
    return (
      <AppShell subtitle={t("teamStaff.availability.subtitle")} title={t("teamStaff.availability.title")}>
        <LoadingState title={t("teamStaff.availability.loadingTitle")} />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("teamStaff.availability.subtitle")} title={t("teamStaff.availability.title")}>
      <SectionCard
        description={t("teamStaff.availability.cardDescription")}
        title={t("teamStaff.availability.cardTitle")}
      >
        <View style={{ gap: spacing[4] }}>
          <AvailabilitySummary members={directory.members} />
          {selectedMember ? <MemberCard member={selectedMember} /> : null}
          {canManageStaff && selectedMember ? (
            <AvailabilityEditor
              initialValues={createAvailabilityEditorValues(selectedMember.id, {
                records: selectedMember.availability ? [selectedMember.availability] : [],
                rules: selectedMember.availabilityRules ?? [],
              })}
              membershipId={selectedMember.id}
              onSubmit={async (values: AvailabilityEditorValues) => {
                try {
                  setValidationErrors(undefined);
                  await updateAvailabilityMutation.mutateAsync(values);
                } catch (error) {
                  setValidationErrors(mapFormError<AvailabilityEditorValidationErrors>(error));
                }
              }}
              submitting={updateAvailabilityMutation.isPending}
              timeZone={timeZone}
              validationErrors={validationErrors}
            />
          ) : null}
          <TeamRoster
            compact
            members={directory.members}
            onMemberPress={(member) => setSelectedMembershipId(member.id)}
            selectedMemberId={selectedMember?.id ?? null}
          />
        </View>
      </SectionCard>
    </AppShell>
  );
}

export function ShiftsScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { session } = useAuth();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const canViewStaff = hasPermission("members.view");
  const canManageStaff = hasPermission("members.manage");
  const timeZone = activeWorkspace?.timezone ?? session?.user.timezone ?? "UTC";
  const [selectedDate, setSelectedDate] = useState(() => getDateKeyForValue(new Date(), timeZone));
  const [selectedShiftId, setSelectedShiftId] = useState<string | null>(null);
  const [editingShift, setEditingShift] = useState<MemberShiftRecord | null>(null);
  const [creating, setCreating] = useState(false);
  const [validationErrors, setValidationErrors] = useState<ShiftEditorValidationErrors>();
  const firstDateKey = addDaysToDateKey(selectedDate, -7);
  const lastDateKey = addDaysToDateKey(selectedDate, 21);
  const range = useMemo(
    () => ({
      from: getDayBoundsForDateKey(firstDateKey, timeZone).start,
      to: getDayBoundsForDateKey(lastDateKey, timeZone).end,
    }),
    [firstDateKey, lastDateKey, timeZone]
  );
  const directory = useTeamStaffDirectory(range);
  const shiftQuery = useShift(selectedShiftId);
  const createShiftMutation = useCreateShift();
  const updateShiftMutation = useUpdateShift(editingShift?.id ?? "");
  const deleteShiftMutation = useDeleteShift();
  const eventsQuery = useEvents({ dateFrom: range.from ?? undefined, dateTo: range.to ?? undefined, perPage: 100 });

  if (!canViewStaff) {
    return (
      <AppShell subtitle={t("teamStaff.shifts.subtitle")} title={t("teamStaff.shifts.title")}>
        <ForbiddenState title={t("teamStaff.shifts.forbiddenTitle")} />
      </AppShell>
    );
  }

  if (directory.isLoading) {
    return (
      <AppShell subtitle={t("teamStaff.shifts.subtitle")} title={t("teamStaff.shifts.title")}>
        <LoadingState title={t("teamStaff.shifts.loadingTitle")} />
      </AppShell>
    );
  }

  const selectedShift =
    shiftQuery.data ??
    directory.shifts.find((shift) => shift.id === selectedShiftId) ??
    null;

  return (
    <AppShell subtitle={t("teamStaff.shifts.subtitle")} title={t("teamStaff.shifts.title")}>
      <SectionCard
        action={
          canManageStaff ? (
            <Button
              label={t("teamStaff.shiftEditor.actions.create", { ns: "common" })}
              onPress={() => {
                setEditingShift(null);
                setCreating((current) => !current);
              }}
              size="sm"
              variant="secondary"
            />
          ) : undefined
        }
        description={t("teamStaff.shifts.cardDescription")}
        title={t("teamStaff.shifts.cardTitle")}
      >
        <View style={{ gap: spacing[4] }}>
          {directory.conflicts[0] ? <StaffingConflictAlert conflict={directory.conflicts[0]} /> : null}
          <ShiftCalendar
            onDateChange={setSelectedDate}
            onShiftPress={(shift) => {
              setSelectedShiftId(shift.id ?? null);
              setEditingShift(shift);
              setCreating(false);
            }}
            selectedDate={selectedDate}
            selectedShiftId={selectedShiftId}
            shifts={directory.shifts}
            timeZone={timeZone}
          />
          {selectedShift ? <ShiftCard shift={selectedShift} /> : null}
          {canManageStaff && (creating || editingShift) ? (
            <ShiftEditor
              eventOptions={eventsQuery.events.map((event) => ({
                label: event.name,
                metadata: event.startsAt ?? undefined,
                value: event.id,
              }))}
              initialValues={
                editingShift
                  ? createShiftEditorValues({
                      ...editingShift,
                      membershipId: editingShift.membershipId ?? editingShift.member?.id ?? "",
                    })
                  : undefined
              }
              memberOptions={buildMemberOptions(directory.members)}
              mode={editingShift ? "edit" : "create"}
              onCancel={() => {
                setCreating(false);
                setEditingShift(null);
              }}
              onSubmit={async (values) => {
                try {
                  setValidationErrors(undefined);
                  if (editingShift?.id) {
                    await updateShiftMutation.mutateAsync(values);
                  } else {
                    await createShiftMutation.mutateAsync(values);
                  }
                  setCreating(false);
                  setEditingShift(null);
                } catch (error) {
                  setValidationErrors(mapFormError<ShiftEditorValidationErrors>(error));
                }
              }}
              stationOptions={directory.stations.map((station) => ({
                label: station.name,
                metadata: station.team?.name ?? undefined,
                value: station.id ?? "",
              }))}
              submitting={createShiftMutation.isPending || updateShiftMutation.isPending}
              teamOptions={directory.teams.map((team) => ({
                label: team.name,
                value: team.id,
              }))}
              timeZone={timeZone}
              validationErrors={validationErrors}
            />
          ) : null}
          {selectedShift?.id && canManageStaff ? (
            <Button
              label={t("teamStaff.detail.deleteAction")}
              onPress={() => void deleteShiftMutation.mutateAsync(selectedShift.id ?? "")}
              size="sm"
              variant="ghost"
            />
          ) : null}
        </View>
      </SectionCard>
    </AppShell>
  );
}
