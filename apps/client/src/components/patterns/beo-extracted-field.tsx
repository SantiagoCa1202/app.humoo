import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { Checkbox } from "@/components/primitives/checkbox";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { FieldMessage } from "@/components/primitives/field-message";
import { NumberField } from "@/components/primitives/number-field";
import { Select } from "@/components/primitives/select";
import { Text } from "@/components/primitives/text";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  applyExtractedFieldCorrection,
  approveExtractedField,
  formatExtractedFieldValue,
  formatExtractionConfidence,
  getExtractedFieldCorrectedValue,
  getExtractedFieldEffectiveValue,
  getExtractedFieldLabel,
  getExtractedFieldNormalizedValue,
  getExtractedFieldReviewStatus,
  getExtractedFieldValueType,
  getExtractionConfidenceState,
  hasExtractedFieldCorrection,
  type ExtractedFieldDescriptor,
  type ExtractedFieldRecord,
  type ExtractedFieldValidationErrors,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

function getReviewBadgeVariant(reviewStatus: ReturnType<typeof getExtractedFieldReviewStatus>) {
  switch (reviewStatus) {
    case "accepted":
      return "success" as const;
    case "corrected":
      return "warning" as const;
    case "rejected":
      return "danger" as const;
    case "pending":
    default:
      return "neutral" as const;
  }
}

function getConfidenceBadgeVariant(confidenceState: ReturnType<typeof getExtractionConfidenceState>) {
  switch (confidenceState) {
    case "high":
      return "success" as const;
    case "medium":
      return "info" as const;
    case "low":
      return "warning" as const;
    case "unknown":
    default:
      return "neutral" as const;
  }
}

function getDefaultInputKind(
  field: ExtractedFieldRecord,
  descriptor?: ExtractedFieldDescriptor | null
) {
  if (descriptor?.input) {
    return descriptor.input;
  }

  switch (getExtractedFieldValueType(field.valueType)) {
    case "integer":
    case "decimal":
      return "number";
    case "boolean":
      return "boolean";
    case "datetime":
      return "datetime";
    case "object":
    case "array":
      return "textarea";
    case "date":
      return "date";
    case "string":
    default:
      return "text";
  }
}

export type BEOExtractedFieldProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  descriptor?: ExtractedFieldDescriptor | null;
  disabled?: boolean;
  editable?: boolean;
  error?: string;
  field: ExtractedFieldRecord;
  onApprove?: (field: ExtractedFieldRecord) => void | Promise<void>;
  onChange?: (field: ExtractedFieldRecord) => void | Promise<void>;
  validationErrors?: ExtractedFieldValidationErrors;
};

