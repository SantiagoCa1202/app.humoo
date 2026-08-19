import type { TFunction } from "i18next";

import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventClientName,
  getEventVenueName,
  type EventDisplayRecord,
} from "@/features/events";
import { formatDocumentDate, formatDocumentDateTime } from "@/features/documents/presentation";
import type {
  BEOChangeSeverity,
  BEOConflictType,
  BEOFieldChangeRecord,
  BEOFieldChangeType,
  BEOImpactRecord,
  BEOVersionComparisonSection,
  BEOExtractionRecord,
  ExtractedFieldDescriptor,
  ExtractedFieldRecord,
  ExtractedFieldReviewStatus,
  ExtractedFieldValueType,
  ExtractionConfidenceState,
  ExtractionReviewSummary,
  ExtractionRunRecord,
  ExtractionRunStatus,
  ExtractionSectionViewModel,
} from "@/features/documents/types";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { AlertTone, SemanticStatusTone } from "@/theme/status-config";

export const EXTRACTION_RUN_STATUS_VALUES = [
  "pending",
  "processing",
  "review_required",
  "completed",
  "failed",
  "cancelled",
] as const satisfies readonly ExtractionRunStatus[];

export const EXTRACTED_FIELD_REVIEW_STATUS_VALUES = [
  "pending",
  "accepted",
  "corrected",
  "rejected",
] as const satisfies readonly ExtractedFieldReviewStatus[];

export const EXTRACTED_FIELD_VALUE_TYPE_VALUES = [
  "string",
  "integer",
  "decimal",
  "boolean",
  "date",
  "datetime",
  "object",
  "array",
] as const satisfies readonly ExtractedFieldValueType[];

export const DEFAULT_EXTRACTION_CONFIDENCE_THRESHOLDS = {
  low: 0.5,
  medium: 0.8,
} as const;

export const BEO_FIELD_CHANGE_TYPE_VALUES = [
  "added",
  "removed",
  "changed",
  "unchanged",
] as const satisfies readonly BEOFieldChangeType[];

export const BEO_CONFLICT_TYPE_VALUES = [
  "version_conflict",
  "remote_update",
  "newer_version",
  "stale_review",
  "optimistic_lock",
  "http_409",
] as const satisfies readonly BEOConflictType[];

