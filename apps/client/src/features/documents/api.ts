import { apiRequest } from "@/api/client";
import { coerceEventRecord } from "@/features/events/api";
import type { InventoryUserReference } from "@/features/inventory";
import type {
  BEOChangeWarningRecord,
  BEOComparisonRecord,
  BEOFieldChangeRecord,
  BEOImpactRecord,
  BEOExtractionRecord,
  BeoRecord,
  BeoVersionRecord,
  DocumentDetailRecord,
  DocumentListFilters,
  DocumentRecord,
  DocumentUploadInput,
  DocumentsCursorPage,
  ExtractedFieldRecord,
  ExtractionRunRecord,
  BEOVersionComparisonSection,
} from "@/features/documents";

type ApiUserReference = {
  id?: string | null;
  name?: string | null;
};

type ApiDocumentLink = {
  entity_id?: string | null;
  entity_type?: string | null;
  id?: string | null;
  is_primary?: boolean | null;
  relationship_type?: string | null;
};

type ApiDocument = {
  created_at?: string | null;
  disk?: string | null;
  download_url?: string | null;
  extension?: string | null;
  id: string;
  latest_beo_version?: ApiBeoVersion | null;
  latest_extraction_run?: ApiExtractionRun | null;
  linked_event?: unknown;
  links?: ApiDocumentLink[] | null;
  metadata?: Record<string, unknown> | null;
  mime_type?: string | null;
  name: string;
  original_filename?: string | null;
  processing_error?: string | null;
  processing_status?: string | null;
  scan_status?: string | null;
  size?: number | null;
  type?: string | null;
  uploaded_by?: ApiUserReference | null;
  updated_at?: string | null;
};

type ApiBeo = {
  approved_at?: string | null;
  approved_by?: ApiUserReference | null;
  created_at?: string | null;
  created_by?: ApiUserReference | null;
  current_version?: number | null;
  event?: unknown;
  event_id?: string | null;
  id: string;
  status?: string | null;
  updated_at?: string | null;
};

type ApiBeoVersion = {
  approved_at?: string | null;
  approved_by?: ApiUserReference | null;
  beo_id?: string | null;
  change_summary?: string | null;
  created_at?: string | null;
  created_by?: ApiUserReference | null;
  document?: ApiDocument | null;
  document_id?: string | null;
  id: string;
  review_notes?: string | null;
  snapshot_json?: Record<string, unknown> | null;
  source?: string | null;
  status?: string | null;
  updated_at?: string | null;
  version?: number | null;
};

type ApiExtractionRun = {
  attempt?: number | null;
  beo_version_id?: string | null;
  completed_at?: string | null;
  correlation_id?: string | null;
  created_at?: string | null;
  document_id?: string | null;
  error_code?: string | null;
  error_message?: string | null;
  id: string;
  latency_ms?: number | null;
  metadata_json?: Record<string, unknown> | null;
  model_key?: string | null;
  prompt_version?: string | null;
  provider?: string | null;
  requested_by?: ApiUserReference | null;
  schema_version?: string | null;
  started_at?: string | null;
  status?: string | null;
  updated_at?: string | null;
  usage_json?: Record<string, unknown> | null;
};

type ApiExtractedField = {
  confidence?: number | null;
  corrected_value_json?: unknown | null;
  corrected_value_text?: string | null;
  created_at?: string | null;
  extraction_run_id?: string | null;
  field_key: string;
  id: string;
  page_number?: number | null;
  raw_value?: string | null;
  review_notes?: string | null;
  reviewed?: boolean | null;
  reviewed_at?: string | null;
  reviewed_by?: ApiUserReference | null;
  review_status?: string | null;
  source_location?: Record<string, unknown> | null;
  updated_at?: string | null;
  value_json?: unknown | null;
  value_text?: string | null;
  value_type?: string | null;
};

type ApiVersionChange = {
  change_type?: string | null;
  confidence?: number | null;
  field_key: string;
  id: string;
  impact?: string | null;
  label?: string | null;
  next_value?: unknown | null;
  previous_value?: unknown | null;
  section_id?: string | null;
  section_title?: string | null;
  translation_key?: string | null;
  value_type?: string | null;
};

type ApiComparisonSection = {
  change_ids: string[];
  description?: string | null;
  id: string;
  title: string;
};

type ApiImpact = {
  entity_id?: string | null;
  entity_type: string;
  id: string;
  impact_type?: string | null;
  requires_regeneration?: boolean | null;
  requires_review?: boolean | null;
  severity?: string | null;
  summary?: string | null;
  title?: string | null;
  translation_key?: string | null;
};

type ApiWarning = {
  description?: string | null;
  id: string;
  severity?: string | null;
  title?: string | null;
};

