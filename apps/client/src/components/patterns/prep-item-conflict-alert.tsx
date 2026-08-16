import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ComparisonCard, type ComparisonChange } from "@/components/patterns/comparison-card";
import { ConflictState } from "@/components/patterns/conflict-state";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  buildPrepItemConflictChanges,
  getPrepItemConflictDescriptionKey,
  type PrepItemConflictType,
  type PrepItemRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepItemConflictAlertProps = {
  accessibilityLabel?: string;
  changes?: ComparisonChange[];
  compact?: boolean;
  conflictType?: PrepItemConflictType;
  description?: React.ReactNode;
  localItem: PrepItemRecord;
  onDiscardLocal?: () => void | Promise<void>;
  onReload?: () => void | Promise<void>;
  onRetry?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  remoteItem?: PrepItemRecord | null;
  title?: React.ReactNode;
};

export function PrepItemConflictAlert({
  accessibilityLabel,
  changes,
  compact = false,
  conflictType = "version_conflict",
  description,
  localItem,
  onDiscardLocal,
  onReload,
  onRetry,
  onReview,
  remoteItem,
  title,
}: PrepItemConflictAlertProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTitle = title ?? t("prep.conflict.title");
  const resolvedDescription =
    description ?? t(getPrepItemConflictDescriptionKey(conflictType));
  const resolvedChanges =
    changes?.length
      ? changes
      : buildPrepItemConflictChanges(localItem, remoteItem, t, i18n.language);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.conflict.accessibilityLabel")}
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
                  label={t("prep.conflict.actions.reload")}
                  onPress={onReload}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReview ? (
                <Button
                  label={t("prep.conflict.actions.review")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onDiscardLocal ? (
                <Button
                  label={t("prep.conflict.actions.discardLocal")}
                  onPress={onDiscardLocal}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {onRetry ? (
                <Button
                  label={t("prep.conflict.actions.retry")}
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
      {resolvedChanges.length ? (
        <ComparisonCard
          changes={resolvedChanges}
          onAccept={onReview}
          onReject={onDiscardLocal}
          subtitle={t("prep.conflict.reviewSubtitle")}
          title={t("prep.conflict.reviewTitle")}
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