export function BEOExtractedField({
  accessibilityLabel,
  compact = false,
  descriptor,
  disabled = false,
  editable = false,
  error,
  field,
  onApprove,
  onChange,
  validationErrors,
}: BEOExtractedFieldProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const fieldLabel = getExtractedFieldLabel(field, descriptor, t);
  const reviewStatus = getExtractedFieldReviewStatus(field.reviewStatus);
  const confidenceState = getExtractionConfidenceState(field.confidence);
  const confidenceLabel = formatExtractionConfidence(field.confidence, i18n.language);
  const normalizedValue = getExtractedFieldNormalizedValue(field);
  const correctedValue = getExtractedFieldCorrectedValue(field);
  const effectiveValue = getExtractedFieldEffectiveValue(field);
  const inputKind = getDefaultInputKind(field, descriptor);
  const timeZone = descriptor?.timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC";
  const formattedNormalizedValue = useMemo(
    () =>
      formatExtractedFieldValue(
        normalizedValue,
        getExtractedFieldValueType(field.valueType),
        i18n.language
      ),
    [field.valueType, i18n.language, normalizedValue]
  );
  const formattedCorrectedValue = useMemo(
    () =>
      formatExtractedFieldValue(
        correctedValue,
        getExtractedFieldValueType(field.valueType),
        i18n.language
      ),
    [correctedValue, field.valueType, i18n.language]
  );

  const handleChange = (nextValue: unknown) => {
    if (!onChange) {
      return;
    }

    void onChange(applyExtractedFieldCorrection(field, nextValue));
  };

  const renderEditor = () => {
    if (!editable || !onChange) {
      return null;
    }

    if (inputKind === "number") {
      const parsedValue =
        typeof effectiveValue === "number"
          ? effectiveValue
          : Number(effectiveValue ?? 0);

      return (
        <NumberField
          accessibilityLabel={descriptor?.accessibilityLabel ?? fieldLabel}
          disabled={disabled}
          error={validationErrors?.value}
          helperText={descriptor?.helperText}
          label={t("documents.extraction.correctedValue")}
          onChange={handleChange}
          step={getExtractedFieldValueType(field.valueType) === "integer" ? 1 : 0.01}
          value={Number.isFinite(parsedValue) ? parsedValue : 0}
        />
      );
    }

    if (inputKind === "boolean") {
      const checked = Boolean(effectiveValue);

      return (
        <Checkbox
          accessibilityLabel={descriptor?.accessibilityLabel ?? fieldLabel}
          checked={checked}
          disabled={disabled}
          label={t("documents.extraction.correctedValue")}
          onChange={handleChange}
        />
      );
    }

    if (inputKind === "select" && descriptor?.options?.length) {
      return (
        <Select
          accessibilityLabel={descriptor.accessibilityLabel ?? fieldLabel}
          disabled={disabled}
          error={validationErrors?.value}
          helperText={descriptor.helperText}
          label={t("documents.extraction.correctedValue")}
          onChange={handleChange}
          options={descriptor.options}
          placeholder={descriptor.placeholder}
          value={typeof effectiveValue === "string" ? effectiveValue : undefined}
        />
      );
    }

    if (inputKind === "entity" && descriptor?.entities?.length) {
      return (
        <EntityPicker
          accessibilityLabel={descriptor.accessibilityLabel ?? fieldLabel}
          disabled={disabled}
          entities={descriptor.entities}
          error={validationErrors?.value}
          helperText={descriptor.helperText}
          label={t("documents.extraction.correctedValue")}
          onChange={handleChange}
          placeholder={descriptor.placeholder}
          value={typeof effectiveValue === "string" ? effectiveValue : undefined}
        />
      );
    }

    if (inputKind === "datetime") {
      return (
        <DateTimeField
          editable={!disabled}
          error={validationErrors?.value}
          helperText={descriptor?.helperText}
          label={t("documents.extraction.correctedValue")}
          onChange={handleChange}
          timeZone={timeZone}
          value={typeof effectiveValue === "string" ? effectiveValue : null}
        />
      );
    }

    if (inputKind === "textarea") {
      return (
        <TextArea
          accessibilityLabel={descriptor?.accessibilityLabel ?? fieldLabel}
          autoGrow
          editable={!disabled}
          error={validationErrors?.value}
          helperText={descriptor?.helperText}
          label={t("documents.extraction.correctedValue")}
          onChangeText={handleChange}
          placeholder={descriptor?.placeholder}
          value={typeof effectiveValue === "string" ? effectiveValue : formattedCorrectedValue ?? formattedNormalizedValue ?? ""}
        />
      );
    }

    return (
      <TextField
        accessibilityLabel={descriptor?.accessibilityLabel ?? fieldLabel}
        editable={!disabled}
        error={validationErrors?.value}
        helperText={descriptor?.helperText}
        label={t("documents.extraction.correctedValue")}
        onChangeText={handleChange}
        placeholder={
          descriptor?.placeholder ??
          (inputKind === "date" ? t("documents.extraction.datePlaceholder") : undefined)
        }
        value={typeof effectiveValue === "string" ? effectiveValue : formattedCorrectedValue ?? formattedNormalizedValue ?? ""}
      />
    );
  };

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? `${fieldLabel}`}
      padding={compact ? "md" : "lg"}
      variant="muted"
    >
      <View style={{ gap: theme.spacing[3] }}>
        <View
          style={{
            alignItems: "flex-start",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[2],
            justifyContent: "space-between",
          }}
        >
          <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 160 }}>
            <Text variant="title">{fieldLabel}</Text>
            <Text tone="muted" variant="caption">
              {field.fieldKey}
            </Text>
          </View>
          <View style={{ alignItems: "flex-end", flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
            {reviewStatus ? (
              <Badge
                label={t(`documents.extraction.reviewStatus.${reviewStatus}`)}
                size="sm"
                variant={getReviewBadgeVariant(reviewStatus)}
              />
            ) : null}
            {confidenceLabel ? (
              <Badge
                label={t("documents.extraction.confidenceLabel", {
                  state: t(`documents.extraction.confidence.${confidenceState}`),
                  value: confidenceLabel,
                })}
                size="sm"
                variant={getConfidenceBadgeVariant(confidenceState)}
              />
            ) : null}
            {onApprove ? (
              <Button
                disabled={disabled}
                label={t("documents.actions.confirm")}
                onPress={() => onApprove(approveExtractedField(field))}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </View>
        <View style={{ gap: theme.spacing[2] }}>
          {field.rawValue?.trim() ? (
            <View style={{ gap: theme.spacing[1] }}>
              <Text tone="muted" variant="overline">
                {t("documents.extraction.originalValue")}
              </Text>
              <Text selectable variant="bodySmall">
                {field.rawValue}
              </Text>
            </View>
          ) : null}
          {formattedNormalizedValue ? (
            <View style={{ gap: theme.spacing[1] }}>
              <Text tone="muted" variant="overline">
                {t("documents.extraction.extractedValue")}
              </Text>
              <Text selectable variant="bodySmall">
                {formattedNormalizedValue}
              </Text>
            </View>
          ) : null}
          {hasExtractedFieldCorrection(field) && formattedCorrectedValue ? (
            <View style={{ gap: theme.spacing[1] }}>
              <Text tone="muted" variant="overline">
                {t("documents.extraction.correctedValue")}
              </Text>
              <Text selectable tone="primary" variant="bodySmall">
                {formattedCorrectedValue}
              </Text>
            </View>
          ) : null}
        </View>
        {renderEditor()}
        <FieldMessage
          error={error ?? validationErrors?.form}
          helperText={
            confidenceState === "low"
              ? t("documents.extraction.lowConfidenceDescription")
              : undefined
          }
        />
      </View>
    </BaseCard>
  );
}
