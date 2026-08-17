import type { TFunction } from "i18next";

import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type {
  InventoryCountItemRecord,
  InventoryItemRecord,
  InventoryMovementRecord,
  InventoryMovementType,
  InventoryStatus,
} from "@/features/inventory/types";

export type InventoryItemEditorMode = "create" | "edit";

export type InventoryItemEditorValues = InventoryItemRecord & {
  locationId?: string | null;
  minimumQuantity?: number | null;
  preferredSupplierId?: string | null;
  reorderQuantity?: number | null;
  version?: number | null;
};

export type InventoryItemEditorValidationErrors = Partial<
  Record<
    | "name"
    | "sku"
    | "baseUnitId"
    | "locationId"
    | "minimumQuantity"
    | "reorderQuantity"
    | "preferredSupplierId"
    | "form",
    string
  >
>;

export type StockAdjustmentValues = {
  currentQuantity?: number | null;
  inventoryItemId?: string | null;
  locationId?: string | null;
  notes?: string | null;
  quantity: number;
  reason?: string | null;
  type: InventoryMovementType;
  unitId?: string | null;
  version?: number | null;
};

export type StockAdjustmentValidationErrors = Partial<
  Record<"type" | "quantity" | "reason" | "locationId" | "form", string>
>;

export type InventoryCountValues = InventoryCountItemRecord & {
  version?: number | null;
};

export type InventoryCountValidationErrors = Partial<
  Record<"countedQuantity" | "notes" | "locationId" | "form", string>
>;

export type InventoryFilters = {
  activeOnly?: boolean;
  locationId?: string | null;
  lowStockOnly?: boolean;
  outOfStockOnly?: boolean;
  search?: string;
  statuses?: InventoryStatus[];
  supplierId?: string | null;
};

export type InventoryFiltersOption = EntityPickerOption<string>;

export const INVENTORY_ITEM_TYPE_VALUES = ["ingredient"] as const;
export const INVENTORY_ADJUSTMENT_TYPE_VALUES = [
  "adjustment_in",
  "adjustment_out",
] as const satisfies readonly InventoryMovementType[];
export const INVENTORY_FILTER_STATUS_VALUES = [
  "in_stock",
  "low_stock",
  "out_of_stock",
  "unknown",
] as const satisfies readonly InventoryStatus[];

let inventoryDraftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function createInventoryDraftId(prefix = "inventory-item") {
  inventoryDraftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${inventoryDraftCounter}`;
}

export function createInventoryItemEditorValues(
  values?: Partial<InventoryItemEditorValues>
): InventoryItemEditorValues {
  return {
    active: values?.active ?? true,
    allowNegativeStock: values?.allowNegativeStock ?? false,
    barcode: values?.barcode ?? null,
    baseUnit: values?.baseUnit ?? null,
    baseUnitId: values?.baseUnitId ?? values?.baseUnit?.id ?? null,
    category: values?.category ?? null,
    costCurrency: values?.costCurrency ?? null,
    currentCost: values?.currentCost ?? null,
    defaultShelfLifeDays: values?.defaultShelfLifeDays ?? null,
    id: values?.id ?? createInventoryDraftId(),
    location: values?.location ?? null,
    locationId: values?.locationId ?? values?.location?.id ?? null,
    metadata: values?.metadata ?? null,
    minimumQuantity: values?.minimumQuantity ?? values?.stock?.minimumQuantity ?? null,
    name: values?.name ?? "",
    preferredSupplier: values?.preferredSupplier ?? null,
    preferredSupplierId:
      values?.preferredSupplierId ?? values?.preferredSupplier?.id ?? null,
    purchaseToBaseFactor: values?.purchaseToBaseFactor ?? null,
    purchaseUnit: values?.purchaseUnit ?? null,
    purchaseUnitId: values?.purchaseUnitId ?? values?.purchaseUnit?.id ?? null,
    reorderQuantity: values?.reorderQuantity ?? values?.stock?.reorderQuantity ?? null,
    sku: values?.sku ?? null,
    stock: values?.stock ?? null,
    trackExpiration: values?.trackExpiration ?? false,
    trackLots: values?.trackLots ?? false,
    type: values?.type ?? "ingredient",
    version: values?.version ?? null,
  };
}

export function normalizeInventoryItemEditorValues(
  values: InventoryItemEditorValues
): InventoryItemEditorValues {
  return {
    ...values,
    baseUnitId: trimOrNull(values.baseUnitId),
    locationId: trimOrNull(values.locationId),
    minimumQuantity:
      values.minimumQuantity === null || values.minimumQuantity === undefined
        ? null
        : Number(values.minimumQuantity),
    name: values.name.trim(),
    preferredSupplierId: trimOrNull(values.preferredSupplierId),
    reorderQuantity:
      values.reorderQuantity === null || values.reorderQuantity === undefined
        ? null
        : Number(values.reorderQuantity),
    sku: trimOrNull(values.sku),
    type: trimOrNull(values.type) ?? "ingredient",
  };
}

export function validateInventoryItemEditorValues(
  values: InventoryItemEditorValues,
  t: TFunction<"common">
): InventoryItemEditorValidationErrors {
  const errors: InventoryItemEditorValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = t("inventory.editor.errors.nameRequired");
  }

  if (!values.baseUnitId?.trim()) {
    errors.baseUnitId = t("inventory.editor.errors.baseUnitRequired");
  }

  if (
    typeof values.minimumQuantity === "number" &&
    typeof values.reorderQuantity === "number" &&
    values.reorderQuantity < values.minimumQuantity
  ) {
    errors.reorderQuantity = t("inventory.editor.errors.reorderAtLeastMinimum");
  }

  return errors;
}

export function hasInventoryItemEditorErrors(
  errors?: InventoryItemEditorValidationErrors | null
) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createStockAdjustmentValues(
  values?: Partial<StockAdjustmentValues>
): StockAdjustmentValues {
  return {
    currentQuantity: values?.currentQuantity ?? null,
    inventoryItemId: values?.inventoryItemId ?? null,
    locationId: values?.locationId ?? null,
    notes: values?.notes ?? null,
    quantity: values?.quantity ?? 0,
    reason: values?.reason ?? null,
    type: values?.type ?? "adjustment_in",
    unitId: values?.unitId ?? null,
    version: values?.version ?? null,
  };
}

export function normalizeStockAdjustmentValues(
  values: StockAdjustmentValues
): StockAdjustmentValues {
  return {
    ...values,
    inventoryItemId: trimOrNull(values.inventoryItemId),
    locationId: trimOrNull(values.locationId),
    notes: trimOrNull(values.notes),
    quantity: Number(values.quantity),
    reason: trimOrNull(values.reason),
    unitId: trimOrNull(values.unitId),
  };
}

export function validateStockAdjustmentValues(
  values: StockAdjustmentValues,
  t: TFunction<"common">
): StockAdjustmentValidationErrors {
  const errors: StockAdjustmentValidationErrors = {};

  if (!values.type) {
    errors.type = t("inventory.adjustment.errors.typeRequired");
  }

  if (!Number.isFinite(values.quantity) || values.quantity <= 0) {
    errors.quantity = t("inventory.adjustment.errors.quantityRequired");
  }

  return errors;
}

export function hasStockAdjustmentErrors(
  errors?: StockAdjustmentValidationErrors | null
) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createInventoryCountValues(
  values?: Partial<InventoryCountValues>
): InventoryCountValues {
  return {
    countedQuantity: values?.countedQuantity ?? values?.expectedQuantity ?? 0,
    expectedQuantity: values?.expectedQuantity ?? null,
    id: values?.id ?? `${createInventoryDraftId("inventory-count")}`,
    inventoryItem: values?.inventoryItem ?? null,
    inventoryItemId: values?.inventoryItemId ?? values?.inventoryItem?.id ?? null,
    location: values?.location ?? null,
    locationId: values?.locationId ?? values?.location?.id ?? null,
    notes: values?.notes ?? null,
    reviewed: values?.reviewed ?? false,
    reviewedAt: values?.reviewedAt ?? null,
    reviewedBy: values?.reviewedBy ?? null,
    stockCountId: values?.stockCountId ?? null,
    stockLotId: values?.stockLotId ?? null,
    unit: values?.unit ?? null,
    unitId: values?.unitId ?? values?.unit?.id ?? null,
    varianceCost: values?.varianceCost ?? null,
    varianceQuantity: values?.varianceQuantity ?? null,
    version: values?.version ?? null,
  };
}

export function normalizeInventoryCountValues(
  values: InventoryCountValues
): InventoryCountValues {
  return {
    ...values,
    countedQuantity:
      values.countedQuantity === null || values.countedQuantity === undefined
        ? null
        : Number(values.countedQuantity),
    locationId: trimOrNull(values.locationId),
    notes: trimOrNull(values.notes),
    unitId: trimOrNull(values.unitId),
  };
}

export function validateInventoryCountValues(
  values: InventoryCountValues,
  t: TFunction<"common">
): InventoryCountValidationErrors {
  const errors: InventoryCountValidationErrors = {};

  if (
    values.countedQuantity === null ||
    values.countedQuantity === undefined ||
    !Number.isFinite(values.countedQuantity) ||
    values.countedQuantity < 0
  ) {
    errors.countedQuantity = t("inventory.count.errors.countedQuantityRequired");
  }

  return errors;
}

export function hasInventoryCountErrors(
  errors?: InventoryCountValidationErrors | null
) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createEmptyInventoryFilters(): Required<InventoryFilters> {
  return {
    activeOnly: false,
    locationId: null,
    lowStockOnly: false,
    outOfStockOnly: false,
    search: "",
    statuses: [],
    supplierId: null,
  };
}

export function normalizeInventoryFilters(
  filters?: InventoryFilters | null
): Required<InventoryFilters> {
  const values = {
    ...createEmptyInventoryFilters(),
    ...filters,
  };

  return {
    activeOnly: Boolean(values.activeOnly),
    locationId: trimOrNull(values.locationId),
    lowStockOnly: Boolean(values.lowStockOnly),
    outOfStockOnly: Boolean(values.outOfStockOnly),
    search: values.search?.trim() ?? "",
    statuses: values.statuses?.filter(Boolean) ?? [],
    supplierId: trimOrNull(values.supplierId),
  };
}
