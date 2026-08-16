import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Checkbox } from "@/components/primitives/checkbox";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { TextField } from "@/components/primitives/text-field";
import type {
  RecipeUnitOption,
  RecipeYieldRecord,
  RecipeYieldValidationErrors,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeYieldEditorProps = {
  accessibilityLabel?: string;
  allowPrimary?: boolean;
  compact?: boolean;
  disabled?: boolean;
  errors?: RecipeYieldValidationErrors;
  onCancel?: () => void;
  onChange: (value: RecipeYieldRecord) => void;
  onSubmit?: () => void;
  unitOptions: RecipeUnitOption[];
  value: RecipeYieldRecord;
};

export function RecipeYieldEditor({
  accessibilityLabel,
  allowPrimary = true,
  compact = false,
  disabled = false,
  errors,
  onCancel,
  onChange,
  onSubmit,
  unitOptions,
  value,
}: RecipeYieldEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.form.yieldEditor.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="muted"
    >
      <CardHeader title={t("recipes.form.yieldEditor.title")} />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          <TextField
            editable={!disabled}
            error={errors?.label}
            label={t("recipes.form.fields.yieldLabel.label")}
            onChangeText={(label) => onChange({ ...value, label })}
            placeholder={t("recipes.form.fields.yieldLabel.placeholder")}
            value={value.label ?? ""}
          />
          <QuantityInput
            disabled={disabled}
            error={errors?.quantity ?? errors?.unitId}
            label={t("recipes.form.fields.yieldQuantity.label")}
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
          {allowPrimary ? (
            <Checkbox
              checked={Boolean(value.isDefault)}
              disabled={disabled}
              label={t("recipes.form.fields.isDefaultYield.label")}
              onChange={(isDefault) => onChange({ ...value, isDefault })}
            />
          ) : null}
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
                  label={t("recipes.actions.saveYield")}
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
