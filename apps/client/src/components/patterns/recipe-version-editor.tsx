import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { IconButton } from "@/components/primitives/icon-button";
import { MultiSelect } from "@/components/primitives/multi-select";
import { NumberField } from "@/components/primitives/number-field";
import { Select } from "@/components/primitives/select";
import { Text } from "@/components/primitives/text";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import { RecipeIngredientEditor } from "@/components/patterns/recipe-ingredient-editor";
import { RecipeIngredientRow } from "@/components/patterns/recipe-ingredient-row";
import { RecipeStepEditor } from "@/components/patterns/recipe-step-editor";
import { RecipeStepItem } from "@/components/patterns/recipe-step-item";
import { RecipeYieldCard } from "@/components/patterns/recipe-yield-card";
import { RecipeYieldEditor } from "@/components/patterns/recipe-yield-editor";
import {
  type RecipeAllergenOption,
  createRecipeIngredientDraft,
  createRecipeStepDraft,
  createRecipeYieldDraft,
  getRecipeIngredientKey,
  getRecipeStepKey,
  getRecipeYieldKey,
  moveItemInArray,
  normalizeRecipeIngredientsOrder,
  normalizeRecipeStepsOrder,
  normalizeRecipeYields,
  RECIPE_VERSION_STATUS_VALUES,
  sortRecipeIngredientsForEdit,
  sortRecipeStepsForEdit,
  type RecipeIngredientOption,
  type RecipeUnitOption,
  type RecipeVersionRecord,
  type RecipeVersionValidationErrors,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeVersionEditorProps = {
  accessibilityLabel?: string;
  allergenOptions?: RecipeAllergenOption[];
  compact?: boolean;
  costCurrencyCode?: string;
  disabled?: boolean;
  errors?: RecipeVersionValidationErrors;
  ingredientOptions?: RecipeIngredientOption[];
  onChange: (value: RecipeVersionRecord) => void;
  readonly?: boolean;
  unitOptions: RecipeUnitOption[];
  value: RecipeVersionRecord;
};

export function RecipeVersionEditor({
  accessibilityLabel,
  allergenOptions,
  compact = false,
  costCurrencyCode,
  disabled = false,
  errors,
  ingredientOptions,
  onChange,
  readonly = false,
  unitOptions,
  value,
}: RecipeVersionEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [editingIngredientKey, setEditingIngredientKey] = useState<string | null>(null);
  const [editingStepKey, setEditingStepKey] = useState<string | null>(null);
  const [editingYieldKey, setEditingYieldKey] = useState<string | null>(null);
  const orderedIngredients = useMemo(
    () => sortRecipeIngredientsForEdit(value.ingredients ?? []),
    [value.ingredients]
  );
  const orderedSteps = useMemo(() => sortRecipeStepsForEdit(value.steps ?? []), [value.steps]);
  const yields = value.yields ?? [];
  const allergens = value.allergens ?? [];
  const allergenOptionsById = useMemo(
    () =>
      new Map(
        (allergenOptions ?? []).map((option) => [
          option.value,
          option,
        ])
      ),
    [allergenOptions]
  );

  const updateVersion = (nextVersion: Partial<RecipeVersionRecord>) =>
    onChange({ ...value, ...nextVersion });

  const updateIngredients = (ingredients: RecipeVersionRecord["ingredients"]) =>
    updateVersion({ ingredients: normalizeRecipeIngredientsOrder(ingredients ?? []) });

  const updateSteps = (steps: RecipeVersionRecord["steps"]) =>
    updateVersion({ steps: normalizeRecipeStepsOrder(steps ?? []) });

  const updateYields = (nextYields: RecipeVersionRecord["yields"]) =>
    updateVersion({ yields: normalizeRecipeYields(nextYields ?? []) });

  const updateAllergens = (nextAllergenIds: string[]) =>
    updateVersion({
      allergens: nextAllergenIds.map((allergenId) => {
        const existing = allergens.find((allergen) => allergen.id === allergenId);

        if (existing) {
          return existing;
        }

        const option = allergenOptionsById.get(allergenId);

        return {
          id: allergenId,
          key: option?.key ?? null,
          metadata: option?.metadata ?? null,
          name: option?.name ?? option?.label ?? null,
          presence: null,
          severity: null,
          source: "manual" as const,
        };
      }),
    });

  const presenceOptions = [
    {
      label: t("recipes.allergens.presence.contains"),
      value: "contains",
    },
    {
      label: t("recipes.allergens.presence.mayContain"),
      value: "may_contain",
    },
    {
      label: t("recipes.allergens.presence.crossContact"),
      value: "cross_contact",
    },
  ] as const;

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.form.versionEditor.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="default"
    >
      <CardHeader
        subtitle={t("recipes.form.versionEditor.subtitle")}
        title={t("recipes.form.versionEditor.title")}
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[4] }}>
          <TextField
            editable={!disabled && !readonly}
            error={errors?.name}
            label={t("recipes.form.fields.versionName.label")}
            onChangeText={(name) => updateVersion({ name })}
            placeholder={t("recipes.form.fields.versionName.placeholder")}
            required
            value={value.name}
          />
          <TextArea
            autoGrow
            editable={!disabled && !readonly}
            error={errors?.description}
            label={t("recipes.form.fields.versionDescription.label")}
            onChangeText={(description) => updateVersion({ description })}
            placeholder={t("recipes.form.fields.versionDescription.placeholder")}
            value={value.description ?? ""}
          />
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
            <View style={{ flex: 1, minWidth: 180 }}>
              <Select
                disabled={disabled || readonly}
                error={errors?.status}
                label={t("recipes.form.fields.versionStatus.label")}
                onChange={(status) => updateVersion({ status })}
                options={RECIPE_VERSION_STATUS_VALUES.map((status) => ({
                  label: t(`recipes.versionStatus.${status}`),
                  value: status,
                }))}
                placeholder={t("recipes.form.fields.versionStatus.placeholder")}
                value={value.status ?? undefined}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled || readonly}
                error={errors?.prepTimeMinutes}
                label={t("recipes.form.fields.prepTimeMinutes.label")}
                min={0}
                onChange={(prepTimeMinutes) => updateVersion({ prepTimeMinutes })}
                suffix={t("recipes.duration.minutes_other", { count: 0 })}
                value={value.prepTimeMinutes ?? 0}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled || readonly}
                error={errors?.cookTimeMinutes}
                label={t("recipes.form.fields.cookTimeMinutes.label")}
                min={0}
                onChange={(cookTimeMinutes) => updateVersion({ cookTimeMinutes })}
                suffix={t("recipes.duration.minutes_other", { count: 0 })}
                value={value.cookTimeMinutes ?? 0}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled || readonly}
                error={errors?.restTimeMinutes}
                label={t("recipes.form.fields.restTimeMinutes.label")}
                min={0}
                onChange={(restTimeMinutes) => updateVersion({ restTimeMinutes })}
                suffix={t("recipes.duration.minutes_other", { count: 0 })}
                value={value.restTimeMinutes ?? 0}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled || readonly}
                error={errors?.totalTimeMinutes}
                label={t("recipes.form.fields.totalTimeMinutes.label")}
                min={0}
                onChange={(totalTimeMinutes) => updateVersion({ totalTimeMinutes })}
                suffix={t("recipes.duration.minutes_other", { count: 0 })}
                value={value.totalTimeMinutes ?? 0}
              />
            </View>
          </View>
          <TextArea
            autoGrow
            editable={!disabled && !readonly}
            error={errors?.changeSummary}
            label={t("recipes.form.fields.changeSummary.label")}
            onChangeText={(changeSummary) => updateVersion({ changeSummary })}
            placeholder={t("recipes.form.fields.changeSummary.placeholder")}
            value={value.changeSummary ?? ""}
          />

          {allergenOptions?.length ? (
            <View style={{ gap: theme.spacing[3] }}>
              <Text variant="h4">{t("recipes.allergens.title")}</Text>
              <MultiSelect
                disabled={disabled || readonly}
                label={t("recipes.form.fields.allergens.label")}
                onChange={updateAllergens}
                options={allergenOptions}
                placeholder={t("recipes.form.fields.allergens.placeholder")}
                values={allergens.map((allergen) => allergen.id)}
              />
              {allergens.map((allergen) => {
                const option = allergenOptionsById.get(allergen.id);
                const allergenLabel =
                  allergen.name?.trim() ||
                  option?.name?.trim() ||
                  option?.label ||
                  allergen.key?.trim() ||
                  allergen.id;

                return (
                  <View
                    key={allergen.id}
                    style={{
                      alignItems: "center",
                      flexDirection: "row",
                      flexWrap: "wrap",
                      gap: theme.spacing[3],
                    }}
                  >
                    <View style={{ flex: 1, minWidth: 180 }}>
                      <Text variant="body">{allergenLabel}</Text>
                    </View>
                    <View style={{ flex: 1, minWidth: 200 }}>
                      <Select
                        disabled={disabled || readonly}
                        label={t("recipes.form.fields.allergenPresence.label")}
                        onChange={(presence) =>
                          updateVersion({
                            allergens: allergens.map((current) =>
                              current.id === allergen.id
                                ? { ...current, presence: presence as typeof current.presence, source: current.source ?? "manual" }
                                : current
                            ),
                          })
                        }
                        options={presenceOptions.map((presence) => ({
                          label: presence.label,
                          value: presence.value,
                        }))}
                        placeholder={t("recipes.form.fields.allergenPresence.placeholder")}
                        value={allergen.presence ?? undefined}
                      />
                    </View>
                    {!readonly ? (
                      <IconButton
                        accessibilityLabel={t("recipes.actions.removeAllergen")}
                        disabled={disabled}
                        icon={
                          <Text tone="danger" variant="bodySmall">
                            x
                          </Text>
                        }
                        onPress={() =>
                          updateAllergens(
                            allergens
                              .filter((current) => current.id !== allergen.id)
                              .map((current) => current.id)
                          )
                        }
                        size="sm"
                        variant="ghost"
                      />
                    ) : null}
                  </View>
                );
              })}
            </View>
          ) : null}

          <View style={{ gap: theme.spacing[3] }}>
            <Text variant="h4">{t("recipes.ingredients.title")}</Text>
            {orderedIngredients.map((ingredient, index) => {
              const ingredientKey = getRecipeIngredientKey(ingredient);
              const isEditing = editingIngredientKey === ingredientKey;

              return (
                <View key={ingredientKey} style={{ gap: theme.spacing[3] }}>
                  <RecipeIngredientRow
                    compact={compact}
                    disabled={disabled}
                    editable={!readonly}
                    ingredient={ingredient}
                    onEdit={!readonly ? () => setEditingIngredientKey(isEditing ? null : ingredientKey) : undefined}
                    onRemove={!readonly ? () => {
                      updateIngredients(
                        orderedIngredients.filter((current) => getRecipeIngredientKey(current) !== ingredientKey)
                      );
                    } : undefined}
                  />
                  {!readonly ? (
                    <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
                      <IconButton
                        accessibilityLabel={t("recipes.actions.moveUp")}
                        disabled={disabled || index === 0}
                        icon={<Text variant="bodySmall">^</Text>}
                        onPress={() =>
                          updateIngredients(moveItemInArray(orderedIngredients, index, index - 1))
                        }
                        size="sm"
                        variant="ghost"
                      />
                      <IconButton
                        accessibilityLabel={t("recipes.actions.moveDown")}
                        disabled={disabled || index === orderedIngredients.length - 1}
                        icon={<Text variant="bodySmall">v</Text>}
                        onPress={() =>
                          updateIngredients(moveItemInArray(orderedIngredients, index, index + 1))
                        }
                        size="sm"
                        variant="ghost"
                      />
                    </View>
                  ) : null}
                  {isEditing ? (
                    <RecipeIngredientEditor
                      compact={compact}
                      costCurrencyCode={costCurrencyCode}
                      disabled={disabled}
                      errors={errors?.ingredients?.[ingredientKey]}
                      ingredientOptions={ingredientOptions}
                      onCancel={() => setEditingIngredientKey(null)}
                      onChange={(nextIngredient) =>
                        updateIngredients(
                          orderedIngredients.map((current) =>
                            getRecipeIngredientKey(current) === ingredientKey ? nextIngredient : current
                          )
                        )
                      }
                      onSubmit={() => setEditingIngredientKey(null)}
                      unitOptions={unitOptions}
                      value={ingredient}
                    />
                  ) : null}
                  {index < orderedIngredients.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              );
            })}
            {!readonly ? (
              <Button
                disabled={disabled}
                label={t("recipes.actions.addIngredient")}
                onPress={() => {
                  const nextIngredient = createRecipeIngredientDraft({
                    costCurrency: costCurrencyCode ?? null,
                    position: orderedIngredients.length + 1,
                  });
                  updateIngredients([...(value.ingredients ?? []), nextIngredient]);
                  setEditingIngredientKey(getRecipeIngredientKey(nextIngredient));
                }}
                size="sm"
                variant="secondary"
              />
            ) : null}
          </View>

          <View style={{ gap: theme.spacing[3] }}>
            <Text variant="h4">{t("recipes.steps.title")}</Text>
            {orderedSteps.map((step, index) => {
              const stepKey = getRecipeStepKey(step);
              const isEditing = editingStepKey === stepKey;

              return (
                <View key={stepKey} style={{ gap: theme.spacing[3] }}>
                  <RecipeStepItem
                    compact={compact}
                    disabled={disabled}
                    editable={!readonly}
                    index={index}
                    onEdit={!readonly ? () => setEditingStepKey(isEditing ? null : stepKey) : undefined}
                    onRemove={!readonly ? () => {
                      updateSteps(
                        orderedSteps.filter((current) => getRecipeStepKey(current) !== stepKey)
                      );
                    } : undefined}
                    step={step}
                  />
                  {!readonly ? (
                    <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
                      <IconButton
                        accessibilityLabel={t("recipes.actions.moveUp")}
                        disabled={disabled || index === 0}
                        icon={<Text variant="bodySmall">^</Text>}
                        onPress={() => updateSteps(moveItemInArray(orderedSteps, index, index - 1))}
                        size="sm"
                        variant="ghost"
                      />
                      <IconButton
                        accessibilityLabel={t("recipes.actions.moveDown")}
                        disabled={disabled || index === orderedSteps.length - 1}
                        icon={<Text variant="bodySmall">v</Text>}
                        onPress={() => updateSteps(moveItemInArray(orderedSteps, index, index + 1))}
                        size="sm"
                        variant="ghost"
                      />
                    </View>
                  ) : null}
                  {isEditing ? (
                    <RecipeStepEditor
                      compact={compact}
                      disabled={disabled}
                      errors={errors?.steps?.[stepKey]}
                      onCancel={() => setEditingStepKey(null)}
                      onChange={(nextStep) =>
                        updateSteps(
                          orderedSteps.map((current) =>
                            getRecipeStepKey(current) === stepKey ? nextStep : current
                          )
                        )
                      }
                      onSubmit={() => setEditingStepKey(null)}
                      position={index + 1}
                      value={step}
                    />
                  ) : null}
                  {index < orderedSteps.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              );
            })}
            {!readonly ? (
              <Button
                disabled={disabled}
                label={t("recipes.actions.addStep")}
                onPress={() => {
                  const nextStep = createRecipeStepDraft({
                    position: orderedSteps.length + 1,
                  });
                  updateSteps([...(value.steps ?? []), nextStep]);
                  setEditingStepKey(getRecipeStepKey(nextStep));
                }}
                size="sm"
                variant="secondary"
              />
            ) : null}
          </View>

          <View style={{ gap: theme.spacing[3] }}>
            <Text variant="h4">{t("recipes.yields.title")}</Text>
            {yields.map((yieldRecord, index) => {
              const yieldKey = getRecipeYieldKey(yieldRecord);
              const isEditing = editingYieldKey === yieldKey;

              return (
                <View key={yieldKey} style={{ gap: theme.spacing[3] }}>
                  <RecipeYieldCard
                    compact={compact}
                    onPress={!readonly ? () => setEditingYieldKey(isEditing ? null : yieldKey) : undefined}
                    primary={Boolean(yieldRecord.isDefault)}
                    yieldRecord={yieldRecord}
                  />
                  {!readonly ? (
                    <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
                      <IconButton
                        accessibilityLabel={t("recipes.actions.editYield")}
                        disabled={disabled}
                        icon={<Text variant="bodySmall">e</Text>}
                        onPress={() => setEditingYieldKey(isEditing ? null : yieldKey)}
                        size="sm"
                        variant="ghost"
                      />
                      <IconButton
                        accessibilityLabel={t("recipes.actions.removeYield")}
                        disabled={disabled}
                        icon={<Text tone="danger" variant="bodySmall">x</Text>}
                        onPress={() =>
                          updateYields(yields.filter((current) => getRecipeYieldKey(current) !== yieldKey))
                        }
                        size="sm"
                        variant="ghost"
                      />
                    </View>
                  ) : null}
                  {isEditing ? (
                    <RecipeYieldEditor
                      allowPrimary
                      compact={compact}
                      disabled={disabled}
                      errors={errors?.yields?.[yieldKey]}
                      onCancel={() => setEditingYieldKey(null)}
                      onChange={(nextYield) =>
                        updateYields(
                          yields.map((current) =>
                            getRecipeYieldKey(current) === yieldKey ? nextYield : current
                          )
                        )
                      }
                      onSubmit={() => setEditingYieldKey(null)}
                      unitOptions={unitOptions}
                      value={yieldRecord}
                    />
                  ) : null}
                  {index < yields.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              );
            })}
            {!readonly ? (
              <Button
                disabled={disabled}
                label={t("recipes.actions.addYield")}
                onPress={() => {
                  const nextYield = createRecipeYieldDraft({
                    isDefault: yields.length === 0,
                  });
                  updateYields([...(value.yields ?? []), nextYield]);
                  setEditingYieldKey(getRecipeYieldKey(nextYield));
                }}
                size="sm"
                variant="secondary"
              />
            ) : null}
          </View>
        </View>
      </CardContent>
    </BaseCard>
  );
}
