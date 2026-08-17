import type {
  InventoryCountItemRecord,
  InventoryItemRecord,
  InventoryLotRecord,
  InventoryLocationReference,
  InventoryMovementRecord,
  InventoryMovementType,
  InventoryParLevelRecord,
  InventoryRequirementRecord,
  InventoryRequirementStatus,
  InventoryStatus,
  InventoryStockRecord,
  InventorySummaryRecord,
  InventorySupplierReference,
  InventoryUnitReference,
  WasteEntryRecord,
} from "@/features/inventory/types";

export const INVENTORY_STATUS_ORDER: InventoryStatus[] = [
  "out_of_stock",
  "low_stock",
  "in_stock",
  "unknown",
];

export const INVENTORY_MOVEMENT_TYPE_VALUES: InventoryMovementType[] = [
  "receive",
  "consume",
  "adjustment_in",
  "adjustment_out",
  "transfer",
  "waste",
  "count_adjustment",
  "return_to_supplier",
  "return_from_event",
];

const INVENTORY_WASTE_REASON_VALUES = [
  "spoilage",
  "overproduction",
  "trimming",
  "expired",
  "damaged",
  "dropped",
  "quality",
  "returned",
  "other",
] as const;

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

export function getInventoryLotLabel(lot?: InventoryLotRecord | null) {
  return lot?.lotNumber?.trim() || lot?.supplierLotNumber?.trim() || null;
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

export function formatInventoryDateTime(value?: string | null, locale?: string) {
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

export function formatInventoryDateLabel(value?: string | null, locale?: string) {
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
    }).format(date);
  } catch {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
    }).format(date);
  }
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

