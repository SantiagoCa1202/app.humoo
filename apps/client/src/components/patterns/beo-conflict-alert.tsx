import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { BEOVersionComparison } from "@/components/patterns/beo-version-comparison";
import { ComparisonCard, type ComparisonChange } from "@/components/patterns/comparison-card";
import { ConflictState } from "@/components/patterns/conflict-state";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  formatBeoChangeValue,
  getBeoChangeLabel,
  getBeoConflictDescriptionKey,
  getBeoConflictType,
  getExtractedFieldValueType,
  type BEOConflictType,
  type BEOFieldChangeRecord,
  type BeoVersionRecord,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOConflictAlertProps = {
  accessibilityLabel?: string;
  changes?: BEOFieldChangeRecord[];
  compact?: boolean;
  conflictType?: BEOConflictType | (string & {}) | null;
  description?: React.ReactNode;
  localVersion?: BeoVersionRecord | null;
  onDiscardLocal?: () => void | Promise<void>;
  onReload?: () => void | Promise<void>;
  onRetry?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  remoteVersion?: BeoVersionRecord | null;
  title?: React.ReactNode;
};

export function BEOConflictAlert({
  accessibilityLabel,
  changes,
  compact = false,
  conflictType,
  description,
  localVersion,
  onDiscardLocal,
  onReload,
  onRetry,
  onReview,
  remoteVersion,
  title,
}: BEOConflictAlertProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedType = getBeoConflictType(conflictType) ?? "version_conflict";
  const resolvedTitle = title ?? t("documents.conflict.title");
  const resolvedDescription =
    description ?? t(getBeoConflictDescriptionKey(resolvedType));
  const comparisonChanges: ComparisonChange[] =
    changes?.map((change) => ({
      after:
        formatBeoChangeValue(
          change.nextValue,
          getExtractedFieldValueType(change.valueType),
          i18n.language
        ) ??
        t("documents.comparison.emptyValue"),
      before:
        formatBeoChangeValue(
          change.previousValue,
          getExtractedFieldValueType(change.valueType),
          i18n.language
        ) ??
        t("documents.comparison.emptyValue"),
      id: change.id,
      label: getBeoChangeLabel(change, t),
    })) ?? [];

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("documents.conflict.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <AlertCard
        description={
          <View style={{ gap: theme.spacing[3] }}>
            {typeof resolvedDescription === "string" ? (
              <Text selectable variant="bodySmall">
                {resolvedDescription}
              </Text>
            ) : (
              resolvedDescription
            )}
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {onReload ? (
                <Button
                  label={t("documents.conflict.reloadLatest")}
                  onPress={onReload}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReview ? (
                <Button
                  label={t("documents.conflict.reviewLatest")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onDiscardLocal ? (
                <Button
                  label={t("documents.conflict.keepCurrent")}
                  onPress={onDiscardLocal}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {onRetry ? (
                <Button
                  label={t("documents.actions.retry")}
                  onPress={onRetry}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
            </View>
          </View>
        }
        title={resolvedTitle}
        tone="warning"
        variant="muted"
      />
      {localVersion && remoteVersion ? (
        <BEOVersionComparison
          baseVersion={localVersion}
          changes={changes ?? []}
          compact={compact}
          targetVersion={remoteVersion}
        />
      ) : comparisonChanges.length ? (
        <ComparisonCard
          changes={comparisonChanges}
          subtitle={t("documents.conflict.reviewSubtitle")}
          title={t("documents.conflict.reviewTitle")}
          variant="outlined"
        />
      ) : (
        <ConflictState
          compact={compact}
          description={resolvedDescription}
          onReload={onReload}
          onReview={onReview}
          title={resolvedTitle}
        />
      )}
    </View>
  );
}
