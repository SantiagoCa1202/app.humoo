import { apiRequest } from "@/api/client";
import type { TaskEditorValues, TaskFilters } from "@/features/tasks/forms";
import type { TaskRecord } from "@/features/tasks/types";

type ApiTaskUser = {
  id: string | null;
  name: string | null;
  email?: string | null;
};

type ApiTaskAssignment = {
  id: string;
  membership_id: string;
  status: "assigned" | "accepted" | "declined" | "completed" | "cancelled" | null;
  is_primary: boolean;
  assigned_at: string | null;
  accepted_at: string | null;
  completed_at: string | null;
  role_label: string | null;
  user: ApiTaskUser | null;
};

type ApiTask = {
  id: string;
  workspace_id: string;
  event_id: string | null;
  station_id: string | null;
  team_id: string | null;
  title: string;
  description: string | null;
  type: string | null;
  status: TaskRecord["status"];
  priority: TaskRecord["priority"];
  starts_at: string | null;
  due_at: string | null;
  completed_at: string | null;
  blocked_reason: string | null;
  source: string | null;
  source_type: string | null;
  source_id: string | null;
  version: number;
  metadata: Record<string, unknown> | null;
  assignments: ApiTaskAssignment[];
  event: {
    id: string;
    name: string | null;
    starts_at: string | null;
    timezone: string | null;
  } | null;
  team: {
    id: string;
    key: string | null;
    name: string | null;
    status: string | null;
    type: string | null;
  } | null;
  station: {
    id: string;
    key: string | null;
    name: string | null;
    status: string | null;
    type: string | null;
    team_id: string | null;
    team: {
      id: string;
      key: string | null;
      name: string | null;
      status: string | null;
      type: string | null;
    } | null;
  } | null;
  completed_by: ApiTaskUser | null;
  created_by: ApiTaskUser | null;
  updated_by: ApiTaskUser | null;
  created_at: string | null;
  updated_at: string | null;
};

