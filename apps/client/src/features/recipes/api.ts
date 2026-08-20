import { apiRequest } from "@/api/client";
import type { RecipeEditorValues } from "@/features/recipes/forms";
import type {
  RecipeAllergenRecord,
  RecipeCatalogRecord,
  RecipeComparisonRecord,
  RecipeDetailRecord,
  RecipeRecord,
  RecipesCursorPage,
  RecipeTagValue,
  RecipeUnitReference,
  RecipeVersionChange,
  RecipeVersionRecord,
} from "@/features/recipes/types";

type ApiUserReference = {
  id?: string | null;
  name?: string | null;
};

type ApiUnit = {
  decimal_places?: number | null;
  dimension?: string | null;
  id: string;
  key?: string | null;
  name?: string | null;
  symbol?: string | null;
};

type ApiTag = {
  active?: boolean | null;
  description?: string | null;
  id: string;
  key?: string | null;
  name?: string | null;
  workspace_id?: string | null;
};

type ApiAllergen = {
  category?: string | null;
  description?: string | null;
  id: string;
  key?: string | null;
  name?: string | null;
  presence?: "contains" | "may_contain" | "cross_contact" | null;
  severity?: RecipeAllergenRecord["severity"] | null;
  source?: "manual" | "ingredient" | "ai" | null;
};

type ApiIngredient = {
  component_recipe_id?: string | null;
  component_recipe_version_id?: string | null;
  cost_currency?: string | null;
  extended_cost?: string | number | null;
  id: string | null;
  ingredient_name: string;
  inventory_item_id?: string | null;
  notes?: string | null;
  optional?: boolean | null;
  position?: number | null;
  preparation?: string | null;
  quantity?: string | number | null;
  scalable?: boolean | null;
  unit?: ApiUnit | null;
  unit_cost?: string | number | null;
  unit_id?: string | null;
  waste_percentage?: string | number | null;
  yield_percentage?: string | number | null;
};

type ApiStep = {
  critical?: boolean | null;
  duration_minutes?: number | null;
  id: string | null;
  instruction: string;
  notes?: string | null;
  position?: number | null;
  title?: string | null;
  type?: string | null;
};

type ApiYield = {
  factor_to_base?: string | number | null;
  id: string | null;
  is_default?: boolean | null;
  label?: string | null;
  quantity: string | number;
  unit?: ApiUnit | null;
  unit_id?: string | null;
};

type ApiVersion = {
  allergen_count?: number | null;
  allergens?: ApiAllergen[] | null;
  approved_at?: string | null;
  approved_by?: ApiUserReference | null;
  category?: string | null;
  change_summary?: string | null;
  cook_time_minutes?: number | null;
  cost_currency?: string | null;
  created_at?: string | null;
  created_by?: ApiUserReference | null;
  description?: string | null;
  estimated_cost_per_yield?: string | number | null;
  estimated_total_cost?: string | number | null;
  equipment_required?: string | null;
  id: string | null;
  ingredients?: ApiIngredient[] | null;
  locked?: boolean | null;
  locked_at?: string | null;
  name: string;
  prep_time_minutes?: number | null;
  recipe_id?: string | null;
  rest_time_minutes?: number | null;
  revision?: number | null;
  source?: "manual" | "duplicated" | "import" | "ai" | null;
  status?: RecipeVersionRecord["status"] | null;
  steps?: ApiStep[] | null;
  total_time_minutes?: number | null;
  version: number;
  yields?: ApiYield[] | null;
};

type ApiRecipe = {
  category?: string | null;
  created_at?: string | null;
  created_by?: ApiUserReference | null;
  current_version?: number | null;
  current_version_id?: string | null;
  current_version_record?: ApiVersion | null;
  description?: string | null;
  id: string;
  image_document_id?: string | null;
  metadata?: Record<string, unknown> | null;
  name: string;
  recipe_code?: string | null;
  status?: RecipeRecord["status"] | null;
  tags?: ApiTag[] | null;
  type?: string | null;
  updated_at?: string | null;
  updated_by?: ApiUserReference | null;
};

