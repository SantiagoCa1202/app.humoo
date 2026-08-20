import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { QuantityUnitOption } from "@/components/primitives/quantity-input";
import type { SelectOption } from "@/components/primitives/select-base";
import type {
  RecipeAllergenRecord,
  RecipeIngredientRecord,
  RecipeRecord,
  RecipeStatus,
  RecipeStepRecord,
  RecipeTagValue,
  RecipeVersionRecord,
  RecipeVersionStatus,
  RecipeYieldRecord,
} from "@/features/recipes/types";

export type RecipeIngredientValidationErrors = Partial<
  Record<"ingredientName" | "quantity" | "unitId" | "unitCost" | "wastePercentage", string>
>;

export type RecipeStepValidationErrors = Partial<
  Record<"instruction" | "title" | "durationMinutes" | "type", string>
>;

export type RecipeYieldValidationErrors = Partial<
  Record<"label" | "quantity" | "unitId", string>
>;

export type RecipeVersionValidationErrors = {
  changeSummary?: string;
  cookTimeMinutes?: string;
  description?: string;
  ingredients?: Record<string, RecipeIngredientValidationErrors>;
  name?: string;
  prepTimeMinutes?: string;
  restTimeMinutes?: string;
  status?: string;
  steps?: Record<string, RecipeStepValidationErrors>;
  totalTimeMinutes?: string;
  yields?: Record<string, RecipeYieldValidationErrors>;
};

export type RecipeEditorValidationErrors = {
  category?: string;
  description?: string;
  form?: string;
  name?: string;
  recipeCode?: string;
  status?: string;
  tags?: string;
  type?: string;
  version?: RecipeVersionValidationErrors;
};

export type RecipeEditorValues = RecipeRecord & {
  version: RecipeVersionRecord;
};

export type RecipeEditorMode = "create" | "edit";

export const RECIPE_STATUS_VALUES = [
  "draft",
  "active",
  "archived",
] as const satisfies readonly RecipeStatus[];

export const RECIPE_VERSION_STATUS_VALUES = [
  "draft",
  "review",
  "approved",
  "superseded",
  "archived",
] as const satisfies readonly RecipeVersionStatus[];

let draftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function normalizeTagValue(tag: RecipeTagValue) {
  return typeof tag === "string" ? tag : { ...tag };
}

