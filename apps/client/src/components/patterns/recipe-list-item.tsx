import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import { RecipeStatusBadge } from "@/components/patterns/recipe-status-badge";
import {
  formatRecipeYield,
  getRecipeDefaultYield,
  getRecipeIngredientCount,
  getRecipeStatus,
  type RecipeDisplayRecord,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeListItemProps = {
  accessibilityLabel?: string;
  currentVersion?: RecipeVersionRecord | null;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  recipe: RecipeDisplayRecord;
  selected?: boolean;
  showStatus?: boolean;
};

export function RecipeListItem({
  accessibilityLabel,
  currentVersion,
  disabled = false,
  onPress,
  recipe,
  selected = false,
  showStatus = true,
}: RecipeListItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getRecipeStatus(recipe);
  const recipeYield = formatRecipeYield(getRecipeDefaultYield(currentVersion), i18n.language);
  const ingredientCount = getRecipeIngredientCount(currentVersion);
  const secondary = [recipeYield, typeof ingredientCount === "number"
    ? t("recipes.metrics.ingredients", { count: ingredientCount })
    : null]
    .filter(Boolean)
    .join(" - ");

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.listItem.accessibilityLabel")}
      disabled={disabled}
      onPress={onPress}
      padding="md"
      radius="md"
      selected={selected}
      variant="muted"
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          <Text numberOfLines={2} selectable variant="title">
            {recipe.name}
          </Text>
          {secondary ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {secondary}
            </Text>
          ) : null}
          {currentVersion ? (
            <Text selectable tone="muted" variant="caption">
              {getRecipeStatus(recipe)
                ? t("recipes.version.listHint", {
                    version: currentVersion.version,
                  })
                : t("recipes.version.listHint", {
                    version: currentVersion.version,
                  })}
            </Text>
          ) : null}
        </View>
        {showStatus ? <RecipeStatusBadge size="sm" status={status} /> : null}
      </View>
    </BaseCard>
  );
}
