import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import {
  getDocument,
  getDocumentComparison,
  getDocumentExtraction,
  getDocumentVersions,
  linkDocumentToEvent,
  listDocuments,
  reviewDocumentExtraction,
  uploadDocument,
} from "@/features/documents/api";
import { useWorkspace } from "@/features/workspace";
import type {
  DocumentListFilters,
  DocumentUploadInput,
  DocumentsCursorPage,
} from "@/features/documents";

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

export const documentKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "documents"] as const;
  },
  list(workspaceId: string, filters: DocumentListFilters = {}) {
    return [
      ...this.workspace(workspaceId),
      "list",
      {
        eventId: filters.eventId ?? "",
        perPage: filters.perPage ?? 25,
        processingStatus: filters.processingStatus ?? "",
        search: normalizeSearch(filters.search),
        type: filters.type ?? "beo",
      },
    ] as const;
  },
  detail(workspaceId: string, documentId: string) {
    return [...this.workspace(workspaceId), documentId] as const;
  },
  versions(workspaceId: string, documentId: string) {
    return [...this.detail(workspaceId, documentId), "versions"] as const;
  },
  extraction(workspaceId: string, documentId: string) {
    return [...this.detail(workspaceId, documentId), "extraction"] as const;
  },
  comparison(workspaceId: string, documentId: string) {
    return [...this.detail(workspaceId, documentId), "comparison"] as const;
  },
};

export function useDocuments(filters: DocumentListFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      eventId: filters.eventId ?? "",
      perPage: filters.perPage ?? 25,
      processingStatus: filters.processingStatus ?? "",
      search: normalizeSearch(filters.search),
      type: filters.type ?? "beo",
    }),
    [filters.eventId, filters.perPage, filters.processingStatus, filters.search, filters.type]
  );

  const query = useInfiniteQuery<DocumentsCursorPage, Error>({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listDocuments(context.sessionToken, context.workspaceId, {
        ...normalizedFilters,
        cursor: (pageParam as string | null) ?? null,
      });
    },
    queryKey: workspaceId
      ? documentKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "documents"],
    retry: 1,
  });

  const documents = useMemo(
    () => query.data?.pages.flatMap((page) => page.data) ?? [],
    [query.data]
  );

  return { ...query, documents };
}

export function useDocument(documentId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(documentId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!documentId) {
        throw new Error("Missing document id.");
      }

      return getDocument(context.sessionToken, context.workspaceId, documentId);
    },
    queryKey:
      workspaceId && documentId
        ? documentKeys.detail(workspaceId, documentId)
        : ["workspace", "no-workspace", "documents", "detail"],
    retry: 1,
  });
}

export function useDocumentVersions(documentId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(documentId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!documentId) {
        throw new Error("Missing document id.");
      }

      return getDocumentVersions(context.sessionToken, context.workspaceId, documentId);
    },
    queryKey:
      workspaceId && documentId
        ? documentKeys.versions(workspaceId, documentId)
        : ["workspace", "no-workspace", "documents", "versions"],
    retry: 1,
  });
}

export function useDocumentExtraction(documentId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(documentId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!documentId) {
        throw new Error("Missing document id.");
      }

      return getDocumentExtraction(context.sessionToken, context.workspaceId, documentId);
    },
    queryKey:
      workspaceId && documentId
        ? documentKeys.extraction(workspaceId, documentId)
        : ["workspace", "no-workspace", "documents", "extraction"],
    retry: 1,
  });
}

export function useDocumentComparison(documentId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(documentId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);

      if (!documentId) {
        throw new Error("Missing document id.");
      }

      return getDocumentComparison(context.sessionToken, context.workspaceId, documentId);
    },
    queryKey:
      workspaceId && documentId
        ? documentKeys.comparison(workspaceId, documentId)
        : ["workspace", "no-workspace", "documents", "comparison"],
    retry: 1,
  });
}

export function useUploadDocument() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: DocumentUploadInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return uploadDocument(context.sessionToken, context.workspaceId, input);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(
        documentKeys.detail(workspaceId, result.document.id),
        result
      );
      await queryClient.invalidateQueries({
        queryKey: documentKeys.workspace(workspaceId),
      });
    },
  });
}

export function useLinkDocumentToEvent(documentId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (eventId: string) => {
      const context = getApiContext(session?.token, workspaceId);
      return linkDocumentToEvent(context.sessionToken, context.workspaceId, documentId, eventId);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(
        documentKeys.detail(workspaceId, documentId),
        result
      );
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: documentKeys.versions(workspaceId, documentId),
        }),
        queryClient.invalidateQueries({
          queryKey: documentKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}

export function useReviewDocumentExtraction(documentId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: Parameters<typeof reviewDocumentExtraction>[3]) => {
      const context = getApiContext(session?.token, workspaceId);
      return reviewDocumentExtraction(context.sessionToken, context.workspaceId, documentId, input);
    },
    onSuccess: async (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(documentKeys.extraction(workspaceId, documentId), {
        document: result.document,
        extraction: result.extraction,
      });
      queryClient.setQueryData(documentKeys.detail(workspaceId, documentId), {
        beo: null,
        document: result.document,
      });
      if (result.comparison) {
        queryClient.setQueryData(
          documentKeys.comparison(workspaceId, documentId),
          result.comparison
        );
      }
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: documentKeys.versions(workspaceId, documentId),
        }),
        queryClient.invalidateQueries({
          queryKey: documentKeys.workspace(workspaceId),
        }),
      ]);
    },
  });
}
