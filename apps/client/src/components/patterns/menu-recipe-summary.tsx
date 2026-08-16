import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ListCard } from "@/components/patterns/list-card";
import { SummaryCard } from "@/components/patterns/summary-card";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  getMenuRecipeSummaryLabel,
  type MenuRecipeSummaryRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuRecipeSummaryProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  linkedCount: number;
  onRecipePress?: (recipe: MenuRecipeSummaryRecord) => void;
  recipes: MenuRecipeSummaryRecord[];
  totalItems: number;
  unlinkedCount?: number;
};

export function MenuRecipeSummary({
  accessibilityLabel,
  compact = false,
  linkedCount,
  onRecipePress,
  recipes,
  totalItems,
  unlinkedCount,
}: MenuRecipeSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedUnlinkedCount =
    unlinkedCount ?? Math.max(totalItems - linkedCount, 0);
  const visibleRecipes = compact ? recipes.slice(0, 3) : recipes;

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.recipeSummary.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <SummaryCard
        metrics={[
          {
            label: t("menus.recipeSummary.linkedRecipes"),
            value: new Intl.NumberFormat(i18n.language).format(recipes.length),
          },
          {
            label: t("menus.recipeSummary.linkedItems"),
            value: new Intl.NumberFormat(i18n.language).format(linkedCount),
          },
          {
            label: t("menus.recipeSummary.itemsWithoutRecipe"),
            tone: resolvedUnlinkedCount > 0 ? "warning" : "default",
            value: new Intl.NumberFormat(i18n.language).format(resolvedUnlinkedCount),
          },
        ]}
        subtitle={t("menus.recipeSummary.subtitle", { count: totalItems })}
        title={t("menus.recipeSummary.title")}
        trailing={
          resolvedUnlinkedCount > 0 ? (
            <Badge
              label={t("menus.recipeSummary.itemsWithoutRecipe")}
              size="sm"
              variant="warning"
            />
          ) : undefined
        }
        variant="elevated"
      />
      <ListCard
        accessibilityLabel={t("menus.recipeSummary.listAccessibilityLabel")}
        emptyContent={
          <Text tone="muted" variant="bodySmall">
            {t("menus.recipeSummary.empty")}
          </Text>
        }
        items={visibleRecipes.map((recipe, index) => ({
          id: recipe.id ?? `recipe-summary-${index}`,
          subtitle: recipe.itemNames?.length
            ? recipe.itemNames.join(", ")
            : typeof recipe.itemCount === "number"
            ? t("menus.metrics.items", { count: recipe.itemCount })
            : undefined,
          title: getMenuRecipeSummaryLabel(recipe) ?? t("menus.recipeSummary.unknownRecipe"),
          trailing:
            typeof recipe.itemCount === "number" ? (
              <Badge
                label={t("menus.metrics.items", { count: recipe.itemCount })}
                size="sm"
                variant="neutral"
              />
            ) : undefined,
        }))}
        onItemPress={
          onRecipePress
            ? (item) => {
                const recipe = visibleRecipes.find((entry) => entry.id === item.id);

                if (recipe) {
                  onRecipePress(recipe);
                }
              }
            : undefined
        }
        subtitle={t("menus.recipeSummary.listSubtitle")}
        title={t("menus.recipeSummary.listTitle")}
        variant="default"
      />
    </View>
  );
}
