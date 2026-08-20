import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { commandCenterKeys } from "@/features/home/queryKeys";
import {
  coerceTaskRecord,
  createTask,
  deleteTask,
  getTask,
  listTasks,
  updateTask,
  type TaskListFilters,
  type TasksCursorPage,
} from "@/features/tasks/api";
import type { TaskEditorValues } from "@/features/tasks/forms";
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

function normalizeFilters(filters: TaskListFilters = {}) {
  return {
    assigneeIds: [...(filters.assigneeIds ?? [])].map((value) => value.trim()).filter(Boolean).sort(),
    dueFrom: normalizeString(filters.dueFrom),
    dueTo: normalizeString(filters.dueTo),
    eventId: normalizeString(filters.eventId),
    overdue: Boolean(filters.overdue),
    perPage: filters.perPage ?? 25,
    priorities: [...(filters.priorities ?? [])].sort(),
    search: normalizeString(filters.search),
    stationId: normalizeString(filters.stationId),
    statuses: [...(filters.statuses ?? [])].sort(),
    teamId: normalizeString(filters.teamId),
    unassigned: Boolean(filters.unassigned),
  };
}

export const taskKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "tasks"] as const;
  },
  list(workspaceId: string, filters: TaskListFilters = {}) {
    return [...this.workspace(workspaceId), "list", normalizeFilters(filters)] as const;
  },
  detail(workspaceId: string, taskId: string) {
    return [...this.workspace(workspaceId), "detail", taskId] as const;
  },
  mine(workspaceId: string, membershipId: string) {
    return [...this.workspace(workspaceId), "my-tasks", membershipId] as const;
  },
};

export function useTasks(filters: TaskListFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => normalizeFilters(filters),
    [
      filters.assigneeIds,
      filters.dueFrom,
      filters.dueTo,
      filters.eventId,
      filters.overdue,
      filters.perPage,
      filters.priorities,
      filters.search,
      filters.stationId,
      filters.statuses,
      filters.teamId,
      filters.unassigned,
    ]
  );

  const query = useInfiniteQuery<TasksCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listTasks(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey: workspaceId
      ? taskKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "tasks"],
    retry: 1,
  });

  const tasks = useMemo(() => query.data?.pages.flatMap((page) => page.data) ?? [], [query.data]);

  return {
    ...query,
    tasks,
  };
}

export function useTask(taskId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(taskId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!taskId) {
        throw new Error("Missing task id.");
      }

      return getTask(context.sessionToken, context.workspaceId, taskId);
    },
    queryKey:
      workspaceId && taskId
        ? taskKeys.detail(workspaceId, taskId)
        : ["workspace", "no-workspace", "tasks", "detail"],
    retry: 1,
  });
}

export function useMyTasks(perPage = 25) {
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(membershipId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!membershipId) {
        throw new Error("Missing active membership.");
      }

      const page = await listTasks(context.sessionToken, context.workspaceId, {
        assigneeIds: [membershipId],
        perPage,
      });

      return page.data;
    },
    queryKey:
      workspaceId && membershipId
        ? taskKeys.mine(workspaceId, membershipId)
        : ["workspace", "no-workspace", "tasks", "mine"],
    retry: 1,
  });
}

export function useCreateTask() {
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;

  return useMutation({
    mutationFn: async (values: TaskEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createTask(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async (task) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(taskKeys.detail(workspaceId, task.id ?? ""), task);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: taskKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
        membershipId
          ? queryClient.invalidateQueries({ queryKey: taskKeys.mine(workspaceId, membershipId) })
          : Promise.resolve(),
      ]);
    },
  });
}

export function useUpdateTask(taskId: string) {
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;

  return useMutation({
    mutationFn: async (values: TaskEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateTask(context.sessionToken, context.workspaceId, taskId, values);
    },
    onSuccess: async (task) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(taskKeys.detail(workspaceId, taskId), task);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: taskKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
        membershipId
          ? queryClient.invalidateQueries({ queryKey: taskKeys.mine(workspaceId, membershipId) })
          : Promise.resolve(),
      ]);
    },
  });
}

export function useDeleteTask(taskId: string) {
  const { session } = useAuth();
  const { activeMembership, activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;
  const membershipId = activeMembership?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      await deleteTask(context.sessionToken, context.workspaceId, taskId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      queryClient.removeQueries({ queryKey: taskKeys.detail(workspaceId, taskId) });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: taskKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
        membershipId
          ? queryClient.invalidateQueries({ queryKey: taskKeys.mine(workspaceId, membershipId) })
          : Promise.resolve(),
      ]);
    },
  });
}

export { coerceTaskRecord };
