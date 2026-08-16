import type {
  RecipeAllergenRecord,
  RecipeConflictType,
  RecipeIngredientRecord,
  RecipeRecord,
  RecipeStatus,
  RecipeStepRecord,
  RecipeTagValue,
  RecipeVersionChange,
  RecipeUnitReference,
  RecipeVersionRecord,
  RecipeYieldRecord,
} from "@/features/recipes/types";
import type { SemanticStatusTone } from "@/theme/status-config";

export type RecipeDisplayRecord = RecipeRecord;

function getUnitLabel(unit?: RecipeUnitReference | null) {
  return unit?.symbol?.trim() || unit?.name?.trim() || unit?.key?.trim() || null;
}

export function getRecipeStatus(recipe: RecipeDisplayRecord): RecipeStatus | null {
  return recipe.status ?? null;
}

export function getRecipeTagLabel(tag: RecipeTagValue) {
  return typeof tag === "string" ? tag : tag.label;
}

export function getRecipeVersionLabel(
  version?: RecipeVersionRecord | null,
  t?: (key: string, options?: Record<string, unknown>) => string
) {
  if (!version) {
    return null;
  }

  return t ? t("recipes.version.label", { value: version.version }) : String(version.version);
}

export function getRecipeIngredientCount(version?: RecipeVersionRecord | null) {
  return version?.ingredients?.length ?? null;
}

export function getRecipeStepCount(version?: RecipeVersionRecord | null) {
  return version?.steps?.length ?? null;
}

export function getRecipeAllergenCount(version?: RecipeVersionRecord | null) {
  if (typeof version?.allergenCount === "number") {
    return version.allergenCount;
  }

  return version?.allergens?.length ?? null;
}

export function getRecipeAllergenLabel(
  allergen: RecipeAllergenRecord,
  t?: (key: string) => string
) {
  if (allergen.name?.trim()) {
    return allergen.name.trim();
  }

  if (allergen.key?.trim() && t) {
    return t(`recipes.allergens.catalog.${allergen.key.trim()}`);
  }

  return allergen.key?.trim() ?? t?.("recipes.allergens.unknown") ?? null;
}

export function getRecipeAllergenTone(
  allergen?: RecipeAllergenRecord | null
): SemanticStatusTone {
  return allergen?.severity ?? "neutral";
}

export function hasRecipeAllergenRisk(allergens: RecipeAllergenRecord[]) {
  return allergens.some(
    (allergen) =>
      allergen.severity === "warning" ||
      allergen.severity === "danger" ||
      allergen.presence === "cross_contact"
  );
}

export function getRecipeDefaultYield(version?: RecipeVersionRecord | null) {
  if (!version?.yields?.length) {
    return null;
  }

  return version.yields.find((item) => item.isDefault) ?? version.yields[0] ?? null;
}

export function formatRecipeQuantity(value?: number | null, locale?: string) {
  if (value === null || value === undefined) {
    return null;
  }

  return new Intl.NumberFormat(locale, {
    maximumFractionDigits: 2,
  }).format(value);
}

export function getRecipeUnitLabel(unit?: RecipeUnitReference | null) {
  return getUnitLabel(unit);
}

export function formatRecipeMeasurement(
  quantity?: number | null,
  unit?: RecipeUnitReference | null,
  locale?: string
) {
  const formattedQuantity = formatRecipeQuantity(quantity, locale);
  const unitLabel = getUnitLabel(unit);

  if (formattedQuantity && unitLabel) {
    return `${formattedQuantity} ${unitLabel}`;
  }

  return formattedQuantity ?? unitLabel;
}

export function formatRecipeYield(yieldRecord?: RecipeYieldRecord | null, locale?: string) {
  if (!yieldRecord) {
    return null;
  }

  const quantity = formatRecipeQuantity(yieldRecord.quantity, locale);
  const label = yieldRecord.label?.trim();
  const unitLabel = getUnitLabel(yieldRecord.unit);

  if (label) {
    return quantity ? `${quantity} ${label}` : label;
  }

  if (quantity && unitLabel) {
    return `${quantity} ${unitLabel}`;
  }

  return quantity ?? unitLabel;
}

export function formatRecipeCurrency(
  amount?: number | null,
  currency?: string | null,
  locale?: string
) {
  if (amount === null || amount === undefined || !currency?.trim()) {
    return null;
  }

  try {
    return new Intl.NumberFormat(locale, {
      currency: currency.trim().toUpperCase(),
      style: "currency",
    }).format(amount);
  } catch {
    return `${amount} ${currency.trim().toUpperCase()}`;
  }
}

export function parseRecipeQuantity(value?: number | null) {
  if (value === null || value === undefined) {
    return null;
  }

  return value;
}

export function formatRecipePercent(value?: number | null, locale?: string) {
  if (value === null || value === undefined) {
    return null;
  }

  return new Intl.NumberFormat(locale, {
    maximumFractionDigits: 2,
    style: "percent",
  }).format(value / 100);
}

