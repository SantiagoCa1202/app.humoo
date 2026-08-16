import type {
  RecipeAllergenRecord,
  RecipeIngredientRecord,
  RecipeRecord,
  RecipeStatus,
  RecipeStepRecord,
  RecipeTagValue,
  RecipeUnitReference,
  RecipeVersionRecord,
  RecipeYieldRecord,
} from "@/features/recipes/types";

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

    return left.id.localeCompare(right.id);
  });
}
