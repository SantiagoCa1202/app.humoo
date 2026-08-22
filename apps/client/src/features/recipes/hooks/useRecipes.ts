import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { commandCenterKeys } from "@/features/home/queryKeys";
import {
  compareRecipeVersions,
  createRecipe,
  getRecipe,
  getRecipeCatalog,
  getRecipeVersion,
  getRecipeVersions,
  listRecipes,
  updateRecipe,
} from "@/features/recipes/api";
import type { RecipeEditorValues } from "@/features/recipes/forms";
import type { RecipesCursorPage } from "@/features/recipes/types";
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

export const recipeKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "recipes"] as const;
  },
  list(workspaceId: string, filters: { category?: string | null; perPage?: number; search?: string; status?: string | null } = {}) {
    return [
      ...this.workspace(workspaceId),
      "list",
      {
        category: filters.category ?? "",
        perPage: filters.perPage ?? 25,
        search: normalizeSearch(filters.search),
        status: filters.status ?? "",
      },
    ] as const;
  },
  catalog(workspaceId: string) {
    return [...this.workspace(workspaceId), "catalog"] as const;
  },
  detail(workspaceId: string, recipeId: string) {
    return [...this.workspace(workspaceId), recipeId] as const;
  },
  versions(workspaceId: string, recipeId: string) {
    return [...this.detail(workspaceId, recipeId), "versions"] as const;
  },
  version(workspaceId: string, recipeId: string, versionId: string) {
    return [...this.versions(workspaceId, recipeId), versionId] as const;
  },
  comparison(workspaceId: string, recipeId: string, versionId: string, baseVersionId?: string | null) {
    return [...this.version(workspaceId, recipeId, versionId), "comparison", baseVersionId ?? "previous"] as const;
  },
};

export function useRecipes(filters: { category?: string | null; perPage?: number; search?: string; status?: string | null } = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      category: filters.category ?? "",
      perPage: filters.perPage ?? 25,
      search: normalizeSearch(filters.search),
      status: filters.status ?? "",
    }),
    [filters.category, filters.perPage, filters.search, filters.status]
  );

  const query = useInfiniteQuery<RecipesCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listRecipes(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey: workspaceId
      ? recipeKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "recipes"],
  });

  const recipes = useMemo(() => query.data?.pages.flatMap((page) => page.data) ?? [], [query.data]);
  const catalog = useMemo(() => query.data?.pages[0]?.meta?.catalog ?? null, [query.data]);

  return { ...query, catalog, recipes };
}

export function useRecipeCatalog() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getRecipeCatalog(context.sessionToken, context.workspaceId);
    },
    queryKey: workspaceId ? recipeKeys.catalog(workspaceId) : ["workspace", "no-workspace", "recipes", "catalog"],
  });
}

export function useRecipe(recipeId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(recipeId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!recipeId) {
        throw new Error("Missing recipe id.");
      }

      return getRecipe(context.sessionToken, context.workspaceId, recipeId);
    },
    queryKey: workspaceId && recipeId
      ? recipeKeys.detail(workspaceId, recipeId)
      : ["workspace", "no-workspace", "recipes", "detail"],
  });
}

export function useRecipeVersions(recipeId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(recipeId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!recipeId) {
        throw new Error("Missing recipe id.");
      }

      return getRecipeVersions(context.sessionToken, context.workspaceId, recipeId);
    },
    queryKey: workspaceId && recipeId
      ? recipeKeys.versions(workspaceId, recipeId)
      : ["workspace", "no-workspace", "recipes", "versions"],
  });
}

export function useRecipeVersion(recipeId?: string | null, versionId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(recipeId) && Boolean(versionId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!recipeId || !versionId) {
        throw new Error("Missing recipe version identifier.");
      }

      return getRecipeVersion(context.sessionToken, context.workspaceId, recipeId, versionId);
    },
    queryKey: workspaceId && recipeId && versionId
      ? recipeKeys.version(workspaceId, recipeId, versionId)
      : ["workspace", "no-workspace", "recipes", "version"],
  });
}

export function useRecipeComparison(recipeId?: string | null, versionId?: string | null, baseVersionId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId) && Boolean(recipeId) && Boolean(versionId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!recipeId || !versionId) {
        throw new Error("Missing recipe comparison identifier.");
      }

      return compareRecipeVersions(context.sessionToken, context.workspaceId, recipeId, versionId, baseVersionId);
    },
    queryKey: workspaceId && recipeId && versionId
      ? recipeKeys.comparison(workspaceId, recipeId, versionId, baseVersionId)
      : ["workspace", "no-workspace", "recipes", "comparison"],
  });
}

export function useCreateRecipe() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: RecipeEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return createRecipe(context.sessionToken, context.workspaceId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(recipeKeys.detail(workspaceId, result.recipe.id), result);
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: recipeKeys.workspace(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: commandCenterKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}

export function useUpdateRecipe(recipeId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (values: RecipeEditorValues) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateRecipe(context.sessionToken, context.workspaceId, recipeId, values);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(recipeKeys.detail(workspaceId, recipeId), result);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: recipeKeys.versions(workspaceId, recipeId) }),
        queryClient.invalidateQueries({ queryKey: recipeKeys.workspace(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: commandCenterKeys.workspace(workspaceId) }),
      ]);
    },
  });
}