function humanizeKeySegment(value: string) {
  return value
    .replace(/[._-]+/g, " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function getExtractionRunStatus(
  status?: ExtractionRunRecord["status"]
): ExtractionRunStatus | null {
  return typeof status === "string" &&
    (EXTRACTION_RUN_STATUS_VALUES as readonly string[]).includes(status)
    ? (status as ExtractionRunStatus)
    : null;
}

export function getExtractedFieldReviewStatus(
  status?: ExtractedFieldRecord["reviewStatus"]
): ExtractedFieldReviewStatus | null {
  return typeof status === "string" &&
    (EXTRACTED_FIELD_REVIEW_STATUS_VALUES as readonly string[]).includes(status)
    ? (status as ExtractedFieldReviewStatus)
    : null;
}

export function getExtractedFieldValueType(
  valueType?: ExtractedFieldRecord["valueType"]
): ExtractedFieldValueType | null {
  return typeof valueType === "string" &&
    (EXTRACTED_FIELD_VALUE_TYPE_VALUES as readonly string[]).includes(valueType)
    ? (valueType as ExtractedFieldValueType)
    : null;
}

export function getExtractedFieldNormalizedValue(field: ExtractedFieldRecord) {
  return field.valueJson ?? field.valueText ?? null;
}

export function getExtractedFieldCorrectedValue(field: ExtractedFieldRecord) {
  return field.correctedValueJson ?? field.correctedValueText ?? null;
}

export function hasExtractedFieldCorrection(field: ExtractedFieldRecord) {
  return (
    field.correctedValueJson !== null &&
    field.correctedValueJson !== undefined
  ) || Boolean(field.correctedValueText?.trim());
}

export function getExtractedFieldEffectiveValue(field: ExtractedFieldRecord) {
  return hasExtractedFieldCorrection(field)
    ? getExtractedFieldCorrectedValue(field)
    : getExtractedFieldNormalizedValue(field);
}

export function getExtractedFieldLabel(
  field: Pick<ExtractedFieldRecord, "fieldKey">,
  descriptor?: ExtractedFieldDescriptor | null,
  t?: TFunction<"common">
) {
  if (descriptor?.label?.trim()) {
    return descriptor.label.trim();
  }

  if (t) {
    const translationKey = `documents.extraction.fields.${field.fieldKey}.label`;
    const translated = t(translationKey);

    if (translated !== translationKey) {
      return translated;
    }
  }

  const [, trailing] = splitExtractedFieldSectionKey(field.fieldKey);
  return humanizeKeySegment(trailing);
}

export function splitExtractedFieldSectionKey(fieldKey: string) {
  const [sectionKey, ...rest] = fieldKey.split(".");
  return [sectionKey || "general", rest.length ? rest.join(".") : sectionKey] as const;
}

export function getExtractionConfidenceState(
  confidence?: number | null,
  thresholds = DEFAULT_EXTRACTION_CONFIDENCE_THRESHOLDS
): ExtractionConfidenceState {
  if (confidence === null || confidence === undefined || !Number.isFinite(confidence)) {
    return "unknown";
  }

  if (confidence < thresholds.low) {
    return "low";
  }

  if (confidence < thresholds.medium) {
    return "medium";
  }

  return "high";
}

export function formatExtractionConfidence(
  confidence?: number | null,
  locale?: string
) {
  if (confidence === null || confidence === undefined || !Number.isFinite(confidence)) {
    return null;
  }

  return new Intl.NumberFormat(locale, {
    maximumFractionDigits: 0,
    style: "percent",
  }).format(confidence);
}

export function getExtractionConfidenceTranslationKey(
  state: ExtractionConfidenceState
) {
  return `documents.extraction.confidence.${state}`;
}

export function formatExtractedFieldValue(
  value: unknown,
  valueType?: ExtractedFieldValueType | null,
  locale?: string
) {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  if (typeof value === "string") {
    return value;
  }

  if (typeof value === "number") {
    if (valueType === "integer") {
      return new Intl.NumberFormat(locale, {
        maximumFractionDigits: 0,
      }).format(value);
    }

    if (valueType === "decimal") {
      return new Intl.NumberFormat(locale, {
        maximumFractionDigits: 2,
      }).format(value);
    }

    return String(value);
  }

  if (typeof value === "boolean") {
    return value ? "true" : "false";
  }

  if (Array.isArray(value)) {
    return value.every((item) => typeof item === "string" || typeof item === "number")
      ? value.join(", ")
      : JSON.stringify(value, null, 2);
  }

  if (typeof value === "object") {
    return JSON.stringify(value, null, 2);
  }

  return String(value);
}

export function getBeoFieldChangeType(
  value?: BEOFieldChangeRecord["changeType"]
): BEOFieldChangeType | null {
  return typeof value === "string" &&
    (BEO_FIELD_CHANGE_TYPE_VALUES as readonly string[]).includes(value)
    ? (value as BEOFieldChangeType)
    : null;
}

export function getBeoConflictType(value?: BEOConflictType | (string & {}) | null) {
  return typeof value === "string" &&
    (BEO_CONFLICT_TYPE_VALUES as readonly string[]).includes(value)
    ? (value as BEOConflictType)
    : null;
}

export function getBeoChangeTranslationKey(change: Pick<BEOFieldChangeRecord, "fieldKey" | "translationKey">) {
  return (
    change.translationKey?.trim() || `documents.changes.fields.${change.fieldKey}.label`
  );
}

export function getBeoChangeLabel(
  change: Pick<BEOFieldChangeRecord, "fieldKey" | "label" | "translationKey">,
  t?: TFunction<"common">
) {
  if (change.label?.trim()) {
    return change.label.trim();
  }

  if (t) {
    const translationKey = getBeoChangeTranslationKey(change);
    const translated = t(translationKey);

    if (translated !== translationKey) {
      return translated;
    }
  }

  const [, trailing] = splitExtractedFieldSectionKey(change.fieldKey);
  return humanizeKeySegment(trailing);
}

export function getBeoChangeSectionId(change: Pick<BEOFieldChangeRecord, "fieldKey" | "sectionId">) {
  if (change.sectionId?.trim()) {
    return change.sectionId.trim();
  }

  const [sectionId] = splitExtractedFieldSectionKey(change.fieldKey);
  return sectionId;
}

export function getBeoChangeSectionTitle(
  change: Pick<BEOFieldChangeRecord, "fieldKey" | "sectionId" | "sectionTitle">,
  t?: TFunction<"common">
) {
  if (change.sectionTitle?.trim()) {
    return change.sectionTitle.trim();
  }

  const sectionId = getBeoChangeSectionId(change);
  const translationKey = `documents.changes.sections.${sectionId}`;
  const translated = t ? t(translationKey) : translationKey;

  return t && translated !== translationKey ? translated : humanizeKeySegment(sectionId);
}

export function formatBeoChangeValue(
  value: unknown,
  valueType?: ExtractedFieldValueType | null,
  locale?: string,
  timeZone?: string | null
) {
  if (valueType === "date" && typeof value === "string") {
    return formatDocumentDate(value, locale) ?? value;
  }

  if (valueType === "datetime" && typeof value === "string") {
    return formatDocumentDateTime(value, locale, timeZone) ?? value;
  }

  return formatExtractedFieldValue(value, valueType, locale);
}

export function buildBeoVersionComparisonSections(
  changes: BEOFieldChangeRecord[],
  t?: TFunction<"common">
): BEOVersionComparisonSection[] {
  const grouped = new Map<string, BEOVersionComparisonSection>();

  changes.forEach((change) => {
    const sectionId = getBeoChangeSectionId(change);
    const current = grouped.get(sectionId);

    if (current) {
      current.changeIds.push(change.id);
      return;
    }

    grouped.set(sectionId, {
      changeIds: [change.id],
      id: sectionId,
      title: getBeoChangeSectionTitle(change, t),
    });
  });

  return [...grouped.values()];
}

export function buildBeoChangeSummary(changes: BEOFieldChangeRecord[]) {
  return changes.reduce(
    (summary, change) => {
      const changeType = getBeoFieldChangeType(change.changeType);

      summary.total += 1;

      if (changeType === "added") {
        summary.added += 1;
      } else if (changeType === "removed") {
        summary.removed += 1;
      } else if (changeType === "changed") {
        summary.changed += 1;
      } else if (changeType === "unchanged") {
        summary.unchanged += 1;
      }

      return summary;
    },
    {
      added: 0,
      changed: 0,
      removed: 0,
      total: 0,
      unchanged: 0,
    }
  );
}

export function getBeoImpactTone(severity?: SemanticStatusTone | null): AlertTone {
  if (severity === "danger") {
    return "error";
  }

  if (severity === "success") {
    return "success";
  }

  if (severity === "warning") {
    return "warning";
  }

  return "info";
}

export function getBeoChangeAlertTone(severity?: BEOChangeSeverity | null): AlertTone {
  if (severity === "danger") {
    return "error";
  }

  if (severity === "warning") {
    return "warning";
  }

  return "info";
}

export function getBeoImpactEntityTranslationKey(entityType: BEOImpactRecord["entityType"]) {
  if (entityType === "event") return "documents.impact.entities.event";
  if (entityType === "menu") return "documents.impact.entities.menu";
  if (entityType === "prep") return "documents.impact.entities.prep";
  if (entityType === "tasks") return "documents.impact.entities.tasks";
  if (entityType === "staffing") return "documents.impact.entities.staffing";
  return null;
}

export function getBeoImpactTitle(impact: BEOImpactRecord, t?: TFunction<"common">) {
  if (impact.title?.trim()) {
    return impact.title.trim();
  }

  if (impact.translationKey?.trim() && t) {
    const translated = t(impact.translationKey);
    if (translated !== impact.translationKey) {
      return translated;
    }
  }

  const translationKey = getBeoImpactEntityTranslationKey(impact.entityType);
  if (translationKey && t) {
    return t(translationKey);
  }

  return humanizeKeySegment(String(impact.entityType));
}

export function getBeoConflictDescriptionKey(conflictType?: BEOConflictType | null) {
  if (conflictType === "remote_update") return "documents.conflict.types.remote_update";
  if (conflictType === "newer_version") return "documents.conflict.types.newer_version";
  if (conflictType === "stale_review") return "documents.conflict.types.stale_review";
  if (conflictType === "optimistic_lock") return "documents.conflict.types.optimistic_lock";
  if (conflictType === "http_409") return "documents.conflict.types.http_409";
  return "documents.conflict.types.version_conflict";
}

export function applyExtractedFieldCorrection(
  field: ExtractedFieldRecord,
  nextValue: unknown
) {
  const valueType = getExtractedFieldValueType(field.valueType);
  const base = {
    ...field,
    reviewed: true,
    reviewStatus: "corrected" as const,
  };

  if (nextValue === null || nextValue === undefined || nextValue === "") {
    return {
      ...base,
      correctedValueJson: null,
      correctedValueText: null,
    } satisfies ExtractedFieldRecord;
  }

  if (valueType === "object" || valueType === "array") {
    return {
      ...base,
      correctedValueJson: nextValue,
      correctedValueText: null,
    } satisfies ExtractedFieldRecord;
  }

  if (typeof nextValue === "boolean" || typeof nextValue === "number") {
    return {
      ...base,
      correctedValueJson: nextValue,
      correctedValueText: String(nextValue),
    } satisfies ExtractedFieldRecord;
  }

  return {
    ...base,
    correctedValueJson: null,
    correctedValueText: String(nextValue),
  } satisfies ExtractedFieldRecord;
}

export function approveExtractedField(field: ExtractedFieldRecord) {
  return {
    ...field,
    reviewed: true,
    reviewStatus: hasExtractedFieldCorrection(field) ? "corrected" : "accepted",
  } satisfies ExtractedFieldRecord;
}

export function buildExtractionSections(
  extraction: BEOExtractionRecord,
  t?: TFunction<"common">
): ExtractionSectionViewModel[] {
  if (extraction.sections?.length) {
    return extraction.sections;
  }

  const grouped = new Map<string, string[]>();

  extraction.fields.forEach((field) => {
    const [sectionKey] = splitExtractedFieldSectionKey(field.fieldKey);
    grouped.set(sectionKey, [...(grouped.get(sectionKey) ?? []), field.fieldKey]);
  });

  return [...grouped.entries()].map(([sectionKey, fieldKeys]) => {
    const translationKey = `documents.extraction.sections.${sectionKey}`;
    const translatedTitle = t ? t(translationKey) : translationKey;

    return {
      fieldKeys,
      id: sectionKey,
      title:
        t && translatedTitle !== translationKey
          ? translatedTitle
          : humanizeKeySegment(sectionKey),
    } satisfies ExtractionSectionViewModel;
  });
}

export function buildExtractionReviewSummary(
  fields: ExtractedFieldRecord[]
): ExtractionReviewSummary {
  return fields.reduce<ExtractionReviewSummary>(
    (summary, field) => {
      const reviewStatus = getExtractedFieldReviewStatus(field.reviewStatus);
      const confidenceState = getExtractionConfidenceState(field.confidence);

      summary.fieldCount += 1;

      if (field.reviewed || reviewStatus === "accepted" || reviewStatus === "corrected") {
        summary.reviewedCount += 1;
      }

      if (reviewStatus === "pending" || reviewStatus === "rejected" || !field.reviewed) {
        summary.needsReviewCount += 1;
      }

      if (confidenceState === "low") {
        summary.lowConfidenceCount += 1;
      }

      return summary;
    },
    {
      fieldCount: 0,
      lowConfidenceCount: 0,
      needsReviewCount: 0,
      reviewedCount: 0,
    }
  );
}

export function createEventEntityOptions(
  events: EventDisplayRecord[],
  locale?: string
): EntityPickerOption<string>[] {
  return events
    .filter((event) => Boolean(event.id))
    .map((event) => {
      const metadata = [
        formatEventDateRange(event, locale),
        getEventClientName(event.client),
        getEventVenueName(event.venue),
        formatEventGuestCount(event.guestCountExpected, locale),
      ]
        .filter(Boolean)
        .join(" - ");

      return {
        label: event.name?.trim() || event.id,
        metadata: metadata || undefined,
        value: event.id,
      };
    });
}
