import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { RecipeVersionEditor } from "@/components/patterns/recipe-version-editor";
import { MultiSelect } from "@/components/primitives/multi-select";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  type RecipeAllergenOption,
  createRecipeEditorValues,
  hasRecipeEditorErrors,
  RECIPE_STATUS_VALUES,
  type RecipeEditorMode,
  type RecipeEditorValidationErrors,
  type RecipeEditorValues,
  type RecipeIngredientOption,
  type RecipeRecord,
  type RecipeTagOption,
  type RecipeUnitOption,
  type RecipeVersionRecord,
  validateRecipeEditorValues,
  normalizeRecipeEditorValues,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeEditorFormProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  costCurrencyCode?: string;
  disabled?: boolean;
  ingredientOptions?: RecipeIngredientOption[];
  initialRecipe?: Partial<RecipeRecord>;
  initialVersion?: Partial<RecipeVersionRecord>;
  mode?: RecipeEditorMode;
  onCancel?: () => void;
  onSubmit: (value: RecipeEditorValues) => void | Promise<void>;
  allergenOptions?: RecipeAllergenOption[];
  submitting?: boolean;
  tagOptions?: RecipeTagOption[];
  unitOptions: RecipeUnitOption[];
  validationErrors?: RecipeEditorValidationErrors;
};

function mergeValidationErrors(
  localErrors: RecipeEditorValidationErrors,
  externalErrors?: RecipeEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
    version: {
      ...(localErrors.version ?? {}),
      ...(externalErrors.version ?? {}),
      ingredients: {
        ...(localErrors.version?.ingredients ?? {}),
        ...(externalErrors.version?.ingredients ?? {}),
      },
      steps: {
        ...(localErrors.version?.steps ?? {}),
        ...(externalErrors.version?.steps ?? {}),
      },
      yields: {
        ...(localErrors.version?.yields ?? {}),
        ...(externalErrors.version?.yields ?? {}),
      },
    },
  } satisfies RecipeEditorValidationErrors;
}

export function RecipeEditorForm({
  accessibilityLabel,
  compact = false,
  costCurrencyCode,
  disabled = false,
  ingredientOptions,
  initialRecipe,
  initialVersion,
  mode = "create",
  onCancel,
  onSubmit,
  allergenOptions,
  submitting = false,
  tagOptions,
  unitOptions,
  validationErrors,
}: RecipeEditorFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify({
    initialRecipe: initialRecipe ?? {},
    initialVersion: initialVersion ?? {},
  });
  const defaultValues = useMemo(
    () => createRecipeEditorValues(initialRecipe, initialVersion),
    [initialSignature]
  );
  const [values, setValues] = useState<RecipeEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<RecipeEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const submitLabel =
    mode === "edit" ? t("recipes.actions.saveChanges") : t("recipes.actions.createRecipe");

  const handleSubmit = async () => {
    const normalized = normalizeRecipeEditorValues(values);
    const nextErrors = validateRecipeEditorValues(normalized, t);

    if (hasRecipeEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.form.accessibilityLabel")}
      cancelLabel={t("recipes.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={submitLabel}
      submitting={submitting}
      title={mode === "edit" ? t("recipes.form.editTitle") : t("recipes.form.createTitle")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("recipes.form.fields.name.label")}
          onChangeText={(name) => setValues((current) => ({ ...current, name }))}
          placeholder={t("recipes.form.fields.name.placeholder")}
          required
          value={values.name}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("recipes.form.fields.description.label")}
          onChangeText={(description) => setValues((current) => ({ ...current, description }))}
          placeholder={t("recipes.form.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 180 }}>
            <TextField
              editable={!disabled}
              error={resolvedErrors.recipeCode}
              label={t("recipes.form.fields.recipeCode.label")}
              onChangeText={(recipeCode) => setValues((current) => ({ ...current, recipeCode }))}
              placeholder={t("recipes.form.fields.recipeCode.placeholder")}
              value={values.recipeCode ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 180 }}>
            <TextField
              editable={!disabled}
              error={resolvedErrors.category}
              label={t("recipes.form.fields.category.label")}
              onChangeText={(category) => setValues((current) => ({ ...current, category }))}
              placeholder={t("recipes.form.fields.category.placeholder")}
              value={values.category ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 180 }}>
            <TextField
              editable={!disabled}
              error={resolvedErrors.type}
              label={t("recipes.form.fields.type.label")}
              onChangeText={(type) => setValues((current) => ({ ...current, type }))}
              placeholder={t("recipes.form.fields.type.placeholder")}
              value={values.type ?? ""}
            />
          </View>
        </View>
        <StatusSelect
          disabled={disabled}
          error={resolvedErrors.status}
          label={t("recipes.form.fields.status.label")}
          namespace="recipes"
          onChange={(status) => setValues((current) => ({ ...current, status: status as RecipeEditorValues["status"] }))}
          options={RECIPE_STATUS_VALUES.map((status) => ({ value: status }))}
          value={values.status ?? undefined}
        />
        {tagOptions?.length ? (
          <MultiSelect
            error={resolvedErrors.tags}
            label={t("recipes.form.fields.tags.label")}
            onChange={(nextTags) =>
              setValues((current) => ({
                ...current,
                tags: nextTags.map((tagId) => {
                  const option = tagOptions.find((tagOption) => tagOption.value === tagId);
                  return option ? { id: tagId, label: option.label } : tagId;
                }),
              }))
            }
            options={tagOptions}
            placeholder={t("recipes.form.fields.tags.placeholder")}
            values={(values.tags ?? []).map((tag) => (typeof tag === "string" ? tag : tag.id ?? tag.label))}
          />
        ) : null}
        <RecipeVersionEditor
          allergenOptions={allergenOptions}
          compact={compact}
          costCurrencyCode={costCurrencyCode}
          disabled={disabled}
          errors={resolvedErrors.version}
          ingredientOptions={ingredientOptions}
          onChange={(version) => setValues((current) => ({ ...current, version }))}
          unitOptions={unitOptions}
          value={values.version}
        />
      </View>
    </FormCard>
  );
}
