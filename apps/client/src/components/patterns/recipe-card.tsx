import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { Badge } from "@/components/primitives/badge";
import {
  formatRecipeCurrency,
  formatRecipeYield,
  getRecipeDefaultYield,
  getRecipeIngredientCount,
  getRecipeStatus,
  getRecipeStepCount,
  getRecipeSummary,
  getRecipeVersionLabel,
  type RecipeDisplayRecord,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { RecipeStatusBadge } from "@/components/patterns/recipe-status-badge";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currentVersion?: RecipeVersionRecord | null;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  recipe: RecipeDisplayRecord;
  selected?: boolean;
  showStatus?: boolean;
  trailing?: React.ReactNode;
};

export function RecipeCard({
  accessibilityLabel,
  compact = false,
  currentVersion,
  disabled = false,
  onPress,
  recipe,
  selected = false,
  showStatus = true,
  trailing,
}: RecipeCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getRecipeStatus(recipe);
  const recipeYield = formatRecipeYield(getRecipeDefaultYield(currentVersion), i18n.language);
  const ingredientCount = getRecipeIngredientCount(currentVersion);
  const stepCount = getRecipeStepCount(currentVersion);
  const estimatedCost = formatRecipeCurrency(
    currentVersion?.estimatedTotalCost,
    currentVersion?.costCurrency,
    i18n.language
  );
  const metadata: EntityCardMetadataItem[] = [
    recipeYield
      ? {
          label: t("recipes.labels.yield"),
          value: recipeYield,
        }
      : null,
    typeof ingredientCount === "number"
      ? {
          label: t("recipes.labels.ingredients"),
          value: t("recipes.metrics.ingredients", { count: ingredientCount }),
        }
      : null,
    typeof stepCount === "number"
      ? {
          label: t("recipes.labels.steps"),
          value: t("recipes.metrics.steps", { count: stepCount }),
        }
      : null,
    !compact && estimatedCost
      ? {
          label: t("recipes.labels.estimatedCost"),
          value: estimatedCost,
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.card.accessibilityLabel")}
      disabled={disabled}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={getRecipeSummary(recipe, currentVersion) ?? undefined}
      title={recipe.name}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {trailing}
          {currentVersion ? (
            <Badge
              label={getRecipeVersionLabel(currentVersion, t) ?? t("recipes.version.current")}
              size="sm"
              variant="neutral"
            />
          ) : null}
          {showStatus ? <RecipeStatusBadge size={compact ? "sm" : "md"} status={status} /> : null}
        </View>
      }
      variant={compact ? "muted" : "elevated"}
    />
  );
}