type ApiCursorResponse = {
  data: ApiDocument[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

export async function listDocuments(
  authToken: string,
  workspaceId: string,
  filters: DocumentListFilters = {}
): Promise<DocumentsCursorPage> {
  const response = await apiRequest<ApiCursorResponse>("/documents", {
    authToken,
    query: {
      cursor: filters.cursor ?? undefined,
      event_id: filters.eventId ?? undefined,
      per_page: filters.perPage ?? undefined,
      processing_status: filters.processingStatus ?? undefined,
      search: filters.search?.trim() || undefined,
      type: filters.type ?? undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapDocument),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getDocument(
  authToken: string,
  workspaceId: string,
  documentId: string
): Promise<DocumentDetailRecord> {
  const response = await apiRequest<{
    data: ApiDocument;
    meta?: { beo?: ApiBeo | null } | null;
  }>(`/documents/${documentId}`, {
    authToken,
    workspaceId,
  });

  return {
    beo: response.meta?.beo ? mapBeo(response.meta.beo) : null,
    document: mapDocument(response.data),
  };
}

export async function uploadDocument(
  authToken: string,
  workspaceId: string,
  input: DocumentUploadInput
): Promise<DocumentDetailRecord> {
  const formData = new FormData();
  formData.append("type", input.type?.trim() || "beo");
  formData.append("source", input.source ?? "upload");

  if (input.eventId) {
    formData.append("event_id", input.eventId);
  }

  formData.append("file", {
    name: input.file.name,
    type: input.file.type ?? input.file.mimeType ?? "application/octet-stream",
    uri: input.file.uri,
  } as never);

  const response = await apiRequest<{
    data: ApiDocument;
    meta?: { beo?: ApiBeo | null } | null;
  }>("/documents", {
    method: "POST",
    authToken,
    body: formData,
    workspaceId,
  });

  return {
    beo: response.meta?.beo ? mapBeo(response.meta.beo) : null,
    document: mapDocument(response.data),
  };
}

export async function linkDocumentToEvent(
  authToken: string,
  workspaceId: string,
  documentId: string,
  eventId: string
): Promise<DocumentDetailRecord> {
  const response = await apiRequest<{
    data: ApiDocument;
    meta?: { beo?: ApiBeo | null } | null;
  }>(`/documents/${documentId}/event-link`, {
    method: "PUT",
    authToken,
    body: JSON.stringify({ event_id: eventId }),
    workspaceId,
  });

  return {
    beo: response.meta?.beo ? mapBeo(response.meta.beo) : null,
    document: mapDocument(response.data),
  };
}

export async function getDocumentVersions(
  authToken: string,
  workspaceId: string,
  documentId: string
): Promise<BeoVersionRecord[]> {
  const response = await apiRequest<{ data: ApiBeoVersion[] }>(
    `/documents/${documentId}/versions`,
    {
      authToken,
      workspaceId,
    }
  );

  return response.data.map(mapBeoVersion);
}

export async function getDocumentExtraction(
  authToken: string,
  workspaceId: string,
  documentId: string
): Promise<{
  document: DocumentRecord;
  extraction: BEOExtractionRecord;
}> {
  const response = await apiRequest<{
    data: {
      document: ApiDocument;
      run?: ApiExtractionRun | null;
      fields: ApiExtractedField[];
      sections?: {
        description?: string | null;
        field_keys: string[];
        id: string;
        title: string;
      }[] | null;
    };
  }>(`/documents/${documentId}/extraction`, {
    authToken,
    workspaceId,
  });

  return {
    document: mapDocument(response.data.document),
    extraction: {
      fields: response.data.fields.map(mapExtractedField),
      run: response.data.run ? mapExtractionRun(response.data.run) : null,
      sections: response.data.sections?.map((section) => ({
        description: section.description ?? null,
        fieldKeys: section.field_keys,
        id: section.id,
        title: section.title,
      })),
    },
  };
}

export async function reviewDocumentExtraction(
  authToken: string,
  workspaceId: string,
  documentId: string,
  input: {
    expectedUpdatedAt: string;
    fields: {
      id: string;
      correctedValueJson?: unknown | null;
      correctedValueText?: string | null;
      reviewNotes?: string | null;
      reviewStatus: string;
    }[];
    reviewNotes?: string | null;
  }
): Promise<{
  comparison?: BEOComparisonRecord | null;
  document: DocumentRecord;
  extraction: BEOExtractionRecord;
  version?: BeoVersionRecord | null;
}> {
  const response = await apiRequest<{
    data: {
      comparison?: {
        base_version?: ApiBeoVersion | null;
        changes: ApiVersionChange[];
        impacts?: ApiImpact[] | null;
        sections?: ApiComparisonSection[] | null;
        target_version: ApiBeoVersion;
      } | null;
      document: ApiDocument;
      fields: ApiExtractedField[];
      run: ApiExtractionRun;
      sections?: {
        description?: string | null;
        field_keys: string[];
        id: string;
        title: string;
      }[] | null;
      version?: ApiBeoVersion | null;
    };
  }>(`/documents/${documentId}/review`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify({
      expected_updated_at: input.expectedUpdatedAt,
      fields: input.fields.map((field) => ({
        id: field.id,
        corrected_value_json: field.correctedValueJson ?? null,
        corrected_value_text: field.correctedValueText ?? null,
        review_notes: field.reviewNotes ?? null,
        review_status: field.reviewStatus,
      })),
      review_notes: input.reviewNotes ?? null,
    }),
    workspaceId,
  });

  return {
    comparison: response.data.comparison
      ? mapComparison(response.data.comparison)
      : null,
    document: mapDocument(response.data.document),
    extraction: {
      fields: response.data.fields.map(mapExtractedField),
      run: mapExtractionRun(response.data.run),
      sections: response.data.sections?.map((section) => ({
        description: section.description ?? null,
        fieldKeys: section.field_keys,
        id: section.id,
        title: section.title,
      })),
    },
    version: response.data.version ? mapBeoVersion(response.data.version) : null,
  };
}

export async function getDocumentComparison(
  authToken: string,
  workspaceId: string,
  documentId: string
): Promise<BEOComparisonRecord> {
  const response = await apiRequest<{
    data: {
      base_version?: ApiBeoVersion | null;
      changes: ApiVersionChange[];
      document: ApiDocument;
      impacts?: ApiImpact[] | null;
      sections?: ApiComparisonSection[] | null;
      target_version: ApiBeoVersion;
      warnings?: ApiWarning[] | null;
    };
  }>(`/documents/${documentId}/comparison`, {
    authToken,
    workspaceId,
  });

  return mapComparison(response.data);
}

function mapDocument(document: ApiDocument): DocumentRecord {
  return {
    createdAt: document.created_at ?? null,
    downloadUrl: document.download_url ?? null,
    extension: document.extension ?? null,
    id: document.id,
    linkedEvent: coerceEventRecord(document.linked_event),
    links: document.links?.map((link) => ({
      entityId: link.entity_id ?? null,
      entityType: link.entity_type ?? null,
      id: link.id ?? null,
      isPrimary: link.is_primary ?? null,
      relationshipType: link.relationship_type ?? null,
    })) ?? [],
    latestBeoVersion: document.latest_beo_version
      ? mapBeoVersion(document.latest_beo_version)
      : null,
    latestExtractionRun: document.latest_extraction_run
      ? mapExtractionRun(document.latest_extraction_run)
      : null,
    metadata: document.metadata ?? null,
    mimeType: document.mime_type ?? null,
    name: document.name,
    originalFilename: document.original_filename ?? null,
    processingError: document.processing_error ?? null,
    processingStatus: document.processing_status ?? null,
    scanStatus: document.scan_status ?? null,
    size: document.size ?? null,
    type: document.type ?? null,
    uploadedBy: mapUserReference(document.uploaded_by),
    updatedAt: document.updated_at ?? null,
  };
}

export function coerceDocumentRecord(value: unknown): DocumentRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapDocument(value as ApiDocument);
}

function mapBeo(beo: ApiBeo): BeoRecord {
  return {
    approvedAt: beo.approved_at ?? null,
    approvedBy: mapUserReference(beo.approved_by),
    createdAt: beo.created_at ?? null,
    createdBy: mapUserReference(beo.created_by),
    currentVersion: beo.current_version ?? null,
    event: coerceEventRecord(beo.event),
    eventId: beo.event_id ?? null,
    id: beo.id,
    status: beo.status ?? null,
    updatedAt: beo.updated_at ?? null,
  };
}

export function coerceBeoRecord(value: unknown): BeoRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapBeo(value as ApiBeo);
}

function mapBeoVersion(version: ApiBeoVersion): BeoVersionRecord {
  return {
    approvedAt: version.approved_at ?? null,
    approvedBy: mapUserReference(version.approved_by),
    beoId: version.beo_id ?? null,
    changeSummary: version.change_summary ?? null,
    createdAt: version.created_at ?? null,
    createdBy: mapUserReference(version.created_by),
    document: version.document ? mapDocument(version.document) : null,
    documentId: version.document_id ?? null,
    id: version.id,
    reviewNotes: version.review_notes ?? null,
    snapshotJson: version.snapshot_json ?? null,
    source: version.source ?? null,
    status: version.status ?? null,
    version: version.version ?? null,
  };
}

export function coerceBeoVersionRecord(value: unknown): BeoVersionRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapBeoVersion(value as ApiBeoVersion);
}

function mapExtractionRun(run: ApiExtractionRun): ExtractionRunRecord {
  return {
    attempt: run.attempt ?? null,
    beoVersionId: run.beo_version_id ?? null,
    completedAt: run.completed_at ?? null,
    correlationId: run.correlation_id ?? null,
    createdAt: run.created_at ?? null,
    documentId: run.document_id ?? null,
    errorCode: run.error_code ?? null,
    errorMessage: run.error_message ?? null,
    id: run.id,
    latencyMs: run.latency_ms ?? null,
    metadataJson: run.metadata_json ?? null,
    modelKey: run.model_key ?? null,
    promptVersion: run.prompt_version ?? null,
    provider: run.provider ?? null,
    requestedBy: mapUserReference(run.requested_by),
    schemaVersion: run.schema_version ?? null,
    startedAt: run.started_at ?? null,
    status: run.status ?? null,
    updatedAt: run.updated_at ?? null,
    usageJson: run.usage_json ?? null,
  };
}

function mapExtractedField(field: ApiExtractedField): ExtractedFieldRecord {
  return {
    confidence: field.confidence ?? null,
    correctedValueJson: field.corrected_value_json ?? null,
    correctedValueText: field.corrected_value_text ?? null,
    createdAt: field.created_at ?? null,
    extractionRunId: field.extraction_run_id ?? null,
    fieldKey: field.field_key,
    id: field.id,
    pageNumber: field.page_number ?? null,
    rawValue: field.raw_value ?? null,
    reviewNotes: field.review_notes ?? null,
    reviewed: field.reviewed ?? null,
    reviewedAt: field.reviewed_at ?? null,
    reviewedBy: mapUserReference(field.reviewed_by),
    reviewStatus: field.review_status ?? null,
    sourceLocation: field.source_location ?? null,
    updatedAt: field.updated_at ?? null,
    valueJson: field.value_json ?? null,
    valueText: field.value_text ?? null,
    valueType: field.value_type ?? null,
  };
}

function mapComparison(data: {
  base_version?: ApiBeoVersion | null;
  changes: ApiVersionChange[];
  document?: ApiDocument | null;
  impacts?: ApiImpact[] | null;
  sections?: ApiComparisonSection[] | null;
  target_version: ApiBeoVersion;
  warnings?: ApiWarning[] | null;
}): BEOComparisonRecord {
  return {
    baseVersion: data.base_version ? mapBeoVersion(data.base_version) : null,
    changes: data.changes.map(mapVersionChange),
    document: data.document ? mapDocument(data.document) : null,
    impacts: data.impacts?.map(mapImpact) ?? [],
    sections: data.sections?.map((section) => ({
      changeIds: section.change_ids,
      description: section.description ?? null,
      id: section.id,
      title: section.title,
    })) as BEOVersionComparisonSection[] | undefined,
    targetVersion: mapBeoVersion(data.target_version),
    warnings: data.warnings?.map(mapWarning) ?? [],
  };
}

function mapVersionChange(change: ApiVersionChange): BEOFieldChangeRecord {
  return {
    changeType: change.change_type ?? null,
    confidence: change.confidence ?? null,
    fieldKey: change.field_key,
    id: change.id,
    impact: change.impact ?? null,
    label: change.label ?? null,
    nextValue: change.next_value ?? null,
    previousValue: change.previous_value ?? null,
    sectionId: change.section_id ?? null,
    sectionTitle: change.section_title ?? null,
    translationKey: change.translation_key ?? null,
    valueType: change.value_type ?? null,
  };
}

function mapImpact(impact: ApiImpact): BEOImpactRecord {
  return {
    entityId: impact.entity_id ?? null,
    entityType: impact.entity_type,
    id: impact.id,
    impactType: impact.impact_type ?? null,
    requiresRegeneration: impact.requires_regeneration ?? null,
    requiresReview: impact.requires_review ?? null,
    severity: (impact.severity as BEOImpactRecord["severity"]) ?? null,
    summary: impact.summary ?? null,
    title: impact.title ?? null,
    translationKey: impact.translation_key ?? null,
  };
}

function mapWarning(warning: ApiWarning): BEOChangeWarningRecord {
  return {
    description: warning.description ?? null,
    id: warning.id,
    severity: (warning.severity as BEOChangeWarningRecord["severity"]) ?? null,
    title: warning.title ?? null,
  };
}

function mapUserReference(user?: ApiUserReference | null): InventoryUserReference | null {
  if (!user) {
    return null;
  }

  return {
    id: user.id ?? null,
    name: user.name ?? null,
  };
}
