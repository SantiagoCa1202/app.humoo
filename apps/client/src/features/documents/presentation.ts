import type { TFunction } from "i18next";

import type {
  BeoStructuredSectionViewModel,
  BeoSummaryViewModel,
  BeoVersionRecord,
  DocumentProcessingStatus,
  DocumentRecord,
} from "@/features/documents/types";

const DOCUMENT_PROCESSING_STATUSES = ["uploaded", "processing", "ready", "failed"] as const;

export function getDocumentTitle(document?: DocumentRecord | null) {
  return document?.originalFilename?.trim() || document?.name?.trim() || null;
}

export function getDocumentSource(document?: DocumentRecord | null) {
  const source = document?.metadata?.source;
  return typeof source === "string" && source.trim() ? source.trim() : null;
}

export function getDocumentProcessingStatus(
  status?: DocumentRecord["processingStatus"]
): DocumentProcessingStatus | null {
  return typeof status === "string" &&
    (DOCUMENT_PROCESSING_STATUSES as readonly string[]).includes(status)
    ? (status as DocumentProcessingStatus)
    : null;
}

export function getDocumentEventLink(document?: DocumentRecord | null) {
  return document?.links?.find((link) => link.entityType === "event") ?? null;
}

export function formatDocumentDate(value?: string | null, locale?: string) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  try {
    return new Intl.DateTimeFormat(locale, { dateStyle: "medium" }).format(date);
  } catch {
    return value;
  }
}

export function formatDocumentDateTime(
  value?: string | null,
  locale?: string,
  timezone?: string | null
) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: timezone || undefined,
    }).format(date);
  } catch {
    return value;
  }
}

export function getDocumentTypeLabel(type: string | null | undefined, t: TFunction<"common">) {
  if (!type?.trim()) return null;
  const normalized = type.trim().toLowerCase();
  const known = ["beo", "menu", "recipe", "contract", "invoice", "photo", "export", "attachment", "other"];
  return known.includes(normalized) ? t(`documents.types.${normalized}`) : type.trim();
}

export function getDocumentSourceLabel(source: string | null | undefined, t: TFunction<"common">) {
  if (!source?.trim()) return null;
  const normalized = source.trim().toLowerCase();
  const known = ["upload", "manual", "ai", "import"];
  return known.includes(normalized) ? t(`documents.sources.${normalized}`) : source.trim();
}

export function getFileTypeLabel(document: DocumentRecord, t: TFunction<"common">) {
  const extension = document.extension?.trim().toUpperCase();
  if (extension) return extension;
  if (document.mimeType === "application/pdf") return t("documents.fileTypes.pdf");
  if (document.mimeType?.startsWith("image/")) return t("documents.fileTypes.image");
  return null;
}

export function getBeoVersionLabel(version: BeoVersionRecord, t: TFunction<"common">) {
  return typeof version.version === "number"
    ? t("documents.beoVersion.versionLabel", { version: version.version })
    : t("documents.beoVersion.unnumbered");
}

function humanizeSnapshotKey(key: string) {
  return key.replace(/[._-]+/g, " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

function renderSnapshotValue(value: unknown): React.ReactNode {
  if (value === null || value === undefined || value === "") return null;
  if (typeof value === "string" || typeof value === "number") return String(value);
  if (typeof value === "boolean") return value ? "✓" : "—";
  if (Array.isArray(value)) {
    const simple = value.every((item) => ["string", "number"].includes(typeof item));
    return simple ? value.join(", ") : JSON.stringify(value);
  }
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

export function buildBeoStructuredSections(
  snapshot?: Record<string, unknown> | null
): BeoStructuredSectionViewModel[] {
  if (!snapshot) return [];
  return Object.entries(snapshot).map(([sectionKey, sectionValue]) => {
    const entries =
      sectionValue && typeof sectionValue === "object" && !Array.isArray(sectionValue)
        ? Object.entries(sectionValue as Record<string, unknown>)
        : [[sectionKey, sectionValue] as [string, unknown]];
    return {
      id: sectionKey,
      title: humanizeSnapshotKey(sectionKey),
      fields: entries
        .map(([fieldKey, value]) => ({
          id: `${sectionKey}.${fieldKey}`,
          label: humanizeSnapshotKey(fieldKey),
          value: renderSnapshotValue(value),
        }))
        .filter((field) => field.value !== null),
    };
  }).filter((section) => section.fields.length > 0);
}

export function getBeoSummaryMetrics(summary?: BeoSummaryViewModel | null) {
  if (!summary) return [];
  return [
    ["event", summary.eventName],
    ["client", summary.clientName],
    ["venue", summary.venueName],
    ["guests", summary.guestCount],
    ["service", summary.serviceType],
    ["menu", summary.menuName],
  ] as const;
}
