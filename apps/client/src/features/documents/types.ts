import type { EventDisplayRecord } from "@/features/events";
import type { InventoryUserReference } from "@/features/inventory";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { SelectOption } from "@/components/primitives/select-base";

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
  extension?: string | null;
  id: string;
  links?: DocumentLinkRecord[] | null;
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
  errorCode?: string | null;
  errorMessage?: string | null;
  id: string;
  latencyMs?: number | null;
  metadataJson?: Record<string, unknown> | null;
  modelKey?: string | null;
  promptVersion?: string | null;
  provider?: string | null;
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