type ApiTasksCursorPage = {
  data: ApiTask[];
  path: string;
  per_page: number;
  next_cursor: string | null;
  next_page_url: string | null;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

type TaskResponse = {
  data: ApiTask;
};

export type TaskListFilters = TaskFilters & {
  cursor?: string | null;
  perPage?: number;
};

export type TasksCursorPage = {
  data: TaskRecord[];
  nextCursor: string | null;
  nextPageUrl: string | null;
  path: string;
  perPage: number;
  prevCursor: string | null;
  prevPageUrl: string | null;
};

export async function listTasks(
  authToken: string,
  workspaceId: string,
  filters: TaskListFilters = {}
): Promise<TasksCursorPage> {
  const response = await apiRequest<ApiTasksCursorPage>("/tasks", {
    authToken,
    query: {
      assignee_id: filters.assigneeIds?.length ? filters.assigneeIds : undefined,
      cursor: filters.cursor ?? undefined,
      due_from: filters.dueFrom ?? undefined,
      due_to: filters.dueTo ?? undefined,
      event_id: filters.eventId ?? undefined,
      overdue: filters.overdue ? "1" : undefined,
      per_page: filters.perPage ?? undefined,
      priority: filters.priorities?.length ? filters.priorities : undefined,
      search: filters.search?.trim() || undefined,
      station_id: filters.stationId ?? undefined,
      status: filters.statuses?.length ? filters.statuses : undefined,
      team_id: filters.teamId ?? undefined,
      unassigned: filters.unassigned ? "1" : undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapTask),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getTask(
  authToken: string,
  workspaceId: string,
  taskId: string
): Promise<TaskRecord> {
  const response = await apiRequest<TaskResponse>(`/tasks/${taskId}`, {
    authToken,
    workspaceId,
  });

  return mapTask(response.data);
}

export async function createTask(
  authToken: string,
  workspaceId: string,
  values: TaskEditorValues
): Promise<TaskRecord> {
  const response = await apiRequest<TaskResponse>("/tasks", {
    method: "POST",
    authToken,
    workspaceId,
    body: JSON.stringify(buildTaskPayload(values, false)),
  });

  return mapTask(response.data);
}

export async function updateTask(
  authToken: string,
  workspaceId: string,
  taskId: string,
  values: TaskEditorValues
): Promise<TaskRecord> {
  const response = await apiRequest<TaskResponse>(`/tasks/${taskId}`, {
    method: "PATCH",
    authToken,
    workspaceId,
    body: JSON.stringify(buildTaskPayload(values, true)),
  });

  return mapTask(response.data);
}

export async function deleteTask(
  authToken: string,
  workspaceId: string,
  taskId: string
): Promise<void> {
  await apiRequest<null>(`/tasks/${taskId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

export function coerceTaskRecord(value: unknown): TaskRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapTask(value as ApiTask);
}

function buildTaskPayload(values: TaskEditorValues, includeVersion: boolean) {
  const assignments = (values.assignments ?? [])
    .filter((assignment) => assignment.membershipId?.trim())
    .map((assignment, index) => ({
      is_primary: assignment.isPrimary ?? index === 0,
      membership_id: assignment.membershipId?.trim(),
      status: assignment.status ?? "assigned",
    }));

  return {
    assignments,
    blocked_reason: values.blockedReason ?? null,
    description: values.description ?? null,
    due_at: values.dueAt ?? null,
    event_id: values.eventId ?? null,
    metadata: values.metadata ?? null,
    priority: values.priority ?? "normal",
    source: values.source ?? "user",
    source_id: values.sourceId ?? null,
    source_type: values.sourceType ?? null,
    starts_at: values.startsAt ?? null,
    station_id: values.stationId ?? null,
    status: values.status ?? "todo",
    team_id: values.teamId ?? null,
    title: values.title,
    type: values.type ?? "general",
    ...(includeVersion ? { version: values.version ?? 1 } : {}),
  };
}

function mapTask(task: ApiTask): TaskRecord {
  return {
    assignments: task.assignments?.map((assignment) => ({
      acceptedAt: assignment.accepted_at,
      assignedAt: assignment.assigned_at,
      completedAt: assignment.completed_at,
      id: assignment.id,
      isPrimary: assignment.is_primary,
      membershipId: assignment.membership_id,
      roleLabel: assignment.role_label,
      status: assignment.status,
      user: assignment.user
        ? {
            id: assignment.user.id,
            name: assignment.user.name,
          }
        : null,
    })),
    blockedReason: task.blocked_reason,
    completedAt: task.completed_at,
    completedBy: mapTaskUser(task.completed_by),
    createdAt: task.created_at,
    createdBy: mapTaskUser(task.created_by),
    description: task.description,
    dueAt: task.due_at,
    event: task.event
      ? {
          id: task.event.id,
          name: task.event.name,
          startsAt: task.event.starts_at,
          timezone: task.event.timezone,
        }
      : null,
    eventId: task.event_id,
    id: task.id,
    metadata: task.metadata,
    priority: task.priority,
    source: task.source,
    sourceId: task.source_id,
    sourceType: task.source_type,
    startsAt: task.starts_at,
    station: task.station
      ? {
          id: task.station.id,
          key: task.station.key,
          name: task.station.name,
          status: task.station.status,
          team: task.station.team
            ? {
                id: task.station.team.id,
                key: task.station.team.key,
                name: task.station.team.name,
                status: task.station.team.status,
                type: task.station.team.type,
              }
            : null,
          teamId: task.station.team_id,
          type: task.station.type,
        }
      : null,
    stationId: task.station_id,
    status: task.status,
    team: task.team
      ? {
          id: task.team.id,
          key: task.team.key,
          name: task.team.name,
          status: task.team.status,
          type: task.team.type,
        }
      : null,
    teamId: task.team_id,
    title: task.title,
    type: task.type,
    updatedAt: task.updated_at,
    updatedBy: mapTaskUser(task.updated_by),
    version: task.version,
  };
}

function mapTaskUser(user?: ApiTaskUser | null) {
  if (!user) {
    return null;
  }

  return {
    id: user.id,
    name: user.name,
  };
}