export function formatRecipeDuration(
  minutes?: number | null,
  t?: (key: string, options?: Record<string, unknown>) => string
) {
  if (minutes === null || minutes === undefined) {
    return null;
  }

  if (!t) {
    return String(minutes);
  }

  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  const parts: string[] = [];

  if (hours > 0) {
    parts.push(t("recipes.duration.hours", { count: hours }));
  }

  if (remainingMinutes > 0 || hours === 0) {
    parts.push(t("recipes.duration.minutes", { count: remainingMinutes }));
  }

  return parts.join(" ");
}

export function formatRecipeDateTime(value?: string | null, locale?: string) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  } catch {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  }
}

export function getRecipeSummary(recipe: RecipeDisplayRecord, version?: RecipeVersionRecord | null) {
  return version?.description?.trim() || recipe.description?.trim() || null;
}

export function getRecipeIngredientName(ingredient: RecipeIngredientRecord) {
  return ingredient.ingredientName.trim();
}

export function getRecipeStepPosition(step: RecipeStepRecord, fallbackIndex: number) {
  return step.position ?? fallbackIndex + 1;
}

export function sortRecipeIngredients(ingredients: RecipeIngredientRecord[]) {
  return [...ingredients].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return left.ingredientName.localeCompare(right.ingredientName);
  });
}

export function sortRecipeSteps(steps: RecipeStepRecord[]) {
  return [...steps].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return (left.id ?? left.clientId ?? "").localeCompare(right.id ?? right.clientId ?? "");
  });
}

export function getRecipeScaleFactor(
  baseYield?: RecipeYieldRecord | null,
  targetYield?: RecipeYieldRecord | null
) {
  if (!baseYield || !targetYield) {
    return null;
  }

  if (!baseYield.quantity || !targetYield.quantity) {
    return null;
  }

  if (baseYield.unitId && targetYield.unitId && baseYield.unitId !== targetYield.unitId) {
    return null;
  }

  return targetYield.quantity / baseYield.quantity;
}

export function scaleRecipeIngredients(
  ingredients: RecipeIngredientRecord[],
  scaleFactor?: number | null
) {
  if (!scaleFactor || !Number.isFinite(scaleFactor)) {
    return ingredients.map((ingredient) => ({ ...ingredient }));
  }

  return ingredients.map((ingredient) => ({
    ...ingredient,
    quantity:
      ingredient.quantity === null || ingredient.quantity === undefined
        ? ingredient.quantity
        : ingredient.quantity * scaleFactor,
  }));
}

export function getRecipeCostMissingState(missingCostCount?: number | null, estimated?: boolean) {
  return Boolean((missingCostCount ?? 0) > 0 || estimated);
}

export function buildRecipeVersionComparisonChanges(
  baseVersion?: RecipeVersionRecord | null,
  targetVersion?: RecipeVersionRecord | null,
  t?: (key: string, options?: Record<string, unknown>) => string
): RecipeVersionChange[] {
  if (!baseVersion || !targetVersion || !t) {
    return [];
  }

  const changes: RecipeVersionChange[] = [];

  const pushChange = (id: string, label: string, before?: string | null, after?: string | null) => {
    if ((before ?? null) === (after ?? null)) {
      return;
    }

    changes.push({
      after: after ?? t("recipes.comparison.emptyValue"),
      before: before ?? t("recipes.comparison.emptyValue"),
      id,
      label,
    });
  };

  pushChange(
    "version-name",
    t("recipes.comparison.labels.versionName"),
    baseVersion.name,
    targetVersion.name
  );
  pushChange(
    "yield",
    t("recipes.comparison.labels.yield"),
    formatRecipeYield(getRecipeDefaultYield(baseVersion)),
    formatRecipeYield(getRecipeDefaultYield(targetVersion))
  );
  pushChange(
    "ingredients-count",
    t("recipes.comparison.labels.ingredients"),
    baseVersion.ingredients ? String(baseVersion.ingredients.length) : null,
    targetVersion.ingredients ? String(targetVersion.ingredients.length) : null
  );
  pushChange(
    "steps-count",
    t("recipes.comparison.labels.steps"),
    baseVersion.steps ? String(baseVersion.steps.length) : null,
    targetVersion.steps ? String(targetVersion.steps.length) : null
  );
  pushChange(
    "change-summary",
    t("recipes.comparison.labels.changeSummary"),
    baseVersion.changeSummary?.trim() ?? null,
    targetVersion.changeSummary?.trim() ?? null
  );

  return changes;
}

export function getRecipeConflictDescriptionKey(conflictType?: RecipeConflictType) {
  if (conflictType === "remote_update") {
    return "recipes.conflict.types.remote_update";
  }

  if (conflictType === "stale_data") {
    return "recipes.conflict.types.stale_data";
  }

  if (conflictType === "new_version_created") {
    return "recipes.conflict.types.new_version_created";
  }

  if (conflictType === "locked_version") {
    return "recipes.conflict.types.locked_version";
  }

  return "recipes.conflict.types.version_conflict";
}
