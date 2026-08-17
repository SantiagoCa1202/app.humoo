import type { EventDisplayRecord } from "@/features/events";
import type { InventoryUserReference } from "@/features/inventory";

export type DocumentProcessingStatus = "uploaded" | "processing" | "ready" | "failed";
export type DocumentScanStatus = "pending" | "scanning" | "clean" | "infected" | "failed";
export type BeoStatus = "draft" | "active" | "superseded" | "archived";
export type BeoVersionStatus =
  | "processing"
  | "review_required"
  | "approved"
  | "superseded"
  | "rejected";

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
