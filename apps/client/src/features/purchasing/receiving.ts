import type { TFunction } from "i18next";
import { formatInventoryCurrency, formatInventoryMeasurement, getInventoryItemName } from "@/features/inventory";
import type { DecimalValue, PurchaseOrderAction, PurchaseOrderActionId, PurchaseOrderItemRecord, ReceiptItemRecord, ReceiptRecord } from "@/features/purchasing/types";

const ACTION_IDS = ["approve", "place_order", "receive", "cancel", "reopen"] as const satisfies readonly PurchaseOrderActionId[];
function decimal(value: DecimalValue) { if (value === null || value === undefined || value === "") return null; const result = typeof value === "number" ? value : Number(value); return Number.isFinite(result) ? result : null; }
export function compareDecimalValues(left: DecimalValue, right: DecimalValue) { const a = decimal(left); const b = decimal(right); if (a === null || b === null || a === b) return 0; return a > b ? 1 : -1; }
export function subtractDecimalValues(left: DecimalValue, right: DecimalValue) { const a = decimal(left); const b = decimal(right); return a === null || b === null ? null : a - b; }
export function formatDecimalCurrency(value: DecimalValue, currency?: string | null, locale?: string) { return formatInventoryCurrency(decimal(value), currency, locale); }
export function formatReceiptMeasurement(value: DecimalValue, unit?: ReceiptItemRecord["unit"] | PurchaseOrderItemRecord["unit"] | null, locale?: string) { return formatInventoryMeasurement(decimal(value), unit, locale); }
export function getReceiptReference(receipt?: ReceiptRecord | null) { return receipt?.number?.trim() || receipt?.reference?.trim() || receipt?.id || null; }
export function getReceiptItemName(item?: ReceiptItemRecord | null) { return item?.purchaseOrderItem?.itemName?.trim() || getInventoryItemName(item?.inventoryItem ?? item?.purchaseOrderItem?.inventoryItem) || item?.purchaseOrderItem?.supplierItem?.supplierName?.trim() || item?.purchaseOrderItem?.supplierSku?.trim() || null; }
export function getPurchaseOrderItemRemainingQuantity(item?: PurchaseOrderItemRecord | null) { if (!item) return null; const ordered = decimal(item.quantity); const received = decimal(item.quantityReceived) ?? 0; const cancelled = decimal(item.quantityCancelled) ?? 0; return ordered === null ? null : ordered - received - cancelled; }
export function getPriceTrend(current: DecimalValue, previous: DecimalValue) { if (decimal(current) === null || decimal(previous) === null) return "unknown" as const; const comparison = compareDecimalValues(current, previous); return comparison > 0 ? "increased" as const : comparison < 0 ? "decreased" as const : "unchanged" as const; }
export function isSupportedPurchaseOrderAction(action: PurchaseOrderAction): action is PurchaseOrderAction & { id: PurchaseOrderActionId } { return (ACTION_IDS as readonly string[]).includes(action.id); }
export function resolvePurchaseOrderActionLabel(action: PurchaseOrderAction, t: TFunction<"common">) { if (action.label?.trim()) return action.label.trim(); if (action.translationKey?.trim()) return t(action.translationKey); return isSupportedPurchaseOrderAction(action) ? t(`purchasing.purchaseOrderActions.actions.${action.id}`) : null; }
