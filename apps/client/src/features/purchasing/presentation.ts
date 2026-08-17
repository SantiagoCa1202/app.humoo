import type { TFunction } from "i18next";

import {
  formatInventoryCurrency,
  formatInventoryDateLabel,
  formatInventoryMeasurement,
  formatInventoryQuantity,
  getInventoryItemName,
  getInventoryLocationName,
  type InventoryItemRecord,
} from "@/features/inventory";
import type {
  PurchaseOrderItemRecord,
  PurchaseOrderRecord,
  PurchaseOrderStatus,
  PurchasingSummaryRecord,
  SupplierItemRecord,
  SupplierRecord,
  SupplierStatus,
} from "@/features/purchasing/types";

export const PURCHASE_ORDER_STATUS_VALUES = [
  "draft",
  "pending_approval",
  "approved",
  "submitted",
  "confirmed",
  "partially_received",
  "received",
  "cancelled",
  "closed",
] as const satisfies readonly PurchaseOrderStatus[];

const SUPPLIER_STATUS_VALUES = ["active", "inactive"] as const;

export function formatPurchasingCurrency(
  amount?: number | null,
  currency?: string | null,
  locale?: string
) {
  return formatInventoryCurrency(amount, currency, locale);
}

export function formatPurchasingQuantity(value?: number | null, locale?: string) {
  return formatInventoryQuantity(value, locale);
}

export function formatPurchasingMeasurement(
  quantity?: number | null,
  unit?: SupplierItemRecord["unit"] | PurchaseOrderItemRecord["unit"] | null,
  locale?: string
) {
  return formatInventoryMeasurement(quantity, unit, locale);
}

export function formatPurchasingDateLabel(value?: string | null, locale?: string) {
  return formatInventoryDateLabel(value, locale);
}

export function getSupplierName(supplier?: SupplierRecord | null) {
  return (
    supplier?.name?.trim() ||
    supplier?.companyName?.trim() ||
    supplier?.code?.trim() ||
    null
  );
}

export function getSupplierStatusValue(
  status?: SupplierRecord["status"]
): SupplierStatus | null {
  return typeof status === "string" &&
    (SUPPLIER_STATUS_VALUES as readonly string[]).includes(status)
    ? (status as SupplierStatus)
    : null;
}

export function getSupplierPaymentTermsTranslationKey(paymentTerms?: string | null) {
  if (!paymentTerms?.trim()) {
    return null;
  }

  const normalized = paymentTerms.trim();
  const knownTerms = ["due_on_receipt", "net_15", "net_30", "net_60"];

  return knownTerms.includes(normalized)
    ? `purchasing.paymentTerms.${normalized}`
    : null;
}

export function formatSupplierPaymentTerms(
  paymentTerms: string | null | undefined,
  t: TFunction<"common">
) {
  const translationKey = getSupplierPaymentTermsTranslationKey(paymentTerms);

  return translationKey ? t(translationKey) : paymentTerms?.trim() || null;
}

export function formatSupplierLeadTime(
  leadTimeDays: number | null | undefined,
  t: TFunction<"common">
) {
  if (leadTimeDays === null || leadTimeDays === undefined) {
    return null;
  }

  return t("purchasing.labels.leadTimeDays", { count: leadTimeDays });
}

export function getSupplierItemName(
  supplierItem?: SupplierItemRecord | null,
  inventoryItem?: InventoryItemRecord | null
) {
  return (
    supplierItem?.supplierName?.trim() ||
    getInventoryItemName(inventoryItem ?? supplierItem?.inventoryItem) ||
    supplierItem?.supplierSku?.trim() ||
    null
  );
}

export function getPurchaseOrderItemName(item?: PurchaseOrderItemRecord | null) {
  return (
    item?.itemName?.trim() ||
    getSupplierItemName(item?.supplierItem, item?.inventoryItem) ||
    item?.supplierSku?.trim() ||
    null
  );
}

export function getPurchaseOrderStatusValue(
  status?: PurchaseOrderRecord["status"]
): PurchaseOrderStatus | null {
  return typeof status === "string" &&
    (PURCHASE_ORDER_STATUS_VALUES as readonly string[]).includes(status)
    ? (status as PurchaseOrderStatus)
    : null;
}

export function getPurchaseOrderReference(purchaseOrder?: PurchaseOrderRecord | null) {
  return (
    purchaseOrder?.number?.trim() ||
    purchaseOrder?.supplierReference?.trim() ||
    purchaseOrder?.id ||
    null
  );
}

export function getPurchaseOrderSupplierName(
  purchaseOrder?: PurchaseOrderRecord | null,
  supplier?: SupplierRecord | null
) {
  return getSupplierName(supplier ?? purchaseOrder?.supplier);
}

export function getPurchaseOrderItemCount(purchaseOrder?: PurchaseOrderRecord | null) {
  if (typeof purchaseOrder?.itemCount === "number") {
    return purchaseOrder.itemCount;
  }

  return purchaseOrder?.items?.length ?? null;
}

export function getPurchaseOrderReceivedItemCount(
  purchaseOrder?: PurchaseOrderRecord | null
) {
  if (typeof purchaseOrder?.receivedItemCount === "number") {
    return purchaseOrder.receivedItemCount;
  }

  if (!purchaseOrder?.items?.length) {
    return null;
  }

  return purchaseOrder.items.filter((item) => {
    if (item.status === "received" || item.status === "partially_received") {
      return true;
    }

    return Boolean(item.quantityReceived && item.quantityReceived > 0);
  }).length;
}

