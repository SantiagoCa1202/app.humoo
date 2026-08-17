import type { InventoryStatus } from "@/theme/status-config";

export type { InventoryStatus } from "@/theme/status-config";

export type InventoryUnitReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  symbol?: string | null;
};

export type InventoryLocationReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  status?: string | null;
  type?: string | null;
};

export type InventorySupplierReference = {
  id?: string | null;
  active?: boolean | null;
  name?: string | null;
  preferred?: boolean | null;
  supplierName?: string | null;
  supplierSku?: string | null;
};

export type InventoryStockRecord = {
  availableQuantity?: number | null;
  currency?: string | null;
  inventoryValue?: number | null;
  location?: InventoryLocationReference | null;
  minimumQuantity?: number | null;
  onHandQuantity?: number | null;
  reorderQuantity?: number | null;
  reservedQuantity?: number | null;
  shortageQuantity?: number | null;
  status?: InventoryStatus | null;
  supplier?: InventorySupplierReference | null;
  unit?: InventoryUnitReference | null;
};

export type InventoryItemRecord = {
  active?: boolean | null;
  allowNegativeStock?: boolean | null;
  barcode?: string | null;
  baseUnit?: InventoryUnitReference | null;
  baseUnitId?: string | null;
  category?: string | null;
  costCurrency?: string | null;
  currentCost?: number | null;
  defaultShelfLifeDays?: number | null;
  id: string | null;
  location?: InventoryLocationReference | null;
  metadata?: Record<string, unknown> | null;
  name: string;
  preferredSupplier?: InventorySupplierReference | null;
  purchaseToBaseFactor?: number | null;
  purchaseUnit?: InventoryUnitReference | null;
  purchaseUnitId?: string | null;
  sku?: string | null;
  stock?: InventoryStockRecord | null;
  trackExpiration?: boolean | null;
  trackLots?: boolean | null;
  type?: string | null;
};

export type InventorySummaryRecord = {
  currency?: string | null;
  inStock?: number | null;
  inventoryValue?: number | null;
  locations?: number | null;
  lowStock?: number | null;
  outOfStock?: number | null;
  total?: number | null;
  unknown?: number | null;
};

export type InventorySummaryMetricKey =
  | "total"
  | "in_stock"
  | "low_stock"
  | "out_of_stock"
  | "unknown"
  | "locations"
  | "inventory_value";
