import { useInfiniteQuery } from "@tanstack/react-query";
import { useMemo } from "react";

import { useAuth } from "@/auth/useAuth";
import { listAuditLogs } from "@/features/audit/api";
import type { AuditLogFilters, AuditLogsPage } from "@/features/audit/types";
import { useWorkspace } from "@/features/workspace";

function normalizeFilters(filters: AuditLogFilters) {
  return {
    action: filters.action?.trim() ?? "",
    actorId: filters.actorId?.trim() ?? "",
    dateFrom: filters.dateFrom?.trim() ?? "",
    dateTo: filters.dateTo?.trim() ?? "",
    entityId: filters.entityId?.trim() ?? "",
    entityType: filters.entityType?.trim() ?? "",
  };
}

export const auditKeys = {
  list: (workspaceId: string, filters: ReturnType<typeof normalizeFilters>) =>
    ["workspace", workspaceId, "audit-logs", filters] as const,
};

export function useAuditLogs(filters: AuditLogFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => normalizeFilters(filters),
    [filters.action, filters.actorId, filters.dateFrom, filters.dateTo, filters.entityId, filters.entityType],
  );

  return useInfiniteQuery<AuditLogsPage, Error>({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    getNextPageParam: (page) => page.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: ({ pageParam }) => {
      if (!session?.token || !workspaceId) {
        throw new Error("Audit API context is unavailable.");
      }

      return listAuditLogs(session.token, workspaceId, {
        ...normalizedFilters,
        cursor: pageParam as string | null,
      });
    },
    queryKey: workspaceId
      ? auditKeys.list(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "audit-logs"],
    retry: 1,
  });
}