export function createRecipeDraftKey(
  prefix: "ingredient" | "recipe" | "step" | "version" | "yield" = "recipe"
) {
  draftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${draftCounter}`;
}

export function getRecipeIngredientKey(
  ingredient: Pick<RecipeIngredientRecord, "clientId" | "id" | "ingredientName">
) {
  return (
    ingredient.id ??
    ingredient.clientId ??
    ingredient.ingredientName ??
    createRecipeDraftKey("ingredient")
  );
}

export function getRecipeStepKey(step: Pick<RecipeStepRecord, "clientId" | "id" | "instruction">) {
  return step.id ?? step.clientId ?? step.instruction ?? createRecipeDraftKey("step");
}

export function getRecipeYieldKey(yieldRecord: Pick<RecipeYieldRecord, "clientId" | "id" | "label">) {
  return yieldRecord.id ?? yieldRecord.clientId ?? yieldRecord.label ?? createRecipeDraftKey("yield");
}

export function moveItemInArray<T>(items: T[], fromIndex: number, toIndex: number) {
  if (
    fromIndex < 0 ||
    toIndex < 0 ||
    fromIndex >= items.length ||
    toIndex >= items.length ||
    fromIndex === toIndex
  ) {
    return items;
  }

  const nextItems = [...items];
  const [item] = nextItems.splice(fromIndex, 1);
  nextItems.splice(toIndex, 0, item);
  return nextItems;
}

export function sortRecipeIngredientsForEdit(ingredients: RecipeIngredientRecord[]) {
  return [...ingredients].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return left.ingredientName.localeCompare(right.ingredientName);
  });
}

export function sortRecipeStepsForEdit(steps: RecipeStepRecord[]) {
  return [...steps].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return (left.id ?? left.clientId ?? "").localeCompare(right.id ?? right.clientId ?? "");
  });
}

export function normalizeRecipeIngredientsOrder(ingredients: RecipeIngredientRecord[]) {
  return ingredients.map((ingredient, index) => ({
    ...ingredient,
    position: index + 1,
  }));
}

export function normalizeRecipeStepsOrder(steps: RecipeStepRecord[]) {
  return steps.map((step, index) => ({
    ...step,
    position: index + 1,
  }));
}

export function createRecipeIngredientDraft(
  values?: Partial<RecipeIngredientRecord>
): RecipeIngredientRecord {
  return {
    clientId: values?.clientId ?? createRecipeDraftKey("ingredient"),
    componentRecipeId: values?.componentRecipeId ?? null,
    componentRecipeVersionId: values?.componentRecipeVersionId ?? null,
    costCurrency: values?.costCurrency ?? null,
    extendedCost: values?.extendedCost ?? null,
    id: values?.id ?? null,
    ingredientName: values?.ingredientName ?? "",
    inventoryItemId: values?.inventoryItemId ?? null,
    notes: values?.notes ?? null,
    optional: values?.optional ?? false,
    position: values?.position ?? null,
    preparation: values?.preparation ?? null,
    quantity: values?.quantity ?? 0,
    scalable: values?.scalable ?? true,
    unit: values?.unit ?? null,
    unitCost: values?.unitCost ?? null,
    unitId: values?.unitId ?? values?.unit?.id ?? null,
    wastePercentage: values?.wastePercentage ?? null,
    yieldPercentage: values?.yieldPercentage ?? null,
  };
}

export function createRecipeStepDraft(values?: Partial<RecipeStepRecord>): RecipeStepRecord {
  return {
    clientId: values?.clientId ?? createRecipeDraftKey("step"),
    critical: values?.critical ?? false,
    durationMinutes: values?.durationMinutes ?? null,
    id: values?.id ?? null,
    instruction: values?.instruction ?? "",
    notes: values?.notes ?? null,
    position: values?.position ?? null,
    title: values?.title ?? null,
    type: values?.type ?? null,
  };
}

export function createRecipeYieldDraft(values?: Partial<RecipeYieldRecord>): RecipeYieldRecord {
  return {
    clientId: values?.clientId ?? createRecipeDraftKey("yield"),
    factorToBase: values?.factorToBase ?? null,
    id: values?.id ?? null,
    isDefault: values?.isDefault ?? false,
    label: values?.label ?? null,
    quantity: values?.quantity ?? 0,
    unit: values?.unit ?? null,
    unitId: values?.unitId ?? values?.unit?.id ?? null,
  };
}

export function normalizeRecipeYields(values: RecipeYieldRecord[]) {
  return values.map((yieldRecord, index) => ({
    ...yieldRecord,
    isDefault:
      index === values.findIndex((item) => item.isDefault) ||
      (values.findIndex((item) => item.isDefault) === -1 && index === 0),
  }));
}

export function createRecipeVersionDraft(
  values?: Partial<RecipeVersionRecord>
): RecipeVersionRecord {
  return {
    allergenCount: values?.allergenCount ?? null,
    allergens: values?.allergens ?? [],
    approvedAt: values?.approvedAt ?? null,
    approvedBy: values?.approvedBy ?? null,
    category: values?.category ?? null,
    clientId: values?.clientId ?? createRecipeDraftKey("version"),
    changeSummary: values?.changeSummary ?? null,
    cookTimeMinutes: values?.cookTimeMinutes ?? null,
    costCurrency: values?.costCurrency ?? null,
    createdAt: values?.createdAt ?? null,
    createdBy: values?.createdBy ?? null,
    description: values?.description ?? null,
    estimatedCostPerYield: values?.estimatedCostPerYield ?? null,
    estimatedTotalCost: values?.estimatedTotalCost ?? null,
    equipmentRequired: values?.equipmentRequired ?? null,
    id: values?.id ?? null,
    ingredients: normalizeRecipeIngredientsOrder(
      sortRecipeIngredientsForEdit((values?.ingredients ?? []).map((item) => createRecipeIngredientDraft(item)))
    ),
    locked: values?.locked ?? false,
    lockedAt: values?.lockedAt ?? null,
    name: values?.name ?? "",
    prepTimeMinutes: values?.prepTimeMinutes ?? null,
    recipeId: values?.recipeId ?? null,
    restTimeMinutes: values?.restTimeMinutes ?? null,
    revision: values?.revision ?? null,
    shelfLifeHours: values?.shelfLifeHours ?? null,
    source: values?.source ?? "manual",
    status: values?.status ?? "draft",
    steps: normalizeRecipeStepsOrder(
      sortRecipeStepsForEdit((values?.steps ?? []).map((item) => createRecipeStepDraft(item)))
    ),
    totalTimeMinutes: values?.totalTimeMinutes ?? null,
    version: values?.version ?? 1,
    yields: normalizeRecipeYields((values?.yields ?? []).map((item) => createRecipeYieldDraft(item))),
  };
}

export function createRecipeEditorValues(
  recipe?: Partial<RecipeRecord>,
  version?: Partial<RecipeVersionRecord>
): RecipeEditorValues {
  const resolvedRecipeId = recipe?.id ?? createRecipeDraftKey("recipe");

  return {
    category: recipe?.category ?? null,
    createdAt: recipe?.createdAt ?? null,
    createdBy: recipe?.createdBy ?? null,
    currentVersion: recipe?.currentVersion ?? version?.version ?? 1,
    currentVersionId: recipe?.currentVersionId ?? version?.id ?? null,
    description: recipe?.description ?? null,
    id: resolvedRecipeId,
    imageDocumentId: recipe?.imageDocumentId ?? null,
    metadata: recipe?.metadata ?? null,
    name: recipe?.name ?? "",
    recipeCode: recipe?.recipeCode ?? null,
    status: recipe?.status ?? "draft",
    tags: (recipe?.tags ?? []).map((tag) => normalizeTagValue(tag)),
    type: recipe?.type ?? "standard",
    updatedAt: recipe?.updatedAt ?? null,
    updatedBy: recipe?.updatedBy ?? null,
    version: createRecipeVersionDraft({
      ...version,
      name: version?.name ?? recipe?.name ?? "",
      recipeId: version?.recipeId ?? resolvedRecipeId,
      version: version?.version ?? recipe?.currentVersion ?? 1,
    }),
  };
}

export function normalizeRecipeEditorValues(values: RecipeEditorValues): RecipeEditorValues {
  const normalizedIngredients = normalizeRecipeIngredientsOrder(
    sortRecipeIngredientsForEdit(
      (values.version.ingredients ?? []).map((ingredient) => ({
        ...ingredient,
        ingredientName: ingredient.ingredientName.trim(),
        notes: trimOrNull(ingredient.notes),
        preparation: trimOrNull(ingredient.preparation),
        unitCost:
          ingredient.unitCost === null || ingredient.unitCost === undefined
            ? null
            : Number(ingredient.unitCost),
        wastePercentage:
          ingredient.wastePercentage === null || ingredient.wastePercentage === undefined
            ? null
            : Number(ingredient.wastePercentage),
      }))
    )
  );

  const normalizedSteps = normalizeRecipeStepsOrder(
    sortRecipeStepsForEdit(
      (values.version.steps ?? []).map((step) => ({
        ...step,
        instruction: step.instruction.trim(),
        notes: trimOrNull(step.notes),
        title: trimOrNull(step.title),
        type: trimOrNull(step.type),
      }))
    )
  );

  const normalizedYields = normalizeRecipeYields(
    (values.version.yields ?? []).map((yieldRecord) => ({
      ...yieldRecord,
      label: trimOrNull(yieldRecord.label),
    }))
  );

  const normalizedAllergens = (values.version.allergens ?? []).reduce<RecipeAllergenRecord[]>(
    (result, allergen) => {
      if (!allergen.id?.trim()) {
        return result;
      }

      result.push({
        ...allergen,
        id: allergen.id.trim(),
        key: trimOrNull(allergen.key),
        metadata: trimOrNull(allergen.metadata),
        name: trimOrNull(allergen.name),
        source: allergen.source ?? "manual",
      });

      return result;
    },
    []
  );

  return {
    ...values,
    category: trimOrNull(values.category),
    description: trimOrNull(values.description),
    name: values.name.trim(),
    recipeCode: trimOrNull(values.recipeCode),
    tags: (values.tags ?? []).map((tag) => normalizeTagValue(tag)),
    type: trimOrNull(values.type) ?? "standard",
    version: {
      ...values.version,
      changeSummary: trimOrNull(values.version.changeSummary),
      description: trimOrNull(values.version.description),
      allergens: normalizedAllergens,
      ingredients: normalizedIngredients,
      name: values.version.name.trim(),
      recipeId: values.id,
      steps: normalizedSteps,
      yields: normalizedYields,
    },
  };
}

export function validateRecipeEditorValues(
  values: RecipeEditorValues,
  t: (key: string) => string
): RecipeEditorValidationErrors {
  const errors: RecipeEditorValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = t("recipes.form.errors.nameRequired");
  }

  const versionErrors: RecipeVersionValidationErrors = {};

  if (!values.version.name.trim()) {
    versionErrors.name = t("recipes.form.errors.versionNameRequired");
  }

  const ingredientErrors = (values.version.ingredients ?? []).reduce<
    Record<string, RecipeIngredientValidationErrors>
  >((result, ingredient) => {
    const currentErrors: RecipeIngredientValidationErrors = {};

    if (!ingredient.ingredientName.trim()) {
      currentErrors.ingredientName = t("recipes.form.errors.ingredientNameRequired");
    }

    if (ingredient.quantity === null || ingredient.quantity === undefined || ingredient.quantity <= 0) {
      currentErrors.quantity = t("recipes.form.errors.ingredientQuantityRequired");
    }

    if (!ingredient.unitId) {
      currentErrors.unitId = t("recipes.form.errors.unitRequired");
    }

    if (
      ingredient.wastePercentage !== null &&
      ingredient.wastePercentage !== undefined &&
      (ingredient.wastePercentage < 0 || ingredient.wastePercentage > 100)
    ) {
      currentErrors.wastePercentage = t("recipes.form.errors.wastePercentageRange");
    }

    if (
      ingredient.unitCost !== null &&
      ingredient.unitCost !== undefined &&
      ingredient.unitCost < 0
    ) {
      currentErrors.unitCost = t("recipes.form.errors.unitCostNonNegative");
    }

    if (Object.keys(currentErrors).length > 0) {
      result[getRecipeIngredientKey(ingredient)] = currentErrors;
    }

    return result;
  }, {});

  if (Object.keys(ingredientErrors).length > 0) {
    versionErrors.ingredients = ingredientErrors;
  }

  const stepErrors = (values.version.steps ?? []).reduce<Record<string, RecipeStepValidationErrors>>(
    (result, step) => {
      const currentErrors: RecipeStepValidationErrors = {};

      if (!step.instruction.trim()) {
        currentErrors.instruction = t("recipes.form.errors.stepInstructionRequired");
      }

      if (
        step.durationMinutes !== null &&
        step.durationMinutes !== undefined &&
        step.durationMinutes < 0
      ) {
        currentErrors.durationMinutes = t("recipes.form.errors.durationNonNegative");
      }

      if (Object.keys(currentErrors).length > 0) {
        result[getRecipeStepKey(step)] = currentErrors;
      }

      return result;
    },
    {}
  );

  if (Object.keys(stepErrors).length > 0) {
    versionErrors.steps = stepErrors;
  }

  const yieldErrors = (values.version.yields ?? []).reduce<Record<string, RecipeYieldValidationErrors>>(
    (result, yieldRecord) => {
      const currentErrors: RecipeYieldValidationErrors = {};

      if (yieldRecord.quantity <= 0) {
        currentErrors.quantity = t("recipes.form.errors.yieldQuantityRequired");
      }

      if (!yieldRecord.unitId) {
        currentErrors.unitId = t("recipes.form.errors.unitRequired");
      }

      if (Object.keys(currentErrors).length > 0) {
        result[getRecipeYieldKey(yieldRecord)] = currentErrors;
      }

      return result;
    },
    {}
  );

  if (Object.keys(yieldErrors).length > 0) {
    versionErrors.yields = yieldErrors;
  }

  if (Object.keys(versionErrors).length > 0) {
    errors.version = versionErrors;
  }

  return errors;
}

export function hasRecipeEditorErrors(errors?: RecipeEditorValidationErrors | null) {
  if (!errors) {
    return false;
  }

  return Boolean(
    errors.form ||
      errors.category ||
      errors.description ||
      errors.name ||
      errors.recipeCode ||
      errors.status ||
      errors.tags ||
      errors.type ||
      (errors.version &&
        (errors.version.name ||
          errors.version.description ||
          errors.version.changeSummary ||
          errors.version.prepTimeMinutes ||
          errors.version.cookTimeMinutes ||
          errors.version.restTimeMinutes ||
          errors.version.totalTimeMinutes ||
          errors.version.status ||
          (errors.version.ingredients && Object.keys(errors.version.ingredients).length > 0) ||
          (errors.version.steps && Object.keys(errors.version.steps).length > 0) ||
          (errors.version.yields && Object.keys(errors.version.yields).length > 0)))
  );
}

export type RecipeUnitOption = QuantityUnitOption<string>;
export type RecipeIngredientOption = EntityPickerOption<string>;
export type RecipeTagOption = SelectOption<string>;
export type RecipeAllergenOption = SelectOption<string> & {
  key?: string | null;
  metadata?: string | null;
  name?: string | null;
};
