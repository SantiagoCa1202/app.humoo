import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { DetailCard } from "@/components/patterns/detail-card";
import { SummaryCard } from "@/components/patterns/summary-card";
import { Badge } from "@/components/primitives/badge";
import {
  formatRecipeCurrency,
  getRecipeCostMissingState,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeCostIngredientCost = {
  id: string;
  cost?: number | null;
  name: string;
};

export type RecipeCostSummaryProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  costPerYield?: number | null;
  currency?: string | null;
  estimated?: boolean;
  ingredientCosts?: RecipeCostIngredientCost[];
  missingCostCount?: number | null;
  totalCost?: number | null;
};

export function RecipeCostSummary({
  accessibilityLabel,
  compact = false,
  costPerYield,
  currency,
  estimated = false,
  ingredientCosts,
  missingCostCount = 0,
  totalCost,
}: RecipeCostSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedMissingCostCount = missingCostCount ?? 0;
  const totalCostLabel = formatRecipeCurrency(totalCost, currency, i18n.language);
  const costPerYieldLabel = formatRecipeCurrency(costPerYield, currency, i18n.language);
  const shouldWarn = getRecipeCostMissingState(resolvedMissingCostCount, estimated);
  const metrics = [
    totalCostLabel
      ? {
          label: t("recipes.cost.totalCost"),
          value: totalCostLabel,
        }
      : null,
    costPerYieldLabel
      ? {
          label: t("recipes.cost.costPerYield"),
          value: costPerYieldLabel,
        }
      : null,
    {
      label: t("recipes.cost.missingCosts"),
      tone: resolvedMissingCostCount > 0 ? "warning" : undefined,
      value: new Intl.NumberFormat(i18n.language).format(resolvedMissingCostCount),
    },
  ].filter(Boolean) as React.ComponentProps<typeof SummaryCard>["metrics"];

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.cost.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <SummaryCard
        metrics={metrics}
        subtitle={estimated ? t("recipes.cost.estimatedDescription") : undefined}
        title={t("recipes.cost.title")}
        trailing={
          estimated ? (
            <Badge label={t("recipes.cost.estimated")} size="sm" variant="warning" />
          ) : undefined
        }
        variant="elevated"
      />
      {ingredientCosts?.length && !compact ? (
        <DetailCard
          rows={ingredientCosts.map((ingredient) => ({
            label: ingredient.name,
            value:
              formatRecipeCurrency(ingredient.cost, currency, i18n.language) ??
              t("recipes.cost.missingValue"),
          }))}
          subtitle={t("recipes.cost.ingredientsSubtitle")}
          title={t("recipes.cost.ingredientsTitle")}
          variant="default"
        />
      ) : null}
      {shouldWarn ? (
        <AlertCard
          description={
            resolvedMissingCostCount > 0
              ? t("recipes.cost.missingDescription", { count: resolvedMissingCostCount })
              : t("recipes.cost.estimatedDescription")
          }
          title={
            resolvedMissingCostCount > 0
              ? t("recipes.cost.missingTitle")
              : t("recipes.cost.estimatedTitle")
          }
          tone="warning"
          variant="muted"
        />
      ) : null}
    </View>
  );
}
