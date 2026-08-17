import type {
  InventoryItemRecord,
  InventoryLocationReference,
  InventoryUnitReference,
  InventoryUserReference,
} from "@/features/inventory";
import type { PurchaseOrderStatus, SupplierStatus } from "@/theme/status-config";

export type { PurchaseOrderStatus, SupplierStatus } from "@/theme/status-config";

export type PurchaseOrderItemStatus =
  | "open"
  | "partially_received"
  | "received"
  | "cancelled";

export type DecimalValue = number | string | null | undefined;

export type SupplierRecord = {
  code?: string | null;
  companyName?: string | null;
  contactEmail?: string | null;
  contactName?: string | null;
  contactPhone?: string | null;
  createdAt?: string | null;
  createdBy?: InventoryUserReference | null;
  currency?: string | null;
  email?: string | null;
  id: string | null;
  leadTimeDays?: number | null;
  metadata?: Record<string, unknown> | null;
  minimumOrderAmount?: number | null;
  name?: string | null;
  notes?: string | null;
  paymentTerms?: string | null;
  phone?: string | null;
  preferred?: boolean | null;
  status?: SupplierStatus | (string & {}) | null;
  supplierItemCount?: number | null;
  updatedAt?: string | null;
  website?: string | null;
};

export type SupplierItemRecord = {
  active?: boolean | null;
  baseUnitFactor?: number | null;
  brand?: string | null;
  createdAt?: string | null;
  currency?: string | null;
  id: string | null;
  inventoryItem?: InventoryItemRecord | null;
  inventoryItemId?: string | null;
  leadTimeDays?: number | null;
  metadata?: Record<string, unknown> | null;
  minimumOrderQuantity?: number | null;
  packQuantity?: number | null;
  packUnit?: InventoryUnitReference | null;
  packUnitId?: string | null;
  preferred?: boolean | null;
  price?: number | null;
  priceUpdatedAt?: string | null;
  supplier?: SupplierRecord | null;
  supplierId?: string | null;
  supplierName?: string | null;
  supplierSku?: string | null;
  unit?: InventoryUnitReference | null;
  unitId?: string | null;
  updatedAt?: string | null;
};

export type PurchaseOrderItemRecord = {
  createdAt?: string | null;
  currency?: string | null;
  discount?: number | null;
  id: string | null;
  inventoryItem?: InventoryItemRecord | null;
  inventoryItemId?: string | null;
  itemName?: string | null;
  notes?: string | null;
  position?: number | null;
  purchaseOrderId?: string | null;
  quantity?: number | null;
  quantityCancelled?: number | null;
  quantityReceived?: number | null;
  sourceId?: string | null;
  sourceType?: string | null;
  status?: PurchaseOrderItemStatus | null;
  supplierItem?: SupplierItemRecord | null;
  supplierItemId?: string | null;
  supplierSku?: string | null;
  tax?: number | null;
  total?: number | null;
  unit?: InventoryUnitReference | null;
  unitId?: string | null;
  unitPrice?: number | null;
  updatedAt?: string | null;
};

export type PurchaseOrderRecord = {
  approvedAt?: string | null;
  approvedBy?: InventoryUserReference | null;
  cancelledAt?: string | null;
  cancelledBy?: InventoryUserReference | null;
  cancellationReason?: string | null;
  confirmedAt?: string | null;
  createdAt?: string | null;
  createdBy?: InventoryUserReference | null;
  currency?: string | null;
  discount?: number | null;
  eventId?: string | null;
  expectedAt?: string | null;
  id: string | null;
  inventoryLocation?: InventoryLocationReference | null;
  inventoryLocationId?: string | null;
  itemCount?: number | null;
  items?: PurchaseOrderItemRecord[] | null;
  metadata?: Record<string, unknown> | null;
  notes?: string | null;
  number?: string | null;
  orderedAt?: string | null;
  paymentTerms?: string | null;
  receivedAt?: string | null;
  receivedItemCount?: number | null;
  shipping?: number | null;
  source?: string | null;
  sourceId?: string | null;
  sourceType?: string | null;
  status?: PurchaseOrderStatus | null;
  submittedAt?: string | null;
  subtotal?: number | null;
  supplier?: SupplierRecord | null;
  supplierId?: string | null;
  supplierReference?: string | null;
  tax?: number | null;
  total?: number | null;
  updatedAt?: string | null;
  updatedBy?: InventoryUserReference | null;
  version?: number | null;
};

export type PurchaseOrderActionId =
  | "approve"
  | "place_order"
  | "receive"
  | "cancel"
  | "reopen";

export type PurchaseOrderAction = {
  disabled?: boolean;
  id: PurchaseOrderActionId | (string & {});
  label?: string | null;
  translationKey?: string | null;
};

export type ReceiptStatus = "draft" | "receiving" | "completed" | "cancelled";
export type ReceiptItemConditionStatus = "accepted" | "damaged" | "rejected" | "unknown";

export type ReceiptItemRecord = {
  conditionStatus?: ReceiptItemConditionStatus | null;
  currency?: string | null;
  expiresAt?: string | null;
  id: string | null;
  inventoryItem?: InventoryItemRecord | null;
  inventoryItemId?: string | null;
  lotNumber?: string | null;
  notes?: string | null;
  purchaseOrderItem?: PurchaseOrderItemRecord | null;
  purchaseOrderItemId?: string | null;
  quantityReceived?: DecimalValue;
  quantityRejected?: DecimalValue;
  receiptId?: string | null;
  unit?: InventoryUnitReference | null;
  unitCost?: DecimalValue;
};

export type ReceiptRecord = {
  id: string | null;
  inventoryLocation?: InventoryLocationReference | null;
  inventoryLocationId?: string | null;
  itemCount?: number | null;
  items?: ReceiptItemRecord[] | null;
  notes?: string | null;
  number?: string | null;
  purchaseOrder?: PurchaseOrderRecord | null;
  purchaseOrderId?: string | null;
  receivedAt?: string | null;
  receivedBy?: InventoryUserReference | null;
  reference?: string | null;
  status?: ReceiptStatus | null;
};

export type PurchaseOrderConflictType =
  | "optimistic_lock"
  | "status_changed"
  | "receiving_changed"
  | "updated";

export type PurchaseOrderConflictChange = {
  after?: React.ReactNode;
  before?: React.ReactNode;
  id?: string | null;
  label?: string | null;
  translationKey?: string | null;
};

export type PriceHistoryRecord = {
  currency?: string | null;
  effectiveAt?: string | null;
  id: string | null;
  price?: DecimalValue;
  supplierItemId?: string | null;
};

export type PurchasingSummaryRecord = {
  approved?: number | null;
  cancelled?: number | null;
  closed?: number | null;
  confirmed?: number | null;
  currency?: string | null;
  draft?: number | null;
  overdueDeliveries?: number | null;
  partiallyReceived?: number | null;
  pendingApproval?: number | null;
  purchaseOrders?: number | null;
  received?: number | null;
  submitted?: number | null;
  totalOrderedValue?: number | null;
  outstandingValue?: number | null;
};

export type PurchasingSummaryMetricKey =
  | "purchase_orders"
  | "draft"
  | "pending_approval"
  | "approved"
  | "submitted"
  | "confirmed"
  | "partially_received"
  | "received"
  | "cancelled"
  | "closed"
  | "overdue_deliveries"
  | "total_ordered_value"
  | "outstanding_value";
