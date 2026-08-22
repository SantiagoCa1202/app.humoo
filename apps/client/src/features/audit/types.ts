export type AuditActor = {
  email: string | null;
  id: string;
  name: string | null;
};

export type AuditLogRecord = {
  action: string;
  actor: AuditActor | null;
  createdAt: string | null;
  entityId: string | null;
  entityType: string | null;
  id: string;
  source: string | null;
};

export type AuditLogFilters = {
  action?: string;
  actorId?: string;
  dateFrom?: string;
  dateTo?: string;
  entityId?: string;
  entityType?: string;
};

export type AuditLogsPage = {
  data: AuditLogRecord[];
  nextCursor: string | null;
  nextPageUrl: string | null;
  path: string;
  perPage: number;
  prevCursor: string | null;
  prevPageUrl: string | null;
};
