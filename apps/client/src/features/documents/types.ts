import type { EventDisplayRecord } from "@/features/events";
import type { InventoryUserReference } from "@/features/inventory";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { SelectOption } from "@/components/primitives/select-base";
import type { SemanticStatusTone } from "@/theme/status-config";

export type DocumentProcessingStatus = "uploaded" | "processing" | "ready" | "failed";
export type DocumentScanStatus = "pending" | "scanning" | "clean" | "infected" | "failed";
export type BeoStatus = "draft" | "active" | "superseded" | "archived";
export type BeoVersionStatus =
  | "processing"
  | "review_required"
  | "approved"
  | "superseded"
  | "rejected";
export type ExtractionRunStatus =
  | "pending"
  | "processing"
  | "review_required"
  | "completed"
  | "failed"
  | "cancelled";
export type ExtractionResultStatus = "completed" | "partial" | "failed";
export type ExtractedFieldReviewStatus =
  | "pending"
  | "accepted"
  | "corrected"
  | "rejected";
export type ExtractedFieldValueType =
  | "string"
  | "integer"
  | "decimal"
  | "boolean"
  | "date"
  | "datetime"
  | "object"
  | "array";
export type ExtractedFieldInputKind =
  | "text"
  | "textarea"
  | "number"
  | "select"
  | "date"
  | "datetime"
  | "entity"
  | "boolean";
export type ExtractionConfidenceState = "unknown" | "low" | "medium" | "high";

export type DocumentMetadata = Record<string, unknown> & {
  source?: string | null;
  page_count?: number | null;
};

export type DocumentLinkRecord = {
  entityId?: string | null;
  entityType?: string | null;
  id?: string | null;
  isPrimary?: boolean | null;
  relationshipType?: string | null;
};

export type DocumentRecord = {
  createdAt?: string | null;
  downloadUrl?: string | null;
  extension?: string | null;
  id: string;
  linkedEvent?: EventDisplayRecord | null;
  links?: DocumentLinkRecord[] | null;
  latestBeoVersion?: BeoVersionRecord | null;
  latestExtractionRun?: ExtractionRunRecord | null;
  metadata?: DocumentMetadata | null;
  mimeType?: string | null;
  name: string;
  originalFilename?: string | null;
  processingError?: string | null;
  processingStatus?: DocumentProcessingStatus | (string & {}) | null;
  scanStatus?: DocumentScanStatus | (string & {}) | null;
  size?: number | null;
  type?: string | null;
  uploadedBy?: InventoryUserReference | null;
  updatedAt?: string | null;
};

export type DocumentDetailRecord = {
  beo?: BeoRecord | null;
  document: DocumentRecord;
};

export type BeoImportBatchRecord = {
  createdAt?: string | null;
  eventOrdersCount?: number | null;
  id: string;
  originalFilename: string;
  propertyId?: string | null;
  sourceSystem?: string | null;
  status?: string | null;
};

export type EventFunctionRecord = {
  expectedCount?: number | null;
  guaranteedCount?: number | null;
  hiddenByPreferences?: boolean;
  id: string;
  menuStatus?: string | null;
  name: string;
  operationalCategory?: string | null;
  setCount?: number | null;
};

export type EventFunctionsPage = {
  data: EventFunctionRecord[];
  hiddenCount: number;
};

export type BeoRecord = {
  approvedAt?: string | null;
  approvedBy?: InventoryUserReference | null;
  createdAt?: string | null;
  createdBy?: InventoryUserReference | null;
  currentVersion?: number | null;
  event?: EventDisplayRecord | null;
  eventId?: string | null;
  id: string;
  status?: BeoStatus | (string & {}) | null;
  updatedAt?: string | null;
};

export type BeoSnapshot = Record<string, unknown>;

export type BeoVersionRecord = {
  approvedAt?: string | null;
  approvedBy?: InventoryUserReference | null;
  beoId?: string | null;
  changeSummary?: string | null;
  createdAt?: string | null;
  createdBy?: InventoryUserReference | null;
  document?: DocumentRecord | null;
  documentId?: string | null;
  id: string;
  reviewNotes?: string | null;
  snapshotJson?: BeoSnapshot | null;
  source?: string | null;
  status?: BeoVersionStatus | (string & {}) | null;
  version?: number | null;
};

export type ExtractionRunRecord = {
  attempt?: number | null;
  beoVersionId?: string | null;
  completedAt?: string | null;
  correlationId?: string | null;
  createdAt?: string | null;
  documentId?: string | null;
  importBatchId?: string | null;
  errorCode?: string | null;
  errorMessage?: string | null;
  id: string;
  latencyMs?: number | null;
  metadataJson?: Record<string, unknown> | null;
  modelKey?: string | null;
  promptVersion?: string | null;
  provider?: string | null;
  resultStatus?: ExtractionResultStatus | (string & {}) | null;
  requestedBy?: InventoryUserReference | null;
  schemaVersion?: string | null;
  startedAt?: string | null;
  status?: ExtractionRunStatus | (string & {}) | null;
  updatedAt?: string | null;
  usageJson?: Record<string, unknown> | null;
};