export function getPurchaseOrderDate(purchaseOrder?: PurchaseOrderRecord | null) {
  return (
    purchaseOrder?.orderedAt ??
    purchaseOrder?.submittedAt ??
    purchaseOrder?.createdAt ??
    null
  );
}

export function getPurchaseOrderOutstandingValue(purchaseOrder?: PurchaseOrderRecord | null) {
  if (!purchaseOrder) {
    return null;
  }

  const resolvedStatus = getPurchaseOrderStatusValue(purchaseOrder.status);

  if (
    resolvedStatus === "received" ||
    resolvedStatus === "cancelled" ||
    resolvedStatus === "closed"
  ) {
    return 0;
  }

  return purchaseOrder.total ?? null;
}

export function isPurchaseOrderOverdue(
  purchaseOrder?: PurchaseOrderRecord | null,
  now = new Date()
) {
  const resolvedStatus = getPurchaseOrderStatusValue(purchaseOrder?.status);

  if (
    !purchaseOrder?.expectedAt ||
    resolvedStatus === "received" ||
    resolvedStatus === "cancelled" ||
    resolvedStatus === "closed"
  ) {
    return false;
  }

  const expectedAt = new Date(purchaseOrder.expectedAt);

  if (Number.isNaN(expectedAt.getTime())) {
    return false;
  }

  return expectedAt.getTime() < now.getTime();
}

export function getPurchaseOrderReceivedProgressLabel(
  purchaseOrder: PurchaseOrderRecord,
  t: TFunction<"common">
) {
  const receivedCount = getPurchaseOrderReceivedItemCount(purchaseOrder);
  const itemCount = getPurchaseOrderItemCount(purchaseOrder);

  if (
    receivedCount === null ||
    receivedCount === undefined ||
    itemCount === null ||
    itemCount === undefined
  ) {
    return null;
  }

  return t("purchasing.purchaseOrders.receivedProgress", {
    received: receivedCount,
    total: itemCount,
  });
}

export function buildPurchasingSummary(
  purchaseOrders: PurchaseOrderRecord[]
): PurchasingSummaryRecord {
  const summary: PurchasingSummaryRecord = {
    approved: 0,
    cancelled: 0,
    closed: 0,
    confirmed: 0,
    draft: 0,
    overdueDeliveries: 0,
    partiallyReceived: 0,
    pendingApproval: 0,
    purchaseOrders: purchaseOrders.length,
    received: 0,
    submitted: 0,
    totalOrderedValue: 0,
    outstandingValue: 0,
  };

  const currencies = new Set<string>();

  purchaseOrders.forEach((purchaseOrder) => {
    const currency = purchaseOrder.currency?.trim()?.toUpperCase();

    if (currency) {
      currencies.add(currency);
    }

    switch (getPurchaseOrderStatusValue(purchaseOrder.status)) {
      case "draft":
        summary.draft = (summary.draft ?? 0) + 1;
        break;
      case "pending_approval":
        summary.pendingApproval = (summary.pendingApproval ?? 0) + 1;
        break;
      case "approved":
        summary.approved = (summary.approved ?? 0) + 1;
        break;
      case "submitted":
        summary.submitted = (summary.submitted ?? 0) + 1;
        break;
      case "confirmed":
        summary.confirmed = (summary.confirmed ?? 0) + 1;
        break;
      case "partially_received":
        summary.partiallyReceived = (summary.partiallyReceived ?? 0) + 1;
        break;
      case "received":
        summary.received = (summary.received ?? 0) + 1;
        break;
      case "cancelled":
        summary.cancelled = (summary.cancelled ?? 0) + 1;
        break;
      case "closed":
        summary.closed = (summary.closed ?? 0) + 1;
        break;
      default:
        break;
    }

    if (typeof purchaseOrder.total === "number") {
      summary.totalOrderedValue = (summary.totalOrderedValue ?? 0) + purchaseOrder.total;
    }

    const outstandingValue = getPurchaseOrderOutstandingValue(purchaseOrder);

    if (typeof outstandingValue === "number") {
      summary.outstandingValue = (summary.outstandingValue ?? 0) + outstandingValue;
    }

    if (isPurchaseOrderOverdue(purchaseOrder)) {
      summary.overdueDeliveries = (summary.overdueDeliveries ?? 0) + 1;
    }
  });

  if (currencies.size === 1) {
    summary.currency = [...currencies][0];
  } else {
    summary.currency = null;
    summary.totalOrderedValue = null;
    summary.outstandingValue = null;
  }

  return summary;
}

export function groupPurchaseOrdersByStatus(purchaseOrders: PurchaseOrderRecord[]) {
  return PURCHASE_ORDER_STATUS_VALUES.map((status) => ({
    id: status,
    purchaseOrders: purchaseOrders.filter(
      (purchaseOrder) => getPurchaseOrderStatusValue(purchaseOrder.status) === status
    ),
    status,
  })).filter((group) => group.purchaseOrders.length > 0);
}

export function getSupplierItemLocationName(
  supplierItem?: SupplierItemRecord | null
) {
  return getInventoryLocationName(supplierItem?.inventoryItem?.location);
}
