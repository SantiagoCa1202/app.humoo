import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import {
  formatBeoChangeValue,
  formatExtractionConfidence,
  getBeoChangeLabel,
  getBeoFieldChangeType,
  getExtractionConfidenceState,
  getExtractionConfidenceTranslationKey,
} from "@/features/documents";
import type { BEOFieldChangeRecord, ExtractedFieldValueType } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";
import type { SemanticStatusTone } from "@/theme/status-config";

export type BEOFieldComparisonProps = {
  accessibilityLabel?: string;
  change: BEOFieldChangeRecord;
  compact?: boolean;
  timeZone?: string | null;
  onPress?: () => void | Promise<void>;
};

function getChangeBadgeVariant(changeType: BEOFieldComparisonProps["change"]["changeType"]): SemanticStatusTone {
  if (changeType === "added") return "success";
  if (changeType === "removed") return "danger";
  if (changeType === "changed") return "warning";
  return "neutral";
}

function renderValue(value: string | null, emptyLabel: string) {
  return value ? (
    <Text selectable variant="bodySmall">
      {value}
    </Text>
  ) : (
    <Text selectable tone="muted" variant="bodySmall">
      {emptyLabel}
    </Text>
  );
}

export function BEOFieldComparison({
  accessibilityLabel,
  change,
  compact = false,
  onPress,
  timeZone,
}: BEOFieldComparisonProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const changeType = getBeoFieldChangeType(change.changeType) ?? "changed";
  const label = getBeoChangeLabel(change, t);
  const valueType = change.valueType as ExtractedFieldValueType | null | undefined;
  const confidenceState = getExtractionConfidenceState(change.confidence);
  const confidenceLabel = formatExtractionConfidence(change.confidence, i18n.language);
  const previousValue = formatBeoChangeValue(
    change.previousValue,
    valueType,
    i18n.language,
    timeZone
  );
  const nextValue = formatBeoChangeValue(change.nextValue, valueType, i18n.language, timeZone);

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("documents.comparison.field.accessibilityLabel", { changeType: t(`documents.comparison.changeTypes.${changeType}`), field: label })
      }
      onPress={onPress}
      padding={compact ? "md" : "lg"}
      variant="outlined"
    >
      <CardHeader
        padding="none"
        subtitle={
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
            <Badge
              label={t(`documents.comparison.changeTypes.${changeType}`)}
              size="sm"
              variant={getChangeBadgeVariant(changeType)}
            />
            {confidenceLabel ? (
              <Badge
                label={t("documents.extraction.confidenceLabel", {
                  state: t(getExtractionConfidenceTranslationKey(confidenceState)),
                  value: confidenceLabel,
                })}
                size="sm"
                variant={confidenceState === "low" ? "warning" : "neutral"}
              />
            ) : null}
          </View>
        }
        title={label}
      />
      <CardContent padding="none" topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          <View
            style={{
              flexDirection: compact ? "column" : "row",
              flexWrap: "wrap",
              gap: theme.spacing[3],
            }}
          >
            <View
              style={{
                backgroundColor: theme.colors.status.dangerSoft,
                borderColor: theme.colors.status.danger,
                borderCurve: "continuous",
                borderRadius: theme.radius.md,
                borderWidth: 1,
                flex: 1,
                gap: theme.spacing[2],
                minWidth: 180,
                padding: theme.spacing[3],
              }}
            >
              <Text tone="danger" variant="overline">
                {t("documents.comparison.before")}
              </Text>
              {renderValue(previousValue, t("documents.comparison.emptyValue"))}
            </View>
            <View
              style={{
                backgroundColor: theme.colors.status.successSoft,
                borderColor: theme.colors.status.success,
                borderCurve: "continuous",
                borderRadius: theme.radius.md,
                borderWidth: 1,
                flex: 1,
                gap: theme.spacing[2],
                minWidth: 180,
                padding: theme.spacing[3],
              }}
            >
              <Text tone="success" variant="overline">
                {t("documents.comparison.after")}
              </Text>
              {renderValue(nextValue, t("documents.comparison.emptyValue"))}
            </View>
          </View>
          {change.impact?.trim() ? (
            <View style={{ gap: theme.spacing[1] }}>
              <Text tone="secondary" variant="overline">
                {t("documents.comparison.impact")}
              </Text>
              <Text selectable variant="bodySmall">
                {change.impact.trim()}
              </Text>
            </View>
          ) : null}
        </View>
      </CardContent>
    </BaseCard>
  );
}
