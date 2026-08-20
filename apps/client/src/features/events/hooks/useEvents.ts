import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { commandCenterKeys } from "@/features/home/queryKeys";
import { useWorkspace } from "@/features/workspace";
import {
  createEvent,
  deleteEvent,
  getEvent,
  listEvents,
  updateEvent,
  type EventListFilters,
} from "@/features/events/api";
import type {
  CreateEventInput,
  EventsCursorPage,
  UpdateEventInput,
} from "@/features/events/types";

function normalizeSearch(value?: string) {
  const trimmed = value?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : "";
}

function getApiContext(
  sessionToken: string | null | undefined,
  workspaceId: string | null | undefined
) {
  if (!sessionToken || !workspaceId) {
    throw new Error("No active workspace session.");
  }

  return {
    sessionToken,
    workspaceId,
  };
}

export const eventKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "events"] as const;
  },
  list(
    workspaceId: string,
    filters: EventListFilters = {}
  ) {
    return [
      ...this.workspace(workspaceId),
      "list",
      {
        clientId: filters.clientId ?? "",
        dateFrom: filters.dateFrom ?? "",
        dateTo: filters.dateTo ?? "",
        perPage: filters.perPage ?? 25,
        search: normalizeSearch(filters.search),
        serviceType: filters.serviceType ?? "",
        statuses: [...(filters.statuses ?? [])].sort(),
        venueId: filters.venueId ?? "",
      },
    ] as const;
  },
  detail(workspaceId: string, eventId: string) {
    return [...this.workspace(workspaceId), eventId] as const;
  },
};

export function useEvents(filters: EventListFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      clientId: filters.clientId ?? "",
      dateFrom: filters.dateFrom ?? "",
      dateTo: filters.dateTo ?? "",
      perPage: filters.perPage ?? 25,
      search: normalizeSearch(filters.search),
      serviceType: filters.serviceType ?? "",
      statuses: [...(filters.statuses ?? [])].sort(),
      venueId: filters.venueId ?? "",
    }),
    [
      filters.clientId,
      filters.dateFrom,
      filters.dateTo,
      filters.perPage,
      filters.search,
      filters.serviceType,
      filters.statuses,
      filters.venueId,
    ]
  );

  const query = useInfiniteQuery<EventsCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);

      return listEvents(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey: workspaceId
      ? eventKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "events"],
    retry: 1,
  });

  const events = useMemo(
    () => query.data?.pages.flatMap((page) => page.data) ?? [],
    [query.data]
  );

  return {
    ...query,
    events,
  };
}

export function useEvent(eventId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(eventId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!eventId) {
        throw new Error("Missing event id.");
      }

      return getEvent(context.sessionToken, context.workspaceId, eventId);
    },
    queryKey:
      workspaceId && eventId
        ? eventKeys.detail(workspaceId, eventId)
        : ["workspace", "no-workspace", "events", "detail"],
    retry: 1,
  });
}

export function useCreateEvent() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: CreateEventInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return createEvent(context.sessionToken, context.workspaceId, input);
    },
    onSuccess: async (event) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(eventKeys.detail(workspaceId, event.id), event);
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: eventKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}

export function useUpdateEvent(eventId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: UpdateEventInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateEvent(context.sessionToken, context.workspaceId, eventId, input);
    },
    onSuccess: async (event) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(eventKeys.detail(workspaceId, eventId), event);
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: eventKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}

export function useDeleteEvent(eventId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      await deleteEvent(context.sessionToken, context.workspaceId, eventId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      queryClient.removeQueries({
        queryKey: eventKeys.detail(workspaceId, eventId),
      });
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: eventKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}
