import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { commandCenterKeys } from "@/features/home/queryKeys";
import {
  confirmPrepRegeneration,
  createPrepList,
  generatePrep,
  getPrepList,
  getPrepVersions,
  listPrepLists,
  previewPrepGeneration,
  regeneratePrep,
  updatePrepItem,
} from "@/features/prep/api";
import type { PrepGenerationOptionsRecord, PrepListCursorPage } from "@/features/prep/types";
import type { PrepItemEditorValues, PrepListEditorValues } from "@/features/prep/forms";
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

export const prepKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "prep"] as const;
  },
  list(
    workspaceId: string,
    filters: { eventId?: string | null; perPage?: number; search?: string; status?: string | null } = {}
  ) {
    return [
      ...this.workspace(workspaceId),
      "list",
      {
        eventId: normalizeString(filters.eventId),
        perPage: filters.perPage ?? 25,
        search: normalizeString(filters.search),
        status: normalizeString(filters.status),
      },
    ] as const;
  },
  detail(workspaceId: string, prepListId: string) {
    return [...this.workspace(workspaceId), prepListId] as const;
  },
  versions(workspaceId: string, prepListId: string) {
    return [...this.detail(workspaceId, prepListId), "versions"] as const;
  },
  preview(workspaceId: string, prepListId: string) {
    return [...this.detail(workspaceId, prepListId), "preview"] as const;
  },
};

export function usePrepLists(
  filters: { eventId?: string | null; perPage?: number; search?: string; status?: string | null } = {}
) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      eventId: normalizeString(filters.eventId),
      perPage: filters.perPage ?? 25,
      search: normalizeString(filters.search),
      status: normalizeString(filters.status),
    }),
    [filters.eventId, filters.perPage, filters.search, filters.status]
  );

  const query = useInfiniteQuery<PrepListCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listPrepLists(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey:
      workspaceId
        ? prepKeys.list(workspaceId, normalizedFilters)
        : ["workspace", "no-workspace", "prep"],
  });

  const prepLists = useMemo(() => query.data?.pages.flatMap((page) => page.data) ?? [], [query.data]);

  return { ...query, prepLists };
}

export function usePrepList(prepListId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(prepListId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return getPrepList(context.sessionToken, context.workspaceId, prepListId);
    },
    queryKey:
      workspaceId && prepListId
        ? prepKeys.detail(workspaceId, prepListId)
        : ["workspace", "no-workspace", "prep", "detail"],
  });
}

export function usePrepVersions(prepListId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(prepListId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return getPrepVersions(context.sessionToken, context.workspaceId, prepListId);
    },
    queryKey:
      workspaceId && prepListId
        ? prepKeys.versions(workspaceId, prepListId)
        : ["workspace", "no-workspace", "prep", "versions"],
  });
}

export function useCreatePrepList() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: PrepListEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createPrepList(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(prepKeys.detail(workspaceId, result.prepList.id), result);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
    },
  });
}

export function usePrepPreview(prepListId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: PrepGenerationOptionsRecord) => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return previewPrepGeneration(context.sessionToken, context.workspaceId, prepListId, values);
    },
    onSuccess: (result) => {
      if (!workspaceId || !prepListId) {
        return;
      }

      queryClient.setQueryData(prepKeys.preview(workspaceId, prepListId), result);
    },
  });
}

export function useGeneratePrep(prepListId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: PrepGenerationOptionsRecord) => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return generatePrep(context.sessionToken, context.workspaceId, prepListId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId || !prepListId || !result.prepList) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: prepKeys.versions(workspaceId, prepListId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
      queryClient.setQueryData(prepKeys.detail(workspaceId, prepListId), {
        currentVersion: result.currentVersion,
        prepList: result.prepList,
        progress: result.preview.progress,
        versions: null,
      });
    },
  });
}

export function useRegeneratePrep(prepListId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  const previewMutation = useMutation({
    mutationFn: async (values: PrepGenerationOptionsRecord) => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return regeneratePrep(context.sessionToken, context.workspaceId, prepListId, values);
    },
    onSuccess: (result) => {
      if (!workspaceId || !prepListId) {
        return;
      }

      queryClient.setQueryData(prepKeys.preview(workspaceId, prepListId), result);
    },
  });

  const confirmMutation = useMutation({
    mutationFn: async (values: PrepGenerationOptionsRecord) => {
      const context = getApiContext(session?.token, workspaceId);

      if (!prepListId) {
        throw new Error("Missing prep list id.");
      }

      return confirmPrepRegeneration(context.sessionToken, context.workspaceId, prepListId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId || !prepListId || !result.prepList) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: prepKeys.versions(workspaceId, prepListId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
      queryClient.setQueryData(prepKeys.detail(workspaceId, prepListId), {
        currentVersion: result.currentVersion,
        prepList: result.prepList,
        progress: result.preview.progress,
        versions: null,
      });
    },
  });

  return { confirmMutation, previewMutation };
}

export function useUpdatePrepItem(itemId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: PrepItemEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);

      if (!itemId) {
        throw new Error("Missing prep item id.");
      }

      return updatePrepItem(context.sessionToken, context.workspaceId, itemId, values);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: prepKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
    },
  });
}
