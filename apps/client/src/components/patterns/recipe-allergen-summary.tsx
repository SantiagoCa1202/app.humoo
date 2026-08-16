import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { SummaryCard } from "@/components/patterns/summary-card";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  getRecipeAllergenLabel,
  getRecipeAllergenTone,
  hasRecipeAllergenRisk,
  type RecipeAllergenRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeAllergenSummaryProps = {
  accessibilityLabel?: string;
  allergens: RecipeAllergenRecord[];
  compact?: boolean;
  incomplete?: boolean;
  showWarning?: boolean;
  unknownIngredientsCount?: number;
};

export function RecipeAllergenSummary({
  accessibilityLabel,
  allergens,
  compact = false,
  incomplete = false,
  showWarning = false,
  unknownIngredientsCount = 0,
}: RecipeAllergenSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const hasRisk = hasRecipeAllergenRisk(allergens);
  const shouldWarn = showWarning && (incomplete || unknownIngredientsCount > 0 || hasRisk);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.allergens.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <SummaryCard
        metrics={[
          {
            label: t("recipes.labels.allergens"),
            value: new Intl.NumberFormat(i18n.language).format(allergens.length),
          },
          {
            label: t("recipes.allergens.unknownIngredients"),
            tone: unknownIngredientsCount > 0 ? "warning" : "default",
            value: new Intl.NumberFormat(i18n.language).format(unknownIngredientsCount),
          },
        ]}
        subtitle={t("recipes.allergens.subtitle")}
        title={t("recipes.allergens.title")}
        trailing={
          incomplete ? (
            <Badge label={t("recipes.allergens.incomplete")} size="sm" variant="warning" />
          ) : undefined
        }
        variant="elevated"
      />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {allergens.length ? (
          allergens.slice(0, compact ? 6 : allergens.length).map((allergen, index) => (
            <Badge
              key={allergen.id ?? allergen.key ?? `recipe-allergen-${index}`}
              label={getRecipeAllergenLabel(allergen, t) ?? t("recipes.allergens.unknown")}
              size="sm"
              variant={getRecipeAllergenTone(allergen)}
            />
          ))
        ) : (
          <Text tone="muted" variant="bodySmall">
            {t("recipes.allergens.empty")}
          </Text>
        )}
      </View>
      {shouldWarn ? (
        <AlertCard
          description={
            incomplete
              ? t("recipes.allergens.incompleteDescription")
              : t("recipes.allergens.unknownIngredientsDescription", {
                  count: unknownIngredientsCount,
                })
          }
          title={t("recipes.allergens.warningTitle")}
          tone="warning"
          variant="muted"
        />
      ) : null}
    </View>
  );
}
