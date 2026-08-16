import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { RecipeIngredientRow } from "@/components/patterns/recipe-ingredient-row";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import {
  sortRecipeIngredients,
  type RecipeIngredientRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeIngredientsListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  editable?: boolean;
  ingredients: RecipeIngredientRecord[];
  loading?: boolean;
  onEditIngredient?: (ingredient: RecipeIngredientRecord) => void | Promise<void>;
  onIngredientPress?: (ingredient: RecipeIngredientRecord) => void | Promise<void>;
  onRemoveIngredient?: (ingredient: RecipeIngredientRecord) => void | Promise<void>;
};

export function RecipeIngredientsList({
  accessibilityLabel,
  compact = false,
  editable = false,
  ingredients,
  loading = false,
  onEditIngredient,
  onIngredientPress,
  onRemoveIngredient,
}: RecipeIngredientsListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const orderedIngredients = sortRecipeIngredients(ingredients);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.ingredients.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="default"
    >
      <CardHeader
        subtitle={t("recipes.ingredients.subtitle")}
        title={t("recipes.ingredients.title")}
      />
      <CardContent topDivider>
        {loading ? (
          <View style={{ gap: theme.spacing[3] }}>
            {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
              <SkeletonText key={`recipe-ingredient-skeleton-${index}`} lines={2} />
            ))}
          </View>
        ) : orderedIngredients.length === 0 ? (
          <EmptyState
            compact
            description={t("recipes.ingredients.emptyDescription")}
            title={t("recipes.ingredients.emptyTitle")}
          />
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            {orderedIngredients.map((ingredient, index) => (
              <View key={ingredient.id} style={{ gap: theme.spacing[3] }}>
                <RecipeIngredientRow
                  compact={compact}
                  editable={editable}
                  ingredient={ingredient}
                  onEdit={
                    onEditIngredient ? () => void onEditIngredient(ingredient) : undefined
                  }
                  onPress={
                    onIngredientPress ? () => void onIngredientPress(ingredient) : undefined
                  }
                  onRemove={
                    onRemoveIngredient ? () => void onRemoveIngredient(ingredient) : undefined
                  }
                />
                {index < orderedIngredients.length - 1 ? <Divider spacing="none" /> : null}
              </View>
            ))}
          </View>
        )}
      </CardContent>
    </BaseCard>
  );
}
