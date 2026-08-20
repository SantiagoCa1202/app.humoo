import { router, useLocalSearchParams, type Href } from "expo-router";
import { useDeferredValue, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { useAuth } from "@/auth/useAuth";
import { AppShell } from "@/components/patterns/AppShell";
import { ConflictState } from "@/components/patterns/conflict-state";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { MyTasksCard } from "@/components/patterns/my-tasks-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { TaskAssignment } from "@/components/patterns/task-assignment";
import { TaskCard } from "@/components/patterns/task-card";
import { TaskDueIndicator } from "@/components/patterns/task-due-indicator";
import { TaskEditorForm } from "@/components/patterns/task-editor-form";
import { TaskFiltersForm } from "@/components/patterns/task-filters-form";
import { TaskList } from "@/components/patterns/task-list";
import { TaskPriorityBadge } from "@/components/patterns/task-priority-badge";
import { TaskStatusActions } from "@/components/patterns/task-status-actions";
import { TaskStatusBadge } from "@/components/patterns/task-status-badge";
import { TaskSummaryCard } from "@/components/patterns/task-summary-card";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { useEvents } from "@/features/events";
import {
  createEmptyTaskFilters,
  createTaskEditorValues,
  normalizeTaskEditorValues,
} from "@/features/tasks/forms";
import { coerceTaskRecord } from "@/features/tasks/api";
import {
  useCreateTask,
  useDeleteTask,
  useMyTasks,
  useTask,
  useTasks,
  useUpdateTask,
} from "@/features/tasks/hooks";
import { applyTaskStatusAction } from "@/features/tasks/presentation";
import type {
  TaskAssignmentOption,
  TaskEditorValidationErrors,
  TaskEntityOption,
} from "@/features/tasks/forms";
import type { TaskRecord } from "@/features/tasks/types";
import { useStations, useTeams, useWorkspaceStaffMembers } from "@/features/team-staff";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapTaskFormErrors(error: unknown): {
  submitError: string | null;
  validationErrors: TaskEditorValidationErrors;
} {
  if (!isApiError(error)) {
    return {
      submitError: error instanceof Error ? error.message : null,
      validationErrors: {},
    };
  }

  const validationErrors: TaskEditorValidationErrors = {};

  for (const [field, messages] of Object.entries(error.fieldErrors ?? {})) {
    const message = messages[0];

    if (!message) {
      continue;
    }

    switch (field) {
      case "blocked_reason":
        validationErrors.blockedReason = message;
        break;
      case "description":
      case "priority":
      case "status":
      case "title":
        validationErrors[field] = message;
        break;
      case "due_at":
        validationErrors.dueAt = message;
        break;
      case "event_id":
        validationErrors.eventId = message;
        break;
      case "starts_at":
        validationErrors.startsAt = message;
        break;
      case "station_id":
        validationErrors.stationId = message;
        break;
      case "team_id":
        validationErrors.teamId = message;
        break;
      case "version":
        validationErrors.form = message;
        break;
      default:
        if (field.startsWith("assignments")) {
          validationErrors.assignments = message;
        }
        break;
    }
  }

  return {
    submitError: error.message,
    validationErrors,
  };
}

function buildAssignmentOptions(members: ReturnType<typeof useWorkspaceStaffMembers>["data"] = []) {
  return members.map<TaskAssignmentOption>((member) => ({
    label: member.name ?? member.email ?? member.id,
    roleLabel: member.role?.name ?? undefined,
    value: member.id,
  }));
}

function buildEventOptions(events: ReturnType<typeof useEvents>["events"]) {
  return events
    .filter((event) => Boolean(event.id))
    .map<TaskEntityOption & { startsAt?: string | null; timezone?: string | null }>((event) => ({
      label: event.name,
      metadata: event.startsAt ?? undefined,
      startsAt: event.startsAt,
      timezone: event.timezone,
      value: event.id as string,
    }));
}

function buildTeamOptions(teams: ReturnType<typeof useTeams>["data"] = []) {
  return teams
    .filter((team) => Boolean(team.id))
    .map<TaskEntityOption>((team) => ({
      label: team.name,
      metadata: team.type ?? undefined,
      value: team.id as string,
    }));
}

function buildStationOptions(stations: ReturnType<typeof useStations>["data"] = []) {
  return stations
    .filter((station) => Boolean(station.id))
    .map<TaskEntityOption>((station) => ({
      label: station.name,
      metadata: station.team?.name ?? undefined,
      value: station.id as string,
    }));
}

function getStatusActions(task?: TaskRecord | null) {
  if (!task?.status) {
    return [];
  }

  if (task.status === "todo") {
    return [{ id: "start" }, { id: "block" }, { id: "skip" }];
  }

  if (task.status === "in_progress") {
    return [{ id: "complete" }, { id: "block" }, { id: "skip" }];
  }

  if (task.status === "blocked") {
    return [{ id: "reopen" }, { id: "complete" }];
  }

  if (task.status === "done" || task.status === "cancelled") {
    return [{ id: "reopen" }];
  }

  return [];
}

export function TaskListScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { activeMembership, hasPermission } = useWorkspace();
  const canCreate = hasPermission("tasks.create");
  const canView = hasPermission("tasks.view");
  const eventsQuery = useEvents({ perPage: 100 });
  const membersQuery = useWorkspaceStaffMembers();
  const teamsQuery = useTeams();
  const stationsQuery = useStations();
  const [filters, setFilters] = useState(() => createEmptyTaskFilters());
  const deferredFilters = useDeferredValue(filters);
  const tasksQuery = useTasks({
    ...deferredFilters,
    search: deferredFilters.search?.trim() ?? "",
  });

  const assignmentOptions = useMemo(
    () => buildAssignmentOptions(membersQuery.data ?? []),
    [membersQuery.data]
  );
  const eventOptions = useMemo(() => buildEventOptions(eventsQuery.events), [eventsQuery.events]);
  const teamOptions = useMemo(() => buildTeamOptions(teamsQuery.data ?? []), [teamsQuery.data]);
  const stationOptions = useMemo(
    () => buildStationOptions(stationsQuery.data ?? []),
    [stationsQuery.data]
  );

  if (!canView) {
    return (
      <AppShell subtitle={t("app:tasks.list.subtitle")} title={t("app:tasks.list.title")}>
        <ForbiddenState
          description={t("app:tasks.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:tasks.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("app:tasks.list.subtitle")} title={t("app:tasks.list.title")}>
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[2] }}>
              {activeMembership ? (
                <Button
                  label={t("app:tasks.actions.viewMine")}
                  onPress={() => router.push(routes.app.myTasks)}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {canCreate ? (
                <Button
                  label={t("common:tasks.actions.create")}
                  onPress={() => router.push(routes.app.taskCreate)}
                  size="sm"
                />
              ) : null}
            </View>
          }
          description={t("app:tasks.list.cardDescription")}
          title={t("app:tasks.list.cardTitle")}
        >
          <View style={{ gap: spacing[4] }}>
            <TaskFiltersForm
              assigneeOptions={assignmentOptions.length ? assignmentOptions : undefined}
              eventOptions={eventOptions.length ? eventOptions : undefined}
              filters={filters}
              onChange={(nextFilters) =>
                setFilters({
                  ...createEmptyTaskFilters(),
                  ...nextFilters,
                })
              }
              onReset={() => setFilters(createEmptyTaskFilters())}
              stationOptions={stationOptions.length ? stationOptions : undefined}
              teamOptions={teamOptions.length ? teamOptions : undefined}
            />
            <TaskSummaryCard tasks={tasksQuery.tasks} />
            <TaskList
              error={tasksQuery.isError && tasksQuery.error instanceof Error ? tasksQuery.error.message : undefined}
              groupByStatus
              loading={tasksQuery.isLoading}
              onEndReached={() => {
                if (tasksQuery.hasNextPage && !tasksQuery.isFetchingNextPage) {
                  void tasksQuery.fetchNextPage();
                }
              }}
              onItemPress={(task) =>
                router.push({
                  pathname: routes.app.taskDetail,
                  params: { taskId: task.id },
                } as Href)
              }
              onRefresh={async () => {
                await tasksQuery.refetch();
              }}
              refreshing={tasksQuery.isRefetching}
              tasks={tasksQuery.tasks}
            />
          </View>
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function TaskDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const taskId = resolveRouteParam(useLocalSearchParams<{ taskId?: string }>().taskId);
  const canEdit = hasPermission("tasks.edit");
  const canDelete = hasPermission("tasks.delete");
  const canView = hasPermission("tasks.view");
  const taskQuery = useTask(taskId);
  const updateMutation = useUpdateTask(taskId ?? "");
  const deleteMutation = useDeleteTask(taskId ?? "");
  const [conflictTask, setConflictTask] = useState<TaskRecord | null>(null);
  const [overrideTask, setOverrideTask] = useState<TaskRecord | null>(null);
  const [loadingAction, setLoadingAction] = useState<string | null>(null);
  const currentTask = overrideTask ?? taskQuery.data ?? null;

  if (!canView) {
    return (
      <AppShell subtitle={t("app:tasks.detail.subtitle")} title={t("app:tasks.detail.title")}>
        <ForbiddenState
          description={t("app:tasks.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:tasks.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!taskId) {
    return (
      <AppShell subtitle={t("app:tasks.detail.subtitle")} title={t("app:tasks.detail.title")}>
        <ErrorState
          detail={t("app:tasks.missingIdentifierDescription")}
          title={t("app:tasks.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  const handleStatusAction = async (actionId: string) => {
    if (!currentTask?.id) {
      return;
    }

    try {
      setLoadingAction(actionId);
      setConflictTask(null);
      const nextTask = normalizeTaskEditorValues(
        createTaskEditorValues(applyTaskStatusAction(currentTask, actionId))
      );
      await updateMutation.mutateAsync(nextTask);
      setOverrideTask(null);
    } catch (error) {
      if (isApiError(error) && error.kind === "conflict") {
        setConflictTask(coerceTaskRecord(error.details));
      }
    } finally {
      setLoadingAction(null);
    }
  };

  return (
    <AppShell
      subtitle={t("app:tasks.detail.subtitle")}
      title={
        currentTask?.title
          ? t("app:tasks.detail.titleWithName", { name: currentTask.title })
          : t("app:tasks.detail.title")
      }
    >
      <View style={{ gap: spacing[4] }}>
        {taskQuery.isLoading ? <LoadingState title={t("app:tasks.loadingTitle")} /> : null}
        {taskQuery.isError ? (
          isApiError(taskQuery.error) && taskQuery.error.kind === "not_found" ? (
            <EmptyState
              description={t("app:tasks.notFoundDescription")}
              onAction={() => router.replace(routes.app.tasks)}
              title={t("app:tasks.notFoundTitle")}
            />
          ) : (
            <ErrorState
              detail={taskQuery.error instanceof Error ? taskQuery.error.message : undefined}
              onRetry={async () => {
                await taskQuery.refetch();
              }}
              title={t("app:tasks.errorTitle")}
            />
          )
        ) : null}
        {conflictTask ? (
          <ConflictState
            description={t("app:tasks.edit.conflictDescription")}
            onReload={async () => {
              setConflictTask(null);
              setOverrideTask(null);
              await taskQuery.refetch();
            }}
            onReview={() => {
              setOverrideTask(conflictTask);
              setConflictTask(null);
            }}
          />
        ) : null}
        {currentTask ? (
          <>
            <SectionCard
              action={
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[2] }}>
                  {canEdit ? (
                    <Button
                      label={t("app:tasks.detail.editAction")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.taskEdit,
                          params: { taskId: currentTask.id },
                        } as Href)
                      }
                      size="sm"
                      variant="secondary"
                    />
                  ) : null}
                  {canDelete ? (
                    <Button
                      disabled={deleteMutation.isPending}
                      label={t("app:tasks.detail.deleteAction")}
                      onPress={async () => {
                        await deleteMutation.mutateAsync();
                        router.replace(routes.app.tasks);
                      }}
                      size="sm"
                      variant="ghost"
                    />
                  ) : null}
                </View>
              }
              description={t("app:tasks.detail.cardDescription")}
              title={t("app:tasks.detail.cardTitle")}
            >
              <TaskCard task={currentTask} />
            </SectionCard>

            <SectionCard
              description={t("app:tasks.detail.statusDescription")}
              title={t("app:tasks.detail.statusTitle")}
            >
              <View style={{ gap: spacing[3] }}>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[2] }}>
                  {currentTask.priority ? <TaskPriorityBadge priority={currentTask.priority} /> : null}
                  {currentTask.status ? <TaskStatusBadge status={currentTask.status} /> : null}
                </View>
                <TaskDueIndicator
                  dueAt={currentTask.dueAt}
                  status={currentTask.status}
                  timeZone={currentTask.event?.timezone}
                />
                <TaskStatusActions
                  availableActions={getStatusActions(currentTask)}
                  disabled={updateMutation.isPending}
                  loadingAction={loadingAction}
                  onAction={(action) => void handleStatusAction(action.id)}
                />
              </View>
            </SectionCard>

            <SectionCard
              description={t("app:tasks.detail.assignmentDescription")}
              title={t("app:tasks.detail.assignmentTitle")}
            >
              <TaskAssignment assignments={currentTask.assignments} />
            </SectionCard>

            {currentTask.event ? (
              <SectionCard
                action={
                  currentTask.event.id ? (
                    <Button
                      label={t("app:tasks.detail.openEventAction")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.eventDetail,
                          params: { eventId: currentTask.event?.id },
                        } as Href)
                      }
                      size="sm"
                      variant="ghost"
                    />
                  ) : null
                }
                description={t("app:tasks.detail.relationDescription")}
                title={t("app:tasks.detail.relationTitle")}
              >
                <Text variant="body">{currentTask.event.name ?? t("common:tasks.labels.context")}</Text>
              </SectionCard>
            ) : null}

            {currentTask.description?.trim() || currentTask.blockedReason?.trim() ? (
              <SectionCard
                description={t("app:tasks.detail.notesDescription")}
                title={t("app:tasks.detail.notesTitle")}
              >
                <View style={{ gap: spacing[2] }}>
                  {currentTask.description?.trim() ? (
                    <Text variant="body">{currentTask.description.trim()}</Text>
                  ) : null}
                  {currentTask.blockedReason?.trim() ? (
                    <Text tone="danger" variant="bodySmall">
                      {currentTask.blockedReason.trim()}
                    </Text>
                  ) : null}
                </View>
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function TaskCreateScreen() {
  return <TaskUpsertScreen mode="create" />;
}

export function TaskEditScreen() {
  return <TaskUpsertScreen mode="edit" />;
}

type TaskUpsertMode = "create" | "edit";

function TaskUpsertScreen({ mode }: { mode: TaskUpsertMode }) {
  const { t } = useTranslation(["app", "common"]);
  const { session } = useAuth();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const taskId = resolveRouteParam(useLocalSearchParams<{ taskId?: string }>().taskId);
  const canCreate = hasPermission("tasks.create");
  const canEdit = hasPermission("tasks.edit");
  const allowed = mode === "create" ? canCreate : canEdit;
  const taskQuery = useTask(mode === "edit" ? taskId : null);
  const createMutation = useCreateTask();
  const updateMutation = useUpdateTask(taskId ?? "");
  const eventsQuery = useEvents({ perPage: 100 });
  const membersQuery = useWorkspaceStaffMembers();
  const teamsQuery = useTeams();
  const stationsQuery = useStations();
  const [validationErrors, setValidationErrors] = useState<TaskEditorValidationErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [conflictTask, setConflictTask] = useState<TaskRecord | null>(null);
  const [overrideTask, setOverrideTask] = useState<TaskRecord | null>(null);
  const currentTask = overrideTask ?? taskQuery.data ?? null;
  const assignmentOptions = useMemo(
    () => buildAssignmentOptions(membersQuery.data ?? []),
    [membersQuery.data]
  );
  const eventOptions = useMemo(() => buildEventOptions(eventsQuery.events), [eventsQuery.events]);
  const teamOptions = useMemo(() => buildTeamOptions(teamsQuery.data ?? []), [teamsQuery.data]);
  const stationOptions = useMemo(
    () => buildStationOptions(stationsQuery.data ?? []),
    [stationsQuery.data]
  );
  const defaultTimeZone = activeWorkspace?.timezone ?? session?.user.timezone ?? "UTC";

  if (!allowed) {
    return (
      <AppShell subtitle={t(`app:tasks.${mode}.subtitle`)} title={t(`app:tasks.${mode}.title`)}>
        <ForbiddenState
          description={t("app:tasks.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("app:tasks.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!session?.token) {
    return (
      <AppShell subtitle={t(`app:tasks.${mode}.subtitle`)} title={t(`app:tasks.${mode}.title`)}>
        <ErrorState
          detail={t("app:tasks.apiRequired")}
          title={t("app:tasks.errorTitle")}
        />
      </AppShell>
    );
  }

  const handleSubmit = async (values: TaskRecord) => {
    const normalized = normalizeTaskEditorValues(createTaskEditorValues(values));

    try {
      setConflictTask(null);
      setSubmitError(null);
      setValidationErrors({});

      const savedTask =
        mode === "create"
          ? await createMutation.mutateAsync(normalized)
          : await updateMutation.mutateAsync({
              ...normalized,
              id: taskId,
              version: currentTask?.version ?? 1,
            });

      router.replace({
        pathname: routes.app.taskDetail,
        params: { taskId: savedTask.id },
      } as Href);
    } catch (error) {
      if (isApiError(error) && error.kind === "conflict") {
        setConflictTask(coerceTaskRecord(error.details));
        setSubmitError(error.message);
        return;
      }

      const mapped = mapTaskFormErrors(error);
      setSubmitError(mapped.submitError);
      setValidationErrors(mapped.validationErrors);
    }
  };

  return (
    <AppShell
      subtitle={t(`app:tasks.${mode}.subtitle`)}
      title={
        mode === "edit" && currentTask?.title
          ? t("app:tasks.edit.titleWithName", { name: currentTask.title })
          : t(`app:tasks.${mode}.title`)
      }
    >
      <View style={{ gap: spacing[4] }}>
        {mode === "edit" && taskQuery.isLoading ? (
          <LoadingState title={t("app:tasks.loadingTitle")} />
        ) : null}
        {mode === "edit" && taskQuery.isError ? (
          <ErrorState
            detail={taskQuery.error instanceof Error ? taskQuery.error.message : undefined}
            onRetry={async () => {
              await taskQuery.refetch();
            }}
            title={t("app:tasks.errorTitle")}
          />
        ) : null}
        {conflictTask ? (
          <ConflictState
            description={t("app:tasks.edit.conflictDescription")}
            onReload={async () => {
              setConflictTask(null);
              setOverrideTask(null);
              await taskQuery.refetch();
            }}
            onReview={() => {
              setOverrideTask(conflictTask);
              setConflictTask(null);
            }}
          />
        ) : null}
        {(mode === "create" || currentTask) && (
          <SectionCard
            description={t(`app:tasks.${mode}.description`)}
            title={t(`app:tasks.${mode}.cardTitle`)}
          >
            <TaskEditorForm
              assigneeOptions={assignmentOptions.length ? assignmentOptions : undefined}
              eventOptions={eventOptions.length ? eventOptions : undefined}
              initialValues={
                mode === "edit"
                  ? currentTask ?? undefined
                  : {
                      priority: "normal",
                      source: "user",
                      status: "todo",
                      type: "general",
                    }
              }
              key={
                mode === "edit" && currentTask
                  ? `task-edit-${currentTask.id}-${currentTask.version}`
                  : `task-create-${defaultTimeZone}`
              }
              mode={mode}
              onCancel={() => router.back()}
              onSubmit={handleSubmit}
              stationOptions={stationOptions.length ? stationOptions : undefined}
              submitting={createMutation.isPending || updateMutation.isPending}
              teamOptions={teamOptions.length ? teamOptions : undefined}
              timeZone={defaultTimeZone}
              validationErrors={{
                ...validationErrors,
                form: submitError ?? validationErrors.form,
              }}
            />
          </SectionCard>
        )}
      </View>
    </AppShell>
  );
}

export function MyTasksScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { activeMembership, hasPermission } = useWorkspace();
  const canView = hasPermission("tasks.view");
  const myTasksQuery = useMyTasks(50);

  if (!canView) {
    return (
      <AppShell subtitle={t("app:tasks.mine.subtitle")} title={t("app:tasks.mine.title")}>
        <ForbiddenState
          description={t("app:tasks.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:tasks.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!activeMembership) {
    return (
      <AppShell subtitle={t("app:tasks.mine.subtitle")} title={t("app:tasks.mine.title")}>
        <ErrorState
          detail={t("app:tasks.missingMembershipDescription")}
          title={t("app:tasks.missingMembershipTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("app:tasks.mine.subtitle")} title={t("app:tasks.mine.title")}>
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          description={t("app:tasks.mine.cardDescription")}
          title={t("app:tasks.mine.cardTitle")}
        >
          {myTasksQuery.isLoading ? (
            <LoadingState title={t("app:tasks.loadingTitle")} />
          ) : myTasksQuery.isError ? (
            <ErrorState
              detail={myTasksQuery.error instanceof Error ? myTasksQuery.error.message : undefined}
              onRetry={async () => {
                await myTasksQuery.refetch();
              }}
              title={t("app:tasks.errorTitle")}
            />
          ) : (
            <View style={{ gap: spacing[4] }}>
              <TaskSummaryCard tasks={myTasksQuery.data ?? []} />
              <MyTasksCard
                maxItems={5}
                onItemPress={(task) =>
                  router.push({
                    pathname: routes.app.taskDetail,
                    params: { taskId: task.id },
                  } as Href)
                }
                tasks={myTasksQuery.data ?? []}
              />
              <TaskList
                onItemPress={(task) =>
                  router.push({
                    pathname: routes.app.taskDetail,
                    params: { taskId: task.id },
                  } as Href)
                }
                onRefresh={async () => {
                  await myTasksQuery.refetch();
                }}
                refreshing={myTasksQuery.isRefetching}
                tasks={myTasksQuery.data ?? []}
              />
            </View>
          )}
        </SectionCard>
      </View>
    </AppShell>
  );
}
