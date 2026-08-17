import type { InventoryStatus } from "@/theme/status-config";

export type { InventoryStatus } from "@/theme/status-config";

export type InventoryMovementType =
  | "receive"
  | "consume"
  | "adjustment_in"
  | "adjustment_out"
  | "transfer"
  | "waste"
  | "count_adjustment"
  | "return_to_supplier"
  | "return_from_event";

export type StockCountStatus =
  | "draft"
  | "in_progress"
  | "completed"
  | "cancelled";

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

export type InventoryUserReference = {
  id?: string | null;
  name?: string | null;
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

export type InventoryMovementRecord = {
  baseQuantity?: number | null;
  baseUnit?: InventoryUnitReference | null;
  baseUnitId?: string | null;
  correlationId?: string | null;
  createdAt?: string | null;
  createdBy?: InventoryUserReference | null;
  currency?: string | null;
  fromLocation?: InventoryLocationReference | null;
  fromLocationId?: string | null;
  id: string | null;
  inventoryItem?: InventoryItemRecord | null;
  inventoryItemId?: string | null;
  inventoryLocationId?: string | null;
  location?: InventoryLocationReference | null;
  notes?: string | null;
  occurredAt?: string | null;
  quantity?: number | null;
  reason?: string | null;
  referenceId?: string | null;
  referenceType?: string | null;
  resultingQuantity?: number | null;
  source?: string | null;
  stockLotId?: string | null;
  toLocation?: InventoryLocationReference | null;
  toLocationId?: string | null;
  totalCost?: number | null;
  type?: InventoryMovementType | null;
  unit?: InventoryUnitReference | null;
  unitCost?: number | null;
  unitId?: string | null;
};

export type InventoryCountItemRecord = {
  countedQuantity?: number | null;
  expectedQuantity?: number | null;
  id: string | null;
  inventoryItem?: InventoryItemRecord | null;
  inventoryItemId?: string | null;
  location?: InventoryLocationReference | null;
  locationId?: string | null;
  notes?: string | null;
  reviewed?: boolean | null;
  reviewedAt?: string | null;
  reviewedBy?: InventoryUserReference | null;
  stockCountId?: string | null;
  stockLotId?: string | null;
  unit?: InventoryUnitReference | null;
  unitId?: string | null;
  varianceCost?: number | null;
  varianceQuantity?: number | null;
};