type ApiCursorResponse = {
  data: ApiRecipe[];
  meta?: { catalog?: ApiCatalog | null } | null;
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

type ApiCatalog = {
  allergens: ApiAllergen[];
  tags: ApiTag[];
  units: ApiUnit[];
};

type ApiVersionChange = {
  after?: string | null;
  before?: string | null;
  id?: string | null;
  label?: string | null;
  severity?: RecipeVersionChange["severity"] | null;
};

function toNumber(value?: string | number | null) {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function mapUser(user?: ApiUserReference | null) {
  if (!user) {
    return null;
  }

  return {
    id: user.id ?? undefined,
    name: user.name ?? null,
  };
}

function mapUnit(unit?: ApiUnit | null): RecipeUnitReference | null {
  if (!unit) {
    return null;
  }

  return {
    id: unit.id,
    key: unit.key ?? null,
    name: unit.name ?? null,
    symbol: unit.symbol ?? null,
  };
}

function mapAllergen(allergen: ApiAllergen): RecipeAllergenRecord {
  return {
    id: allergen.id,
    key: allergen.key ?? null,
    metadata: allergen.description ?? null,
    name: allergen.name ?? null,
    presence: allergen.presence ?? null,
    severity: allergen.severity ?? null,
    source: allergen.source ?? null,
  };
}

function mapVersion(version: ApiVersion): RecipeVersionRecord {
  return {
    allergenCount: version.allergen_count ?? null,
    allergens: version.allergens?.map(mapAllergen) ?? [],
    approvedAt: version.approved_at ?? null,
    approvedBy: mapUser(version.approved_by),
    category: version.category ?? null,
    changeSummary: version.change_summary ?? null,
    cookTimeMinutes: version.cook_time_minutes ?? null,
    costCurrency: version.cost_currency ?? null,
    createdAt: version.created_at ?? null,
    createdBy: mapUser(version.created_by),
    description: version.description ?? null,
    estimatedCostPerYield: toNumber(version.estimated_cost_per_yield),
    estimatedTotalCost: toNumber(version.estimated_total_cost),
    equipmentRequired: version.equipment_required ?? null,
    id: version.id,
    ingredients: version.ingredients?.map((ingredient) => ({
      componentRecipeId: ingredient.component_recipe_id ?? null,
      componentRecipeVersionId: ingredient.component_recipe_version_id ?? null,
      costCurrency: ingredient.cost_currency ?? null,
      extendedCost: toNumber(ingredient.extended_cost),
      id: ingredient.id,
      ingredientName: ingredient.ingredient_name,
      inventoryItemId: ingredient.inventory_item_id ?? null,
      notes: ingredient.notes ?? null,
      optional: ingredient.optional ?? null,
      position: ingredient.position ?? null,
      preparation: ingredient.preparation ?? null,
      quantity: toNumber(ingredient.quantity),
      scalable: ingredient.scalable ?? null,
      unit: mapUnit(ingredient.unit),
      unitCost: toNumber(ingredient.unit_cost),
      unitId: ingredient.unit_id ?? null,
      wastePercentage: toNumber(ingredient.waste_percentage),
      yieldPercentage: toNumber(ingredient.yield_percentage),
    })) ?? [],
    locked: version.locked ?? null,
    lockedAt: version.locked_at ?? null,
    name: version.name,
    prepTimeMinutes: version.prep_time_minutes ?? null,
    recipeId: version.recipe_id ?? null,
    restTimeMinutes: version.rest_time_minutes ?? null,
    revision: version.revision ?? null,
    source: version.source ?? null,
    status: version.status ?? null,
    steps: version.steps?.map((step) => ({
      critical: step.critical ?? null,
      durationMinutes: step.duration_minutes ?? null,
      id: step.id,
      instruction: step.instruction,
      notes: step.notes ?? null,
      position: step.position ?? null,
      title: step.title ?? null,
      type: step.type ?? null,
    })) ?? [],
    totalTimeMinutes: version.total_time_minutes ?? null,
    version: version.version,
    yields: version.yields?.map((yieldRecord) => ({
      factorToBase: toNumber(yieldRecord.factor_to_base),
      id: yieldRecord.id,
      isDefault: yieldRecord.is_default ?? null,
      label: yieldRecord.label ?? null,
      quantity: Number(yieldRecord.quantity ?? 0),
      unit: mapUnit(yieldRecord.unit),
      unitId: yieldRecord.unit_id ?? null,
    })) ?? [],
  };
}

function mapRecipe(recipe: ApiRecipe): RecipeRecord {
  return {
    category: recipe.category ?? null,
    createdAt: recipe.created_at ?? null,
    createdBy: mapUser(recipe.created_by),
    currentVersion: recipe.current_version ?? null,
    currentVersionId: recipe.current_version_id ?? null,
    currentVersionRecord: recipe.current_version_record ? mapVersion(recipe.current_version_record) : null,
    description: recipe.description ?? null,
    id: recipe.id,
    imageDocumentId: recipe.image_document_id ?? null,
    metadata: recipe.metadata ?? null,
    name: recipe.name,
    recipeCode: recipe.recipe_code ?? null,
    status: recipe.status ?? null,
    tags: recipe.tags?.map((tag) => ({
      id: tag.id,
      label: tag.name ?? tag.key ?? tag.id,
    } satisfies Exclude<RecipeTagValue, string>)) ?? [],
    type: recipe.type ?? null,
    updatedAt: recipe.updated_at ?? null,
    updatedBy: mapUser(recipe.updated_by),
  };
}

function mapCatalog(catalog?: ApiCatalog | null): RecipeCatalogRecord | null {
  if (!catalog) {
    return null;
  }

  return {
    allergens: catalog.allergens.map(mapAllergen),
    tags: catalog.tags.map((tag) => ({
      active: tag.active ?? null,
      description: tag.description ?? null,
      id: tag.id,
      key: tag.key ?? null,
      name: tag.name ?? null,
      workspaceId: tag.workspace_id ?? null,
    })),
    units: catalog.units.map((unit) => mapUnit(unit)).filter(Boolean) as RecipeUnitReference[],
  };
}

function buildRecipePayload(values: RecipeEditorValues, includeConflict: boolean) {
  const payload: Record<string, unknown> = {
    name: values.name.trim(),
    description: values.description?.trim() || null,
    category: values.category?.trim() || null,
    type: values.type?.trim() || "standard",
    status: values.status ?? "draft",
    recipe_code: values.recipeCode?.trim() || null,
    tags: (values.tags ?? [])
      .map((tag) => (typeof tag === "string" ? tag : tag.id))
      .filter(Boolean),
    version: {
      name: values.version.name.trim(),
      description: values.version.description?.trim() || null,
      status: values.version.status ?? "draft",
      prep_time_minutes: values.version.prepTimeMinutes ?? null,
      cook_time_minutes: values.version.cookTimeMinutes ?? null,
      rest_time_minutes: values.version.restTimeMinutes ?? null,
      total_time_minutes: values.version.totalTimeMinutes ?? null,
      change_summary: values.version.changeSummary?.trim() || null,
      ingredients: (values.version.ingredients ?? []).map((ingredient, index) => ({
        ingredient_name: ingredient.ingredientName.trim(),
        inventory_item_id: ingredient.inventoryItemId ?? null,
        component_recipe_id: ingredient.componentRecipeId ?? null,
        component_recipe_version_id: ingredient.componentRecipeVersionId ?? null,
        quantity: ingredient.quantity ?? 0,
        unit_id: ingredient.unitId,
        waste_percentage: ingredient.wastePercentage ?? null,
        yield_percentage: ingredient.yieldPercentage ?? null,
        unit_cost: ingredient.unitCost ?? null,
        cost_currency: ingredient.costCurrency ?? null,
        optional: ingredient.optional ?? false,
        scalable: ingredient.scalable ?? true,
        preparation: ingredient.preparation?.trim() || null,
        position: ingredient.position ?? index + 1,
        notes: ingredient.notes?.trim() || null,
      })),
      steps: (values.version.steps ?? []).map((step, index) => ({
        title: step.title?.trim() || null,
        instruction: step.instruction.trim(),
        duration_minutes: step.durationMinutes ?? null,
        type: step.type?.trim() || null,
        critical: step.critical ?? false,
        notes: step.notes?.trim() || null,
        position: step.position ?? index + 1,
      })),
      yields: (values.version.yields ?? []).map((yieldRecord) => ({
        quantity: yieldRecord.quantity,
        unit_id: yieldRecord.unitId,
        label: yieldRecord.label?.trim() || null,
        factor_to_base: yieldRecord.factorToBase ?? null,
        is_default: yieldRecord.isDefault ?? false,
      })),
      allergens: (values.version.allergens ?? []).map((allergen) => ({
        id: allergen.id,
        presence: allergen.presence ?? null,
        source: allergen.source ?? "manual",
      })),
    },
  };

  if (includeConflict) {
    payload.current_version_id = values.currentVersionId;
    payload.expected_revision = values.version.revision;
  }

  return payload;
}

export async function getRecipeCatalog(authToken: string, workspaceId: string) {
  const response = await apiRequest<{ data: ApiCatalog }>("/recipes/catalog", {
    authToken,
    workspaceId,
  });

  return mapCatalog(response.data);
}

export async function listRecipes(
  authToken: string,
  workspaceId: string,
  filters: { category?: string | null; cursor?: string | null; perPage?: number; search?: string; status?: string | null } = {}
): Promise<RecipesCursorPage> {
  const response = await apiRequest<ApiCursorResponse>("/recipes", {
    authToken,
    query: {
      category: filters.category ?? undefined,
      cursor: filters.cursor ?? undefined,
      per_page: filters.perPage ?? undefined,
      search: filters.search?.trim() || undefined,
      status: filters.status ?? undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapRecipe),
    meta: response.meta ? { catalog: mapCatalog(response.meta.catalog) } : null,
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getRecipe(authToken: string, workspaceId: string, recipeId: string): Promise<RecipeDetailRecord> {
  const response = await apiRequest<{ data: ApiRecipe; meta?: { catalog?: ApiCatalog | null } | null }>(`/recipes/${recipeId}`, {
    authToken,
    workspaceId,
  });

  return {
    catalog: mapCatalog(response.meta?.catalog),
    currentVersion: response.data.current_version_record ? mapVersion(response.data.current_version_record) : null,
    recipe: mapRecipe(response.data),
  };
}

export async function createRecipe(authToken: string, workspaceId: string, values: RecipeEditorValues): Promise<RecipeDetailRecord> {
  const response = await apiRequest<{ data: ApiRecipe; meta?: { catalog?: ApiCatalog | null } | null }>("/recipes", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildRecipePayload(values, false)),
    workspaceId,
  });

  return {
    catalog: mapCatalog(response.meta?.catalog),
    currentVersion: response.data.current_version_record ? mapVersion(response.data.current_version_record) : null,
    recipe: mapRecipe(response.data),
  };
}

export async function updateRecipe(authToken: string, workspaceId: string, recipeId: string, values: RecipeEditorValues): Promise<RecipeDetailRecord> {
  const response = await apiRequest<{ data: ApiRecipe; meta?: { catalog?: ApiCatalog | null } | null }>(`/recipes/${recipeId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify(buildRecipePayload(values, true)),
    workspaceId,
  });

  return {
    catalog: mapCatalog(response.meta?.catalog),
    currentVersion: response.data.current_version_record ? mapVersion(response.data.current_version_record) : null,
    recipe: mapRecipe(response.data),
  };
}

export async function getRecipeVersions(authToken: string, workspaceId: string, recipeId: string): Promise<RecipeVersionRecord[]> {
  const response = await apiRequest<{ data: ApiVersion[] }>(`/recipes/${recipeId}/versions`, {
    authToken,
    workspaceId,
  });

  return response.data.map(mapVersion);
}

export async function getRecipeVersion(authToken: string, workspaceId: string, recipeId: string, versionId: string): Promise<RecipeVersionRecord> {
  const response = await apiRequest<{ data: ApiVersion }>(`/recipes/${recipeId}/versions/${versionId}`, {
    authToken,
    workspaceId,
  });

  return mapVersion(response.data);
}

export async function compareRecipeVersions(authToken: string, workspaceId: string, recipeId: string, versionId: string, baseVersionId?: string | null): Promise<RecipeComparisonRecord> {
  const response = await apiRequest<{
    data: {
      base_version?: ApiVersion | null;
      changes: ApiVersionChange[];
      recipe?: ApiRecipe | null;
      target_version: ApiVersion;
    };
  }>(`/recipes/${recipeId}/versions/${versionId}/comparison`, {
    authToken,
    query: {
      base_version_id: baseVersionId ?? undefined,
    },
    workspaceId,
  });

  return {
    baseVersion: response.data.base_version ? mapVersion(response.data.base_version) : null,
    changes: response.data.changes.map((change) => ({
      after: change.after ?? null,
      before: change.before ?? null,
      id: change.id ?? undefined,
      label: change.label ?? "",
      severity: change.severity ?? null,
    })),
    recipe: response.data.recipe ? mapRecipe(response.data.recipe) : null,
    targetVersion: mapVersion(response.data.target_version),
  };
}
