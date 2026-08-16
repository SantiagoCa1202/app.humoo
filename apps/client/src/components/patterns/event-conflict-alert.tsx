import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ComparisonCard, type ComparisonChange } from "@/components/patterns/comparison-card";
import { ConflictState } from "@/components/patterns/conflict-state";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import type { EventConflictType } from "@/features/events";

export type EventConflictAlertProps = {
  accessibilityLabel?: string;
  changes?: ComparisonChange[];
  compact?: boolean;
  conflictType?: EventConflictType;
  description?: React.ReactNode;
  onKeepCurrent?: () => void | Promise<void>;
  onReload?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  title?: React.ReactNode;
};

function getConflictDescriptionKey(conflictType?: EventConflictType) {
  if (conflictType === "remote_update") {
    return "events.related.conflict.types.remote_update";
  }

  if (conflictType === "beo_change") {
    return "events.related.conflict.types.beo_change";
  }

  if (conflictType === "stale_data") {
    return "events.related.conflict.types.stale_data";
  }

  return "events.related.conflict.types.version_conflict";
}

export function EventConflictAlert({
  accessibilityLabel,
  changes,
  compact = false,
  conflictType = "version_conflict",
  description,
  onKeepCurrent,
  onReload,
  onReview,
  title,
}: EventConflictAlertProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTitle = title ?? t("events.related.conflict.title");
  const resolvedDescription =
    description ?? t(getConflictDescriptionKey(conflictType));

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("events.related.conflict.accessibilityLabel")}
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
                  label={t("events.related.conflict.actions.reload")}
                  onPress={onReload}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReview ? (
                <Button
                  label={t("events.related.conflict.actions.review")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onKeepCurrent ? (
                <Button
                  label={t("events.related.conflict.actions.keepCurrent")}
                  onPress={onKeepCurrent}
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
      {changes?.length ? (
        <ComparisonCard
          changes={changes}
          onAccept={onReview}
          onReject={onKeepCurrent}
          subtitle={t("events.related.conflict.reviewSubtitle")}
          title={t("events.related.conflict.reviewTitle")}
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
