import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BEOExtractedField } from "@/components/patterns/beo-extracted-field";
import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import {
  buildExtractionReviewSummary,
  type ExtractedFieldDescriptor,
  type ExtractedFieldRecord,
  type BEOExtractionReviewValidationErrors,
  type ExtractionReviewSummary,
  type ExtractionSectionViewModel,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOExtractionSectionProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  descriptors?: Record<string, ExtractedFieldDescriptor | undefined>;
  disabled?: boolean;
  editable?: boolean;
  expanded?: boolean;
  fields: ExtractedFieldRecord[];
  onExpandedChange?: (expanded: boolean) => void;
  onFieldApprove?: (field: ExtractedFieldRecord) => void | Promise<void>;
  onFieldChange?: (field: ExtractedFieldRecord) => void | Promise<void>;
  reviewSummary?: ExtractionReviewSummary;
  section: ExtractionSectionViewModel;
  validationErrors?: BEOExtractionReviewValidationErrors;
};

export function BEOExtractionSection({
  accessibilityLabel,
  compact = false,
  descriptors,
  disabled = false,
  editable = false,
  expanded,
  fields,
  onExpandedChange,
  onFieldApprove,
  onFieldChange,
  reviewSummary,
  section,
  validationErrors,
}: BEOExtractionSectionProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [localExpanded, setLocalExpanded] = useState(true);
  const resolvedExpanded = expanded ?? localExpanded;
  const resolvedSummary = useMemo(
    () => reviewSummary ?? buildExtractionReviewSummary(fields),
    [fields, reviewSummary]
  );

  const toggleExpanded = () => {
    const nextExpanded = !resolvedExpanded;

    if (expanded === undefined) {
      setLocalExpanded(nextExpanded);
    }

    onExpandedChange?.(nextExpanded);
  };

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? section.title}
      padding={compact ? "md" : "lg"}
      variant="outlined"
    >
      <CardHeader
        subtitle={section.description ?? t("documents.extraction.sectionDescription")}
        title={section.title}
        trailing={
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2], justifyContent: "flex-end" }}>
              <Badge
                label={t("documents.extraction.summary.fields", {
                  count: resolvedSummary.fieldCount,
                })}
                size="sm"
                variant="neutral"
              />
              {resolvedSummary.reviewedCount > 0 ? (
                <Badge
                  label={t("documents.extraction.summary.reviewed", {
                    count: resolvedSummary.reviewedCount,
                  })}
                  size="sm"
                  variant="success"
                />
              ) : null}
              {resolvedSummary.needsReviewCount > 0 ? (
                <Badge
                  label={t("documents.extraction.summary.needsReview", {
                    count: resolvedSummary.needsReviewCount,
                  })}
                  size="sm"
                  variant="warning"
                />
              ) : null}
              {resolvedSummary.lowConfidenceCount > 0 ? (
                <Badge
                  label={t("documents.extraction.summary.lowConfidence", {
                    count: resolvedSummary.lowConfidenceCount,
                  })}
                  size="sm"
                  variant="warning"
                />
              ) : null}
            </View>
            <Button
              label={
                resolvedExpanded
                  ? t("documents.extraction.actions.collapseSection")
                  : t("documents.extraction.actions.expandSection")
              }
              onPress={toggleExpanded}
              size="sm"
              variant="ghost"
            />
          </View>
        }
      />
      {resolvedExpanded ? (
        <CardContent topDivider>
          <View style={{ gap: theme.spacing[3] }}>
            {fields.map((field) => (
              <BEOExtractedField
                compact={compact}
                descriptor={descriptors?.[field.fieldKey]}
                disabled={disabled}
                editable={editable}
                field={field}
                key={field.id}
                onApprove={onFieldApprove}
                onChange={onFieldChange}
                validationErrors={validationErrors?.fields?.[field.id]}
              />
            ))}
          </View>
        </CardContent>
      ) : null}
    </BaseCard>
  );
}
