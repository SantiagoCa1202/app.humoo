import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { commandCenterKeys } from "@/features/home/queryKeys";
import {
  createMenu,
  duplicateMenu,
  getMenu,
  getMenuVersions,
  listMenus,
  updateMenu,
} from "@/features/menus/api";
import type { MenuDuplicateOptions, MenusCursorPage } from "@/features/menus/types";
import type { MenuEditorValues } from "@/features/menus/forms";
import { useWorkspace } from "@/features/workspace";

function getApiContext(sessionToken: string | null | undefined, workspaceId: string | null | undefined) {
  if (!sessionToken || !workspaceId) {
    throw new Error("No active workspace session.");
  }

  return { sessionToken, workspaceId };
}

function normalizeSearch(value?: string) {
  const trimmed = value?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : "";
}

export const menuKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "menus"] as const;
  },
  list(workspaceId: string, filters: { perPage?: number; search?: string; status?: string | null } = {}) {
    return [
      ...this.workspace(workspaceId),
      "list",
      {
        perPage: filters.perPage ?? 25,
        search: normalizeSearch(filters.search),
        status: filters.status ?? "",
      },
    ] as const;
  },
  detail(workspaceId: string, menuId: string) {
    return [...this.workspace(workspaceId), menuId] as const;
  },
  versions(workspaceId: string, menuId: string) {
    return [...this.detail(workspaceId, menuId), "versions"] as const;
  },
};

export function useMenus(filters: { perPage?: number; search?: string; status?: string | null } = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      perPage: filters.perPage ?? 25,
      search: normalizeSearch(filters.search),
      status: filters.status ?? "",
    }),
    [filters.perPage, filters.search, filters.status]
  );

  const query = useInfiniteQuery<MenusCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listMenus(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey: workspaceId
      ? menuKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "menus"],
    retry: 1,
  });

  const menus = useMemo(() => query.data?.pages.flatMap((page) => page.data) ?? [], [query.data]);

  return { ...query, menus };
}

export function useMenu(menuId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(menuId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!menuId) {
        throw new Error("Missing menu id.");
      }

      return getMenu(context.sessionToken, context.workspaceId, menuId);
    },
    queryKey:
      workspaceId && menuId
        ? menuKeys.detail(workspaceId, menuId)
        : ["workspace", "no-workspace", "menus", "detail"],
    retry: 1,
  });
}

export function useMenuVersions(menuId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(menuId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!menuId) {
        throw new Error("Missing menu id.");
      }

      return getMenuVersions(context.sessionToken, context.workspaceId, menuId);
    },
    queryKey:
      workspaceId && menuId
        ? menuKeys.versions(workspaceId, menuId)
        : ["workspace", "no-workspace", "menus", "versions"],
    retry: 1,
  });
}

export function useCreateMenu() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: MenuEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createMenu(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(menuKeys.detail(workspaceId, result.menu.id), result);
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: menuKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}

export function useUpdateMenu(menuId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: MenuEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateMenu(context.sessionToken, context.workspaceId, menuId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(menuKeys.detail(workspaceId, menuId), result);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: menuKeys.versions(workspaceId, menuId) }),
        queryClient.invalidateQueries({ queryKey: menuKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
    },
  });
}

export function useDuplicateMenu(menuId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (options: MenuDuplicateOptions) => {
      const context = getApiContext(session?.token, workspaceId);
      return duplicateMenu(context.sessionToken, context.workspaceId, menuId, options);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(menuKeys.detail(workspaceId, result.menu.id), result);
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: menuKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}
