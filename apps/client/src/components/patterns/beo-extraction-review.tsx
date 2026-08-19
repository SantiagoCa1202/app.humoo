import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { BEOExtractionSection } from "@/components/patterns/beo-extraction-section";
import { BEOViewer, type BEOViewerMode } from "@/components/patterns/beo-viewer";
import { FormCard } from "@/components/patterns/form-card";
import {
  buildExtractionReviewSummary,
  buildExtractionSections,
  type BEOExtractionRecord,
  type BEOExtractionReviewValidationErrors,
  type BeoVersionRecord,
  type DocumentRecord,
  type ExtractedFieldDescriptor,
  type ExtractedFieldRecord,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOExtractionReviewProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  document?: DocumentRecord | null;
  draftCorrections?: Record<string, ExtractedFieldRecord | undefined>;
  extraction: BEOExtractionRecord;
  fieldDescriptors?: Record<string, ExtractedFieldDescriptor | undefined>;
  onApprove: (fields: ExtractedFieldRecord[]) => void | Promise<void>;
  onCancel?: () => void;
  onFieldChange?: (field: ExtractedFieldRecord) => void | Promise<void>;
  onOpenOriginal?: () => void | Promise<void>;
  sourceUri?: string | null;
  submitting?: boolean;
  validationErrors?: BEOExtractionReviewValidationErrors;
  version?: BeoVersionRecord | null;
  viewerMode?: BEOViewerMode;
  onViewerModeChange?: (mode: BEOViewerMode) => void;
};

export function BEOExtractionReview({
  accessibilityLabel,
  disabled = false,
  document,
  draftCorrections,
  extraction,
  fieldDescriptors,
  onApprove,
  onCancel,
  onFieldChange,
  onOpenOriginal,
  onViewerModeChange,
  sourceUri,
  submitting = false,
  validationErrors,
  version,
  viewerMode,
}: BEOExtractionReviewProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedFields = useMemo(
    () =>
      extraction.fields.map((field) => {
        const draft = draftCorrections?.[field.id] ?? draftCorrections?.[field.fieldKey];
        return draft ?? field;
      }),
    [draftCorrections, extraction.fields]
  );
  const sections = useMemo(
    () => buildExtractionSections({ ...extraction, fields: resolvedFields }, t),
    [extraction, resolvedFields, t]
  );
  const summary = useMemo(
    () => buildExtractionReviewSummary(resolvedFields),
    [resolvedFields]
  );

  const handleApprove = async () => {
    await onApprove(resolvedFields);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("documents.extraction.review.accessibilityLabel")}
      cancelLabel={t("documents.actions.cancel")}
      disabled={disabled || submitting}
      error={validationErrors?.form}
      onCancel={onCancel}
      onSubmit={handleApprove}
      submitLabel={t("documents.actions.confirm")}
      submitting={submitting}
      title={t("documents.extraction.review.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <BEOViewer
          accessibilityLabel={t("documents.extraction.review.viewerAccessibilityLabel")}
          document={document}
          mode={viewerMode}
          onModeChange={onViewerModeChange}
          onOpenOriginal={onOpenOriginal}
          sourceUri={sourceUri}
          structuredData={version?.snapshotJson ?? null}
          version={version}
        />
        {summary.lowConfidenceCount > 0 || summary.needsReviewCount > 0 ? (
          <AlertCard
            description={t("documents.extraction.review.warningDescription", {
              lowConfidence: summary.lowConfidenceCount,
              needsReview: summary.needsReviewCount,
            })}
            title={t("documents.extraction.review.warningTitle")}
            tone="warning"
            variant="muted"
          />
        ) : null}
        {sections.map((section) => (
          <BEOExtractionSection
            compact={false}
            descriptors={fieldDescriptors}
            disabled={disabled || submitting}
            editable={!disabled}
            fields={resolvedFields.filter((field) => section.fieldKeys.includes(field.fieldKey))}
            key={section.id}
            onFieldApprove={onFieldChange}
            onFieldChange={onFieldChange}
            reviewSummary={buildExtractionReviewSummary(
              resolvedFields.filter((field) => section.fieldKeys.includes(field.fieldKey))
            )}
            section={section}
            validationErrors={validationErrors}
          />
        ))}
      </View>
    </FormCard>
  );
}
