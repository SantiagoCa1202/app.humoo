import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import {
  formatRecipeDuration,
  getRecipeStepPosition,
  type RecipeStepRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeStepItemProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  editable?: boolean;
  index?: number;
  onEdit?: () => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  onRemove?: () => void | Promise<void>;
  step: RecipeStepRecord;
};

export function RecipeStepItem({
  accessibilityLabel,
  compact = false,
  disabled = false,
  editable = false,
  index = 0,
  onEdit,
  onPress,
  onRemove,
  step,
}: RecipeStepItemProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const hasActions = editable && (onEdit || onRemove);
  const position = getRecipeStepPosition(step, index);
  const duration = formatRecipeDuration(step.durationMinutes, t);
  const content = (
    <View style={{ flex: 1, gap: theme.spacing[1] }}>
      {step.title?.trim() ? (
        <Text selectable variant={compact ? "bodySmall" : "body"}>
          {step.title.trim()}
        </Text>
      ) : null}
      <Text selectable tone="default" variant={compact ? "bodySmall" : "body"}>
        {step.instruction}
      </Text>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {duration ? <Badge label={duration} size="sm" variant="neutral" /> : null}
        {step.type?.trim() ? (
          <Badge label={step.type.trim()} size="sm" variant="neutral" />
        ) : null}
        {step.critical ? (
          <Badge label={t("recipes.steps.critical")} size="sm" variant="warning" />
        ) : null}
      </View>
    </View>
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.steps.itemAccessibilityLabel")}
      style={{
        alignItems: "flex-start",
        flexDirection: "row",
        gap: theme.spacing[3],
        opacity: disabled ? 0.72 : 1,
      }}
    >
      <Badge label={String(position)} size="sm" variant="primary" />
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
            <Tooltip content={t("recipes.actions.editStep")}>
              <IconButton
                accessibilityLabel={t("recipes.actions.editStep")}
                disabled={disabled}
                icon={<Text variant="bodySmall">e</Text>}
                onPress={onEdit}
                size="sm"
                variant="ghost"
              />
            </Tooltip>
          ) : null}
          {onRemove ? (
            <Tooltip content={t("recipes.actions.removeStep")}>
              <IconButton
                accessibilityLabel={t("recipes.actions.removeStep")}
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
