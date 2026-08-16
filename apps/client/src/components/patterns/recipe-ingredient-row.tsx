import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import {
  formatRecipeCurrency,
  formatRecipeMeasurement,
  formatRecipePercent,
  getRecipeIngredientName,
  type RecipeIngredientRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeIngredientRowProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  editable?: boolean;
  ingredient: RecipeIngredientRecord;
  onEdit?: () => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  onRemove?: () => void | Promise<void>;
  selected?: boolean;
};

export function RecipeIngredientRow({
  accessibilityLabel,
  compact = false,
  disabled = false,
  editable = false,
  ingredient,
  onEdit,
  onPress,
  onRemove,
  selected = false,
}: RecipeIngredientRowProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const hasActions = editable && (onEdit || onRemove);
  const quantity = formatRecipeMeasurement(ingredient.quantity, ingredient.unit, i18n.language);
  const waste = formatRecipePercent(ingredient.wastePercentage, i18n.language);
  const unitCost = formatRecipeCurrency(
    ingredient.unitCost,
    ingredient.costCurrency,
    i18n.language
  );
  const content = (
    <View style={{ flex: 1, gap: theme.spacing[1] }}>
      <Text
        numberOfLines={compact ? 1 : 2}
        selectable
        tone={selected ? "primary" : "default"}
        variant={compact ? "bodySmall" : "body"}
      >
        {getRecipeIngredientName(ingredient)}
      </Text>
      {ingredient.preparation?.trim() ? (
        <Text numberOfLines={compact ? 1 : 2} selectable tone="secondary" variant="caption">
          {ingredient.preparation.trim()}
        </Text>
      ) : null}
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {quantity ? <Badge label={quantity} size="sm" variant="neutral" /> : null}
        {waste && ingredient.wastePercentage ? (
          <Badge
            label={`${t("recipes.labels.waste")}: ${waste}`}
            size="sm"
            variant="warning"
          />
        ) : null}
        {unitCost ? (
          <Badge
            label={`${t("recipes.labels.unitCost")}: ${unitCost}`}
            size="sm"
            variant="neutral"
          />
        ) : null}
        {ingredient.optional ? (
          <Badge label={t("recipes.labels.optional")} size="sm" variant="neutral" />
        ) : null}
      </View>
    </View>
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.ingredients.rowAccessibilityLabel")}
      style={{
        alignItems: "flex-start",
        flexDirection: "row",
        gap: theme.spacing[3],
        opacity: disabled ? 0.72 : 1,
      }}
    >
      {onPress ? (
        <Pressable
          accessibilityRole="button"
          disabled={disabled}
          onPress={() => void onPress()}
          style={{ flex: 1 }}
        >
          {content}
        </Pressable>
      ) : (
        content
      )}
      {hasActions ? (
        <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
          {onEdit ? (
            <Tooltip content={t("recipes.actions.editIngredient")}>
              <IconButton
                accessibilityLabel={t("recipes.actions.editIngredient")}
                disabled={disabled}
                icon={<Text variant="bodySmall">e</Text>}
                onPress={onEdit}
                size="sm"
                variant="ghost"
              />
            </Tooltip>
          ) : null}
          {onRemove ? (
            <Tooltip content={t("recipes.actions.removeIngredient")}>
              <IconButton
                accessibilityLabel={t("recipes.actions.removeIngredient")}
                disabled={disabled}
                icon={<Text tone="danger" variant="bodySmall">x</Text>}
                onPress={onRemove}
                size="sm"
                variant="ghost"
              />
            </Tooltip>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}
