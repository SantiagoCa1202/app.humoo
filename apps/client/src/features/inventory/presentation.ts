import type {
  InventoryItemRecord,
  InventoryLocationReference,
  InventoryStatus,
  InventoryStockRecord,
  InventorySummaryRecord,
  InventorySupplierReference,
  InventoryUnitReference,
} from "@/features/inventory/types";

export const INVENTORY_STATUS_ORDER: InventoryStatus[] = [
  "out_of_stock",
  "low_stock",
  "in_stock",
  "unknown",
];

function getUnitLabel(unit?: InventoryUnitReference | null) {
  return unit?.symbol?.trim() || unit?.name?.trim() || unit?.key?.trim() || null;
}

export function getInventoryItemName(item?: InventoryItemRecord | null) {
  return item?.name?.trim() || item?.sku?.trim() || item?.barcode?.trim() || null;
}

export function getInventoryUnit(
  item?: InventoryItemRecord | null,
  stock?: InventoryStockRecord | null
) {
  return stock?.unit ?? item?.baseUnit ?? item?.purchaseUnit ?? null;
}

export function getInventoryLocation(
  item?: InventoryItemRecord | null,
  stock?: InventoryStockRecord | null
) {
  return stock?.location ?? item?.location ?? null;
}

export function getInventorySupplier(
  item?: InventoryItemRecord | null,
  stock?: InventoryStockRecord | null
) {
  return stock?.supplier ?? item?.preferredSupplier ?? null;
}

export function getInventoryLocationName(location?: InventoryLocationReference | null) {
  return location?.name?.trim() || location?.key?.trim() || null;
}

export function getInventorySupplierName(supplier?: InventorySupplierReference | null) {
  return supplier?.supplierName?.trim() || supplier?.name?.trim() || null;
}

export function formatInventoryQuantity(value?: number | null, locale?: string) {
  if (value === null || value === undefined) {
    return null;
  }

  return new Intl.NumberFormat(locale, {
    maximumFractionDigits: 4,
  }).format(value);
}

export function formatInventoryMeasurement(
  quantity?: number | null,
  unit?: InventoryUnitReference | null,
  locale?: string
) {
  const formattedQuantity = formatInventoryQuantity(quantity, locale);
  const unitLabel = getUnitLabel(unit);

  if (formattedQuantity && unitLabel) {
    return `${formattedQuantity} ${unitLabel}`;
  }

  return formattedQuantity ?? unitLabel;
}

export function formatInventoryCurrency(
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

export function getInventoryAvailableQuantity(stock?: InventoryStockRecord | null) {
  return stock?.availableQuantity ?? stock?.onHandQuantity ?? null;
}

export function getInventoryThreshold(stock?: InventoryStockRecord | null) {
  if (stock?.minimumQuantity !== null && stock?.minimumQuantity !== undefined) {
    return {
      labelKey: "inventory.labels.minimumStock",
      value: stock.minimumQuantity,
    };
  }

  if (stock?.reorderQuantity !== null && stock?.reorderQuantity !== undefined) {
    return {
      labelKey: "inventory.labels.reorderPoint",
      value: stock.reorderQuantity,
    };
  }

  return null;
}

export function getInventoryStatus(
  item?: InventoryItemRecord | null,
  stock?: InventoryStockRecord | null
): InventoryStatus {
  if (stock?.status) {
    return stock.status;
  }

  const resolvedStock = stock ?? item?.stock ?? null;
  const currentQuantity = getInventoryAvailableQuantity(resolvedStock);

  if (currentQuantity === null || currentQuantity === undefined) {
    return "unknown";
  }

  if (currentQuantity <= 0) {
    return "out_of_stock";
  }

  if (
    resolvedStock?.minimumQuantity !== null &&
    resolvedStock?.minimumQuantity !== undefined &&
    currentQuantity <= resolvedStock.minimumQuantity
  ) {
    return "low_stock";
  }

  if (
    resolvedStock?.reorderQuantity !== null &&
    resolvedStock?.reorderQuantity !== undefined &&
    currentQuantity <= resolvedStock.reorderQuantity
  ) {
    return "low_stock";
  }

  return "in_stock";
}

export function buildInventorySummary(
  items: InventoryItemRecord[]
): InventorySummaryRecord {
  const summary: InventorySummaryRecord = {
    inStock: 0,
    locations: 0,
    lowStock: 0,
    outOfStock: 0,
    total: items.length,
    unknown: 0,
  };
  const locationKeys = new Set<string>();
  let currency: string | null = null;
  let totalValue = 0;
  let hasInventoryValue = false;
  let multipleCurrencies = false;

  items.forEach((item) => {
    const status = getInventoryStatus(item, item.stock);

    if (status === "in_stock") {
      summary.inStock = (summary.inStock ?? 0) + 1;
    } else if (status === "low_stock") {
      summary.lowStock = (summary.lowStock ?? 0) + 1;
    } else if (status === "out_of_stock") {
      summary.outOfStock = (summary.outOfStock ?? 0) + 1;
    } else {
      summary.unknown = (summary.unknown ?? 0) + 1;
    }

    const location = getInventoryLocation(item, item.stock);
    const locationKey = location?.id ?? getInventoryLocationName(location);

    if (locationKey) {
      locationKeys.add(locationKey);
    }

    if (typeof item.stock?.inventoryValue === "number") {
      const valueCurrency = item.stock.currency?.trim() || item.costCurrency?.trim() || null;

      if (valueCurrency) {
        if (currency && currency !== valueCurrency) {
          multipleCurrencies = true;
        } else {
          currency = valueCurrency;
          totalValue += item.stock.inventoryValue;
          hasInventoryValue = true;
        }
      }
    }
  });

  summary.locations = locationKeys.size;

  if (hasInventoryValue && !multipleCurrencies && currency) {
    summary.currency = currency;
    summary.inventoryValue = totalValue;
  }

  return summary;
}

export function groupInventoryItemsByStatus(items: InventoryItemRecord[]) {
  return INVENTORY_STATUS_ORDER.map((status) => ({
    id: `inventory-status-${status}`,
    items: items.filter((item) => getInventoryStatus(item, item.stock) === status),
    status,
  })).filter((group) => group.items.length > 0);
}

export function groupInventoryItemsByLocation(items: InventoryItemRecord[]) {
  const groups = new Map<
    string,
    {
      id: string;
      items: InventoryItemRecord[];
      location: InventoryLocationReference | null;
    }
  >();

  items.forEach((item, index) => {
    const location = getInventoryLocation(item, item.stock);
    const key = location?.id ?? getInventoryLocationName(location) ?? "unknown";
    const existing = groups.get(key);

    if (existing) {
      existing.items.push(item);
      return;
    }

    groups.set(key, {
      id: `inventory-location-${key}`,
      items: [item],
      location,
    });
  });

  return [...groups.values()];
}
