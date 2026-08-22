import { apiRequest } from "@/api/client";
import type { AuditLogFilters, AuditLogRecord, AuditLogsPage } from "@/features/audit/types";

type ApiAuditLog = {
  action: string;
  actor: {
    email: string | null;
    id: string;
    name: string | null;
  } | null;
  created_at: string | null;
  entity_id: string | null;
  entity_type: string | null;
  id: string;
  source: string | null;
};

type ApiAuditLogsPage = {
  data: ApiAuditLog[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

function mapAuditLog(value: ApiAuditLog): AuditLogRecord {
  return {
    action: value.action,
    actor: value.actor,
    createdAt: value.created_at,
    entityId: value.entity_id,
    entityType: value.entity_type,
    id: value.id,
    source: value.source,
  };
}

export async function listAuditLogs(
  authToken: string,
  workspaceId: string,
  filters: AuditLogFilters & { cursor?: string | null; perPage?: number } = {},
): Promise<AuditLogsPage> {
  const response = await apiRequest<ApiAuditLogsPage>("/audit-logs", {
    authToken,
    query: {
      action: filters.action?.trim() || undefined,
      actor_id: filters.actorId?.trim() || undefined,
      cursor: filters.cursor ?? undefined,
      date_from: filters.dateFrom?.trim() || undefined,
      date_to: filters.dateTo?.trim() || undefined,
      entity_id: filters.entityId?.trim() || undefined,
      entity_type: filters.entityType?.trim() || undefined,
      per_page: filters.perPage ?? 50,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapAuditLog),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}
