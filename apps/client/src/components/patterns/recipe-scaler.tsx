import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { SummaryCard } from "@/components/patterns/summary-card";
import { RecipeIngredientsList } from "@/components/patterns/recipe-ingredients-list";
import { RecipeYieldCard } from "@/components/patterns/recipe-yield-card";
import { Button } from "@/components/primitives/button";
import { QuantityInput } from "@/components/primitives/quantity-input";
import {
  formatRecipeMeasurement,
  formatRecipeQuantity,
  getRecipeScaleFactor,
  scaleRecipeIngredients,
  type RecipeDisplayRecord,
  type RecipeIngredientRecord,
  type RecipeUnitOption,
  type RecipeVersionRecord,
  type RecipeYieldRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeScalerProps = {
  accessibilityLabel?: string;
  baseYield: RecipeYieldRecord;
  compact?: boolean;
  disabled?: boolean;
  loading?: boolean;
  onApply?: (value: {
    scaleFactor: number | null;
    scaledIngredients: RecipeIngredientRecord[];
    targetYield: RecipeYieldRecord;
  }) => void | Promise<void>;
  onTargetYieldChange?: (value: RecipeYieldRecord) => void;
  recipe: RecipeDisplayRecord;
  scaledIngredients?: RecipeIngredientRecord[];
  scaleFactor?: number | null;
  targetYield: RecipeYieldRecord;
  unitOptions?: RecipeUnitOption[];
  version: RecipeVersionRecord;
};

export function RecipeScaler({
  accessibilityLabel,
  baseYield,
  compact = false,
  disabled = false,
  loading = false,
  onApply,
  onTargetYieldChange,
  recipe,
  scaledIngredients,
  scaleFactor,
  targetYield,
  unitOptions,
  version,
}: RecipeScalerProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const [draftTargetYield, setDraftTargetYield] = useState<RecipeYieldRecord>(targetYield);

  useEffect(() => {
    setDraftTargetYield(targetYield);
  }, [targetYield]);

  const resolvedScaleFactor =
    scaleFactor ?? getRecipeScaleFactor(baseYield, draftTargetYield);
  const resolvedScaledIngredients = useMemo(
    () =>
      scaledIngredients ??
      scaleRecipeIngredients(version.ingredients ?? [], resolvedScaleFactor),
    [resolvedScaleFactor, scaledIngredients, version.ingredients]
  );
  const hasUnitOptions = Boolean(unitOptions?.length);
  const quantityLabel = formatRecipeMeasurement(
    draftTargetYield.quantity,
    draftTargetYield.unit,
    i18n.language
  );

  const updateTargetYield = (nextValue: RecipeYieldRecord) => {
    setDraftTargetYield(nextValue);
    onTargetYieldChange?.(nextValue);
  };

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.scaler.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <SummaryCard
        metrics={[
          {
            label: t("recipes.scaler.baseYield"),
            value:
              formatRecipeMeasurement(baseYield.quantity, baseYield.unit, i18n.language) ??
              t("recipes.version.emptyValue"),
          },
          {
            label: t("recipes.scaler.targetYield"),
            value: quantityLabel ?? t("recipes.version.emptyValue"),
          },
          {
            label: t("recipes.scaler.scaleFactor"),
            value:
              resolvedScaleFactor === null
                ? t("recipes.scaler.invalidScale")
                : formatRecipeQuantity(resolvedScaleFactor, i18n.language) ??
                  t("recipes.scaler.invalidScale"),
          },
        ]}
        subtitle={recipe.name}
        title={t("recipes.scaler.title")}
        variant="elevated"
      />
      <View style={{ gap: theme.spacing[3] }}>
        {hasUnitOptions ? (
          <QuantityInput
            disabled={disabled || loading}
            error={
              resolvedScaleFactor === null ? t("recipes.scaler.errors.incompatibleUnit") : undefined
            }
            label={t("recipes.scaler.targetYield")}
            min={0.01}
            onChange={(quantity) => updateTargetYield({ ...draftTargetYield, quantity })}
            onUnitChange={(unitId) => {
              const unit = unitOptions?.find((option) => option.value === unitId);
              updateTargetYield({
                ...draftTargetYield,
                unit: unit ? { id: unitId, symbol: unit.label } : null,
                unitId,
              });
            }}
            step={0.01}
            unit={draftTargetYield.unitId ?? undefined}
            units={unitOptions ?? []}
            value={draftTargetYield.quantity}
          />
        ) : (
          <RecipeYieldCard compact={compact} primary yieldRecord={draftTargetYield} />
        )}
        <RecipeYieldCard compact={compact} primary yieldRecord={baseYield} />
      </View>
      <RecipeIngredientsList
        accessibilityLabel={t("recipes.scaler.scaledIngredientsAccessibilityLabel")}
        compact={compact}
        ingredients={resolvedScaledIngredients}
        loading={loading}
      />
      {onApply ? (
        <Button
          disabled={disabled || loading || resolvedScaleFactor === null}
          label={t("recipes.scaler.apply")}
          onPress={() =>
            onApply({
              scaleFactor: resolvedScaleFactor,
              scaledIngredients: resolvedScaledIngredients,
              targetYield: draftTargetYield,
            })
          }
          size="sm"
          variant="primary"
        />
      ) : null}
    </View>
  );
}
