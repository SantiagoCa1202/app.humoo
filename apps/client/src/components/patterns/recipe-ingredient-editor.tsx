import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { NumberField } from "@/components/primitives/number-field";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { TextField } from "@/components/primitives/text-field";
import type {
  RecipeIngredientOption,
  RecipeIngredientRecord,
  RecipeIngredientValidationErrors,
  RecipeUnitOption,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeIngredientEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  costCurrencyCode?: string;
  disabled?: boolean;
  errors?: RecipeIngredientValidationErrors;
  ingredientOptions?: RecipeIngredientOption[];
  onCancel?: () => void;
  onChange: (value: RecipeIngredientRecord) => void;
  onSubmit?: () => void;
  unitOptions: RecipeUnitOption[];
  value: RecipeIngredientRecord;
};

export function RecipeIngredientEditor({
  accessibilityLabel,
  compact = false,
  costCurrencyCode,
  disabled = false,
  errors,
  ingredientOptions,
  onCancel,
  onChange,
  onSubmit,
  unitOptions,
  value,
}: RecipeIngredientEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.form.ingredientEditor.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="muted"
    >
      <CardHeader title={t("recipes.form.ingredientEditor.title")} />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {ingredientOptions?.length ? (
            <EntityPicker
              disabled={disabled}
              entities={ingredientOptions}
              label={t("recipes.form.fields.ingredientReference.label")}
              onChange={(inventoryItemId) => {
                const selectedOption = ingredientOptions.find((option) => option.value === inventoryItemId);
                onChange({
                  ...value,
                  ingredientName:
                    value.ingredientName ||
                    selectedOption?.label ||
                    selectedOption?.name ||
                    "",
                  inventoryItemId,
                });
              }}
              placeholder={t("recipes.form.fields.ingredientReference.placeholder")}
              value={value.inventoryItemId ?? undefined}
            />
          ) : null}
          <TextField
            editable={!disabled}
            error={errors?.ingredientName}
            label={t("recipes.form.fields.ingredientName.label")}
            onChangeText={(ingredientName) => onChange({ ...value, ingredientName })}
            placeholder={t("recipes.form.fields.ingredientName.placeholder")}
            required
            value={value.ingredientName}
          />
          <QuantityInput
            disabled={disabled}
            error={errors?.quantity ?? errors?.unitId}
            label={t("recipes.form.fields.quantity.label")}
            onChange={(quantity) => onChange({ ...value, quantity })}
            onUnitChange={(unitId) => {
              const unit = unitOptions.find((option) => option.value === unitId);
              onChange({
                ...value,
                unit: unit ? { id: unitId, symbol: unit.label } : null,
                unitId,
              });
            }}
            step={0.01}
            unit={value.unitId ?? undefined}
            units={unitOptions}
            value={value.quantity ?? 0}
          />
          <TextField
            editable={!disabled}
            label={t("recipes.form.fields.preparation.label")}
            onChangeText={(preparation) => onChange({ ...value, preparation })}
            placeholder={t("recipes.form.fields.preparation.placeholder")}
            value={value.preparation ?? ""}
          />
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled}
                error={errors?.wastePercentage}
                label={t("recipes.form.fields.wastePercentage.label")}
                max={100}
                min={0}
                onChange={(wastePercentage) => onChange({ ...value, wastePercentage })}
                step={0.01}
                suffix="%"
                value={value.wastePercentage ?? 0}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled}
                error={errors?.unitCost}
                label={t("recipes.form.fields.unitCost.label")}
                min={0}
                onChange={(unitCost) => onChange({ ...value, unitCost })}
                step={0.01}
                suffix={costCurrencyCode}
                value={value.unitCost ?? 0}
              />
            </View>
          </View>
          {onCancel || onSubmit ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2], justifyContent: "flex-end" }}>
              {onCancel ? (
                <Button
                  disabled={disabled}
                  label={t("recipes.actions.cancel")}
                  onPress={onCancel}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {onSubmit ? (
                <Button
                  disabled={disabled}
                  label={t("recipes.actions.saveIngredient")}
                  onPress={onSubmit}
                  size="sm"
                  variant="primary"
                />
              ) : null}
            </View>
          ) : null}
        </View>
      </CardContent>
    </BaseCard>
  );
}
