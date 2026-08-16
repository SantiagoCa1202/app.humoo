import type { ImageSourcePropType } from "react-native";

import type { RecipeStatus } from "@/theme/status-config";
export type { RecipeStatus } from "@/theme/status-config";

export type RecipeVersionStatus =
  | "draft"
  | "review"
  | "approved"
  | "superseded"
  | "archived";

export type RecipeUserReference = {
  id?: string;
  name?: string | null;
  source?: ImageSourcePropType;
};

export type RecipeUnitReference = {
  id?: string;
  key?: string | null;
  name?: string | null;
  symbol?: string | null;
};

export type RecipeTagValue =
  | string
  | {
      id?: string;
      label: string;
    };

export type RecipeAllergenRecord = {
  id: string;
  key?: string | null;
  name?: string | null;
  presence?: "contains" | "may_contain" | "cross_contact" | null;
  source?: "manual" | "ingredient" | "ai" | null;
};

export type RecipeYieldRecord = {
  clientId?: string | null;
  factorToBase?: number | null;
  id: string | null;
  isDefault?: boolean | null;
  label?: string | null;
  quantity: number;
  unit?: RecipeUnitReference | null;
  unitId?: string | null;
};

export type RecipeIngredientRecord = {
  clientId?: string | null;
  componentRecipeId?: string | null;
  componentRecipeVersionId?: string | null;
  costCurrency?: string | null;
  extendedCost?: number | null;
  id: string | null;
  ingredientName: string;
  inventoryItemId?: string | null;
  notes?: string | null;
  optional?: boolean | null;
  position?: number | null;
  preparation?: string | null;
  quantity?: number | null;
  scalable?: boolean | null;
  unit?: RecipeUnitReference | null;
  unitCost?: number | null;
  unitId?: string | null;
  wastePercentage?: number | null;
  yieldPercentage?: number | null;
};

export type RecipeStepRecord = {
  clientId?: string | null;
  critical?: boolean | null;
  durationMinutes?: number | null;
  id: string | null;
  instruction: string;
  notes?: string | null;
  position?: number | null;
  title?: string | null;
  type?: string | null;
};

export type RecipeVersionRecord = {
  allergenCount?: number | null;
  allergens?: RecipeAllergenRecord[] | null;
  approvedAt?: string | null;
  approvedBy?: RecipeUserReference | null;
  category?: string | null;
  clientId?: string | null;
  changeSummary?: string | null;
  cookTimeMinutes?: number | null;
  costCurrency?: string | null;
  createdAt?: string | null;
  createdBy?: RecipeUserReference | null;
  description?: string | null;
  estimatedCostPerYield?: number | null;
  estimatedTotalCost?: number | null;
  equipmentRequired?: string | null;
  id: string | null;
  ingredients?: RecipeIngredientRecord[] | null;
  locked?: boolean | null;
  lockedAt?: string | null;
  name: string;
  prepTimeMinutes?: number | null;
  recipeId?: string | null;
  restTimeMinutes?: number | null;
  revision?: number | null;
  shelfLifeHours?: number | null;
  source?: "manual" | "duplicated" | "import" | "ai" | null;
  status?: RecipeVersionStatus | null;
  steps?: RecipeStepRecord[] | null;
  totalTimeMinutes?: number | null;
  version: number;
  yields?: RecipeYieldRecord[] | null;
};

export type RecipeRecord = {
  category?: string | null;
  createdAt?: string | null;
  createdBy?: RecipeUserReference | null;
  currentVersion?: number | null;
  currentVersionId?: string | null;
  description?: string | null;
  id: string;
  imageDocumentId?: string | null;
  metadata?: Record<string, unknown> | null;
  name: string;
  recipeCode?: string | null;
  status?: RecipeStatus | null;
  tags?: RecipeTagValue[] | null;
  type?: string | null;
  updatedAt?: string | null;
  updatedBy?: RecipeUserReference | null;
};