export type ExtractedFieldRecord = {
  confidence?: number | null;
  correctedValueJson?: unknown | null;
  correctedValueText?: string | null;
  createdAt?: string | null;
  extractionRunId?: string | null;
  fieldKey: string;
  id: string;
  pageNumber?: number | null;
  rawValue?: string | null;
  reviewNotes?: string | null;
  reviewed?: boolean | null;
  reviewedAt?: string | null;
  reviewedBy?: InventoryUserReference | null;
  reviewStatus?: ExtractedFieldReviewStatus | (string & {}) | null;
  sourceLocation?: Record<string, unknown> | null;
  updatedAt?: string | null;
  valueJson?: unknown | null;
  valueText?: string | null;
  valueType?: ExtractedFieldValueType | (string & {}) | null;
};

export type BeoStructuredFieldViewModel = {
  id: string;
  label: string;
  value: React.ReactNode;
};

export type BeoStructuredSectionViewModel = {
  fields: BeoStructuredFieldViewModel[];
  id: string;
  title: string;
};

export type BeoSummaryViewModel = {
  clientName?: string | null;
  eventName?: string | null;
  guestCount?: number | null;
  menuName?: string | null;
  notes?: string | null;
  serviceType?: string | null;
  startsAt?: string | null;
  timezone?: string | null;
  venueName?: string | null;
};

export type ExtractedFieldDescriptor = {
  accessibilityLabel?: string;
  entities?: EntityPickerOption<string>[];
  fieldKey: string;
  helperText?: string;
  input?: ExtractedFieldInputKind;
  label?: string;
  optional?: boolean;
  options?: SelectOption<string>[];
  placeholder?: string;
  timeZone?: string | null;
};

export type ExtractionSectionViewModel = {
  description?: string | null;
  fieldKeys: string[];
  id: string;
  title: string;
};

export type ExtractionReviewSummary = {
  fieldCount: number;
  lowConfidenceCount: number;
  needsReviewCount: number;
  reviewedCount: number;
};

export type BEOExtractionRecord = {
  fields: ExtractedFieldRecord[];
  run?: ExtractionRunRecord | null;
  sections?: ExtractionSectionViewModel[] | null;
};

export type BEOComparisonRecord = {
  baseVersion?: BeoVersionRecord | null;
  changes: BEOFieldChangeRecord[];
  document?: DocumentRecord | null;
  impacts?: BEOImpactRecord[] | null;
  sections?: BEOVersionComparisonSection[] | null;
  targetVersion: BeoVersionRecord;
  warnings?: BEOChangeWarningRecord[] | null;
};

export type DocumentUploadInput = {
  eventId?: string | null;
  file: {
    mimeType?: string | null;
    name: string;
    size?: number | null;
    type?: string | null;
    uri: string;
  };
  source?: "upload" | "manual" | "ai" | "import";
  type?: string | null;
};

export type DocumentListFilters = {
  cursor?: string | null;
  eventId?: string | null;
  perPage?: number;
  processingStatus?: string | null;
  search?: string;
  type?: string | null;
};

export type DocumentsCursorPage = {
  data: DocumentRecord[];
  nextCursor: string | null;
  nextPageUrl: string | null;
  path: string;
  perPage: number;
  prevCursor: string | null;
  prevPageUrl: string | null;
};

export type BEOFieldChangeType = "added" | "removed" | "changed" | "unchanged";

export type BEOChangeSeverity = "info" | "warning" | "danger";

export type BEOImpactEntityType =
  | "event"
  | "menu"
  | "prep"
  | "tasks"
  | "staffing"
  | (string & {});

export type BEOConflictType =
  | "version_conflict"
  | "remote_update"
  | "newer_version"
  | "stale_review"
  | "optimistic_lock"
  | "http_409";

export type BEOFieldChangeRecord = {
  changeType?: BEOFieldChangeType | (string & {}) | null;
  confidence?: number | null;
  fieldKey: string;
  id: string;
  impact?: string | null;
  label?: string | null;
  nextValue?: unknown | null;
  previousValue?: unknown | null;
  sectionId?: string | null;
  sectionTitle?: string | null;
  translationKey?: string | null;
  valueType?: ExtractedFieldValueType | (string & {}) | null;
};

export type BEOVersionComparisonSection = {
  changeIds: string[];
  description?: string | null;
  id: string;
  title: string;
};

export type BEOImpactRecord = {
  entityId?: string | null;
  entityType: BEOImpactEntityType;
  id: string;
  impactType?: string | null;
  requiresRegeneration?: boolean | null;
  requiresReview?: boolean | null;
  severity?: SemanticStatusTone | null;
  summary?: string | null;
  title?: string | null;
  translationKey?: string | null;
};

export type BEOChangeWarningRecord = {
  description?: string | null;
  id: string;
  severity?: SemanticStatusTone | null;
  title?: string | null;
  translationKey?: string | null;
};

export type BEOUploadValidationErrors = Partial<
  Record<"file" | "form", string>
>;

export type ExtractedFieldValidationErrors = Partial<
  Record<"value" | "reviewNotes" | "form", string>
>;

export type BEOExtractionReviewValidationErrors = Partial<
  Record<"form", string>
> & {
  fields?: Record<string, ExtractedFieldValidationErrors>;
};