export function getInventoryParLevelThreshold(
  parLevel?: InventoryParLevelRecord | null
) {
  if (parLevel?.minimumQuantity !== null && parLevel?.minimumQuantity !== undefined) {
    return {
      labelKey: "inventory.labels.minimumStock",
      value: parLevel.minimumQuantity,
    };
  }

  if (parLevel?.reorderQuantity !== null && parLevel?.reorderQuantity !== undefined) {
    return {
      labelKey: "inventory.labels.reorderPoint",
      value: parLevel.reorderQuantity,
    };
  }

  if (parLevel?.targetQuantity !== null && parLevel?.targetQuantity !== undefined) {
    return {
      labelKey: "inventory.labels.parLevel",
      value: parLevel.targetQuantity,
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

export function getInventoryMovementType(movement?: InventoryMovementRecord | null) {
  return movement?.type ?? null;
}

export function getInventoryMovementTranslationKey(
  type?: InventoryMovementType | null
) {
  return type ? `inventory.movements.types.${type}` : "inventory.movements.types.adjustment_in";
}

export function getInventoryMovementTone(type?: InventoryMovementType | null) {
  switch (type) {
    case "receive":
    case "adjustment_in":
    case "return_from_event":
      return "success" as const;
    case "transfer":
    case "count_adjustment":
      return "info" as const;
    case "consume":
    case "adjustment_out":
    case "return_to_supplier":
    case "waste":
      return "warning" as const;
    default:
      return "neutral" as const;
  }
}

export function getInventoryMovementDirection(type?: InventoryMovementType | null) {
  switch (type) {
    case "receive":
    case "adjustment_in":
    case "return_from_event":
      return "in";
    case "consume":
    case "adjustment_out":
    case "return_to_supplier":
    case "waste":
      return "out";
    default:
      return "neutral";
  }
}

export function getInventoryMovementItemName(
  movement?: InventoryMovementRecord | null,
  item?: InventoryItemRecord | null
) {
  return getInventoryItemName(item ?? movement?.inventoryItem);
}

export function getInventoryMovementLocationLabel(
  movement?: InventoryMovementRecord | null
) {
  const fromLabel = getInventoryLocationName(movement?.fromLocation);
  const toLabel = getInventoryLocationName(movement?.toLocation);
  const locationLabel = getInventoryLocationName(movement?.location);

  if (fromLabel && toLabel) {
    return `${fromLabel} -> ${toLabel}`;
  }

  return locationLabel ?? fromLabel ?? toLabel ?? null;
}

export function getInventoryMovementQuantityLabel(
  movement?: InventoryMovementRecord | null,
  locale?: string
) {
  return formatInventoryMeasurement(movement?.quantity, movement?.unit ?? movement?.baseUnit, locale);
}

export function getInventoryMovementResultingLabel(
  movement?: InventoryMovementRecord | null,
  locale?: string
) {
  return formatInventoryMeasurement(
    movement?.resultingQuantity,
    movement?.unit ?? movement?.baseUnit,
    locale
  );
}

export function getInventoryExpirationStatus(
  lot?: InventoryLotRecord | null,
  providedStatus?: "expiring_soon" | "expired" | null
) {
  if (providedStatus) {
    return providedStatus;
  }

  if (lot?.status === "expired") {
    return "expired";
  }

  if (!lot?.expiresAt) {
    return null;
  }

  const expiresAt = new Date(lot.expiresAt);

  if (Number.isNaN(expiresAt.getTime())) {
    return null;
  }

  const today = new Date();
  const endOfToday = new Date(
    today.getFullYear(),
    today.getMonth(),
    today.getDate(),
    23,
    59,
    59,
    999
  );

  if (expiresAt.getTime() <= endOfToday.getTime()) {
    return "expired";
  }

  return null;
}

export function getInventoryDaysUntilExpiration(
  lot?: InventoryLotRecord | null
) {
  if (!lot?.expiresAt) {
    return null;
  }

  const expiresAt = new Date(lot.expiresAt);

  if (Number.isNaN(expiresAt.getTime())) {
    return null;
  }

  const today = new Date();
  const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  const startOfExpiration = new Date(
    expiresAt.getFullYear(),
    expiresAt.getMonth(),
    expiresAt.getDate()
  );

  return Math.round(
    (startOfExpiration.getTime() - startOfToday.getTime()) / (1000 * 60 * 60 * 24)
  );
}

export function getInventoryCountDifference(
  count?: InventoryCountItemRecord | null
) {
  if (
    count?.varianceQuantity !== null &&
    count?.varianceQuantity !== undefined
  ) {
    return count.varianceQuantity;
  }

  if (
    count?.expectedQuantity === null ||
    count?.expectedQuantity === undefined ||
    count?.countedQuantity === null ||
    count?.countedQuantity === undefined
  ) {
    return null;
  }

  return count.countedQuantity - count.expectedQuantity;
}

export function formatInventoryDifference(
  value?: number | null,
  unit?: InventoryUnitReference | null,
  locale?: string
) {
  if (value === null || value === undefined) {
    return null;
  }

  const measurement = formatInventoryMeasurement(Math.abs(value), unit, locale);

  if (!measurement) {
    return null;
  }

  if (value > 0) {
    return `+${measurement}`;
  }

  if (value < 0) {
    return `-${measurement}`;
  }

  return measurement;
}

export function getInventoryWasteReasonTranslationKey(reason?: string | null) {
  return reason ? `inventory.waste.reasons.${reason}` : "inventory.waste.reasons.other";
}

export function getRequiredAvailabilityStatus(
  requirement?: InventoryRequirementRecord | null
): InventoryRequirementStatus {
  if (requirement?.status) {
    return requirement.status;
  }

  if (
    requirement?.required === null ||
    requirement?.required === undefined ||
    requirement?.available === null ||
    requirement?.available === undefined
  ) {
    return "unknown";
  }

  if (typeof requirement.shortage === "number") {
    return requirement.shortage > 0 ? "shortage" : "sufficient";
  }

  return requirement.available >= requirement.required ? "sufficient" : "shortage";
}

export function getRequiredAvailabilityShortage(
  requirement?: InventoryRequirementRecord | null
) {
  if (typeof requirement?.shortage === "number") {
    return requirement.shortage;
  }

  if (
    requirement?.required === null ||
    requirement?.required === undefined ||
    requirement?.available === null ||
    requirement?.available === undefined
  ) {
    return null;
  }

  return Math.max(requirement.required - requirement.available, 0);
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

export function buildInventoryLocationSummary(
  items: InventoryItemRecord[],
  location?: InventoryLocationReference | null
) {
  const summary = buildInventorySummary(items);

  return {
    itemCount: items.length,
    location,
    lowStockCount: summary.lowStock ?? 0,
    outOfStockCount: summary.outOfStock ?? 0,
    summary,
  };
}

export function groupInventoryMovementsByDate(
  movements: InventoryMovementRecord[],
  locale?: string
) {
  const groups = new Map<
    string,
    {
      dateKey: string;
      items: InventoryMovementRecord[];
      label: string;
    }
  >();

  movements.forEach((movement, index) => {
    const rawValue = movement.occurredAt ?? movement.createdAt;
    const date = rawValue ? new Date(rawValue) : null;
    const dateKey =
      date && !Number.isNaN(date.getTime())
        ? date.toISOString().slice(0, 10)
        : `unknown-${index}`;
    const label = formatInventoryDateLabel(rawValue, locale) ?? dateKey;
    const existing = groups.get(dateKey);

    if (existing) {
      existing.items.push(movement);
      return;
    }

    groups.set(dateKey, {
      dateKey,
      items: [movement],
      label,
    });
  });

  return [...groups.values()];
}

export function getWasteEntryItemName(
  entry?: WasteEntryRecord | null
) {
  return getInventoryItemName(entry?.inventoryItem);
}
