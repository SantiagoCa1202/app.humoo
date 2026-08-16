import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ComparisonCard } from "@/components/patterns/comparison-card";
import { ConflictState } from "@/components/patterns/conflict-state";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  getMenuConflictDescriptionKey,
  type MenuConflictChange,
  type MenuConflictType,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuConflictAlertProps = {
  accessibilityLabel?: string;
  changes?: MenuConflictChange[];
  compact?: boolean;
  conflictType?: MenuConflictType;
  description?: React.ReactNode;
  onKeepCurrent?: () => void | Promise<void>;
  onReload?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function MenuConflictAlert({
  accessibilityLabel,
  changes,
  compact = false,
  conflictType = "version_conflict",
  description,
  onKeepCurrent,
  onReload,
  onReview,
  title,
}: MenuConflictAlertProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTitle = title ?? t("menus.conflict.title");
  const resolvedDescription =
    description ?? t(getMenuConflictDescriptionKey(conflictType));

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.conflict.accessibilityLabel")}
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
                  label={t("menus.conflict.actions.reload")}
                  onPress={onReload}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReview ? (
                <Button
                  label={t("menus.conflict.actions.review")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onKeepCurrent ? (
                <Button
                  label={t("menus.conflict.actions.keepCurrent")}
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
          subtitle={t("menus.conflict.reviewSubtitle")}
          title={t("menus.conflict.reviewTitle")}
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
