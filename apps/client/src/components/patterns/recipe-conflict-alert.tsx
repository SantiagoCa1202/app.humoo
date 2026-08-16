import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ConflictState } from "@/components/patterns/conflict-state";
import { RecipeVersionComparison } from "@/components/patterns/recipe-version-comparison";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  getRecipeConflictDescriptionKey,
  type RecipeConflictType,
  type RecipeVersionChange,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeConflictAlertProps = {
  accessibilityLabel?: string;
  changes?: RecipeVersionChange[];
  compact?: boolean;
  conflictType?: RecipeConflictType;
  description?: React.ReactNode;
  localVersion?: RecipeVersionRecord | null;
  onCreateNewVersion?: () => void | Promise<void>;
  onKeepCurrent?: () => void | Promise<void>;
  onReload?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  remoteVersion?: RecipeVersionRecord | null;
  title?: React.ReactNode;
};

export function RecipeConflictAlert({
  accessibilityLabel,
  changes,
  compact = false,
  conflictType = "version_conflict",
  description,
  localVersion,
  onCreateNewVersion,
  onKeepCurrent,
  onReload,
  onReview,
  remoteVersion,
  title,
}: RecipeConflictAlertProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTitle = title ?? t("recipes.conflict.title");
  const resolvedDescription = description ?? t(getRecipeConflictDescriptionKey(conflictType));

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.conflict.accessibilityLabel")}
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
                  label={t("recipes.conflict.actions.reload")}
                  onPress={onReload}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReview ? (
                <Button
                  label={t("recipes.conflict.actions.review")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onKeepCurrent ? (
                <Button
                  label={t("recipes.conflict.actions.keepCurrent")}
                  onPress={onKeepCurrent}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {onCreateNewVersion ? (
                <Button
                  label={t("recipes.conflict.actions.createNewVersion")}
                  onPress={onCreateNewVersion}
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
        <RecipeVersionComparison
          baseVersion={localVersion}
          changes={changes}
          compact={compact}
          targetVersion={remoteVersion}
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
