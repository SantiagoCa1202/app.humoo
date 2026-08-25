import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import type {
  MenuItemRecord,
  MenuItemValidationErrors,
  MenuRecipeOption,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuItemEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  errors?: MenuItemValidationErrors;
  onCancel?: () => void;
  onChange: (value: MenuItemRecord) => void;
  onSubmit?: () => void;
  hasEvent?: boolean;
  recipeOptions?: MenuRecipeOption[];
  value: MenuItemRecord;
};

export function MenuItemEditor({
  accessibilityLabel,
  compact = false,
  disabled = false,
  errors,
  onCancel,
  onChange,
  onSubmit,
  hasEvent = false,
  recipeOptions,
  value,
}: MenuItemEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const canShowRecipePicker = Boolean(recipeOptions?.length);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("menus.itemEditor.accessibilityLabel")}
      padding={compact ? "sm" : "md"}
      radius="md"
      variant="muted"
    >
      <View style={{ gap: theme.spacing[3] }}>
        <TextField
          editable={!disabled}
          error={errors?.name}
          label={t("menus.form.fields.itemName.label")}
          onChangeText={(name) => onChange({ ...value, name })}
          placeholder={t("menus.form.fields.itemName.placeholder")}
          required
          value={value.name}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={errors?.description}
          label={t("menus.form.fields.itemDescription.label")}
          onChangeText={(description) => onChange({ ...value, description })}
          placeholder={t("menus.form.fields.itemDescription.placeholder")}
          value={value.description ?? ""}
        />
        {canShowRecipePicker ? (
          <EntityPicker
            disabled={disabled}
            entities={recipeOptions ?? []}
            error={errors?.recipeId}
            label={t("menus.form.fields.recipe.label")}
            onChange={(recipeId) => {
              const selectedRecipe = recipeOptions?.find((recipe) => recipe.value === recipeId);
              onChange({
                ...value,
                recipe: selectedRecipe
                  ? {
                      id: selectedRecipe.value,
                      name: selectedRecipe.label ?? selectedRecipe.name ?? selectedRecipe.value,
                    }
                  : null,
                recipeId,
                recipeVersionId: selectedRecipe?.currentVersionId ?? null,
              });
            }}
            placeholder={t("menus.form.fields.recipe.placeholder")}
            value={value.recipeId ?? undefined}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 180 }}>
            <TextField
              editable={!disabled}
              keyboardType="decimal-pad"
              label={t("menus.form.fields.quantityPerGuest.label")}
              onChangeText={(nextValue) => {
                const normalized = nextValue.replace(/[^0-9.]/g, "");
                onChange({
                  ...value,
                  quantityPerGuest: normalized === "" ? null : Number(normalized),
                });
              }}
              value={value.quantityPerGuest?.toString() ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 180 }}>
            <TextField
              editable={!disabled}
              label={t("menus.form.fields.servingUnit.label")}
              onChangeText={(servingUnit) => onChange({ ...value, servingUnit })}
              placeholder={t("menus.form.fields.servingUnit.placeholder")}
              value={value.servingUnit ?? ""}
            />
          </View>
        </View>
        {hasEvent ? (
          <TextField
            editable={!disabled}
            keyboardType="decimal-pad"
            label={t("menus.form.fields.eventPlannedQuantity.label")}
            onChangeText={(nextValue) => {
              const normalized = nextValue.replace(/[^0-9.]/g, "");
              onChange({
                ...value,
                eventPlannedQuantity: normalized === "" ? null : Number(normalized),
              });
            }}
            placeholder={t("menus.form.fields.eventPlannedQuantity.placeholder")}
            value={value.eventPlannedQuantity?.toString() ?? ""}
          />
        ) : null}
        {value.recipeId ? (
          <Button
            disabled={disabled}
            label={t("menus.actions.removeRecipe")}
            onPress={() => onChange({ ...value, recipe: null, recipeId: null, recipeVersionId: null })}
            size="sm"
            variant="ghost"
          />
        ) : null}
        <TextArea
          autoGrow
          editable={!disabled}
          error={errors?.notes}
          label={t("menus.form.fields.itemNotes.label")}
          onChangeText={(notes) => onChange({ ...value, notes })}
          placeholder={t("menus.form.fields.itemNotes.placeholder")}
          value={value.notes ?? ""}
        />
        {onSubmit || onCancel ? (
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2], justifyContent: "flex-end" }}>
            {onCancel ? (
              <Button
                disabled={disabled}
                label={t("menus.actions.cancel")}
                onPress={onCancel}
                size="sm"
                variant="ghost"
              />
            ) : null}
            {onSubmit ? (
              <Button
                disabled={disabled}
                label={t("menus.actions.saveItem")}
                onPress={onSubmit}
                size="sm"
                variant="primary"
              />
            ) : null}
          </View>
        ) : null}
      </View>
    </BaseCard>
  );
}
