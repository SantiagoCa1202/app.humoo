import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ComparisonCard } from "@/components/patterns/comparison-card";
import { RecipeVersionCard } from "@/components/patterns/recipe-version-card";
import {
  buildRecipeVersionComparisonChanges,
  type RecipeVersionChange,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeVersionComparisonProps = {
  accessibilityLabel?: string;
  baseVersion: RecipeVersionRecord;
  changes?: RecipeVersionChange[];
  compact?: boolean;
  onSelectBase?: () => void | Promise<void>;
  onSelectTarget?: () => void | Promise<void>;
  targetVersion: RecipeVersionRecord;
};

export function RecipeVersionComparison({
  accessibilityLabel,
  baseVersion,
  changes,
  compact = false,
  onSelectBase,
  onSelectTarget,
  targetVersion,
}: RecipeVersionComparisonProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedChanges =
    changes?.length ? changes : buildRecipeVersionComparisonChanges(baseVersion, targetVersion, t);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.comparison.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <RecipeVersionCard
        compact={compact}
        isCurrent={false}
        onPress={onSelectBase}
        selected={false}
        version={baseVersion}
      />
      <RecipeVersionCard
        compact={compact}
        isCurrent
        onPress={onSelectTarget}
        selected={false}
        version={targetVersion}
      />
      <ComparisonCard
        changes={resolvedChanges}
        subtitle={t("recipes.comparison.subtitle")}
        title={t("recipes.comparison.title")}
        variant="outlined"
      />
    </View>
  );
}
