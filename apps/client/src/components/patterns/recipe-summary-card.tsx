import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { RecipeStatusBadge } from "@/components/patterns/recipe-status-badge";
import {
  formatRecipeCurrency,
  formatRecipeDuration,
  formatRecipeYield,
  getRecipeAllergenCount,
  getRecipeDefaultYield,
  getRecipeIngredientCount,
  getRecipeStatus,
  getRecipeStepCount,
  type RecipeDisplayRecord,
  type RecipeVersionRecord,
} from "@/features/recipes";

export type RecipeSummaryMetric = SummaryMetric & {
  id?: string;
};

export type RecipeSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currentVersion?: RecipeVersionRecord | null;
  metrics?: RecipeSummaryMetric[];
  recipe: RecipeDisplayRecord;
};

export function RecipeSummaryCard({
  accessibilityLabel,
  compact = false,
  currentVersion,
  metrics,
  recipe,
}: RecipeSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const status = getRecipeStatus(recipe);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          formatRecipeYield(getRecipeDefaultYield(currentVersion), i18n.language)
            ? {
                id: "yield",
                label: t("recipes.labels.yield"),
                value: formatRecipeYield(getRecipeDefaultYield(currentVersion), i18n.language),
              }
            : null,
          typeof getRecipeIngredientCount(currentVersion) === "number"
            ? {
                id: "ingredients",
                label: t("recipes.labels.ingredients"),
                value: t("recipes.metrics.ingredients", {
                  count: getRecipeIngredientCount(currentVersion),
                }),
              }
            : null,
          typeof getRecipeStepCount(currentVersion) === "number"
            ? {
                id: "steps",
                label: t("recipes.labels.steps"),
                value: t("recipes.metrics.steps", {
                  count: getRecipeStepCount(currentVersion),
                }),
              }
            : null,
          currentVersion?.prepTimeMinutes
            ? {
                id: "prepTime",
                label: t("recipes.labels.prepTime"),
                value: formatRecipeDuration(currentVersion.prepTimeMinutes, t),
              }
            : null,
          currentVersion?.cookTimeMinutes
            ? {
                id: "cookTime",
                label: t("recipes.labels.cookTime"),
                value: formatRecipeDuration(currentVersion.cookTimeMinutes, t),
              }
            : null,
          currentVersion?.totalTimeMinutes
            ? {
                id: "totalTime",
                label: t("recipes.labels.totalTime"),
                value: formatRecipeDuration(currentVersion.totalTimeMinutes, t),
              }
            : null,
          formatRecipeCurrency(
            currentVersion?.estimatedTotalCost,
            currentVersion?.costCurrency,
            i18n.language
          )
            ? {
                id: "estimatedTotalCost",
                label: t("recipes.labels.estimatedCost"),
                value: formatRecipeCurrency(
                  currentVersion?.estimatedTotalCost,
                  currentVersion?.costCurrency,
                  i18n.language
                ),
              }
            : null,
          formatRecipeCurrency(
            currentVersion?.estimatedCostPerYield,
            currentVersion?.costCurrency,
            i18n.language
          )
            ? {
                id: "estimatedCostPerYield",
                label: t("recipes.labels.costPerYield"),
                value: formatRecipeCurrency(
                  currentVersion?.estimatedCostPerYield,
                  currentVersion?.costCurrency,
                  i18n.language
                ),
              }
            : null,
          typeof getRecipeAllergenCount(currentVersion) === "number"
            ? {
                id: "allergens",
                label: t("recipes.labels.allergens"),
                value: t("recipes.metrics.allergens", {
                  count: getRecipeAllergenCount(currentVersion),
                }),
              }
            : null,
        ].filter(Boolean) as RecipeSummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.summary.accessibilityLabel")}
      metrics={compact ? resolvedMetrics.slice(0, 3) : resolvedMetrics}
      subtitle={recipe.description ?? currentVersion?.description ?? undefined}
      title={recipe.name}
      trailing={status ? <RecipeStatusBadge size="sm" status={status} /> : undefined}
      variant="elevated"
    />
  );
}
