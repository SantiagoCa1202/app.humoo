import type { CurrencyOption } from "@/components/primitives/currency-input";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { QuantityUnitOption } from "@/components/primitives/quantity-input";
import type {
  InventoryItemRecord,
  InventoryLocationReference,
  InventoryUnitReference,
} from "@/features/inventory";
import type {
  PurchaseOrderItemRecord,
  PurchaseOrderRecord,
  PurchaseOrderStatus,
  SupplierItemRecord,
  SupplierRecord,
  SupplierStatus,
} from "@/features/purchasing/types";

export type SupplierEditorMode = "create" | "edit";
export type SupplierItemEditorMode = "create" | "edit";
export type PurchaseOrderEditorMode = "create" | "edit";

export type SupplierEditorValues = SupplierRecord & {
  addressLine1?: string | null;
  addressLine2?: string | null;
  city?: string | null;
  contactPhone?: string | null;
  countryCode?: string | null;
  currency?: string | null;
  email?: string | null;
  leadTimeDays?: number | null;
  minimumOrderAmount?: number | null;
  paymentTerms?: string | null;
  phone?: string | null;
  postalCode?: string | null;
  state?: string | null;
  taxId?: string | null;
  version?: number | null;
};

export type SupplierEditorValidationErrors = Partial<
  Record<
    | "name"
    | "code"
    | "email"
    | "phone"
    | "contactName"
    | "contactEmail"
    | "contactPhone"
    | "addressLine1"
    | "addressLine2"
    | "city"
    | "state"
    | "postalCode"
    | "countryCode"
    | "taxId"
    | "leadTimeDays"
    | "minimumOrderAmount"
    | "paymentTerms"
    | "currency"
    | "status"
    | "notes"
    | "form",
    string
  >
>;

export type SupplierItemEditorValues = SupplierItemRecord & {
  clientId?: string | null;
  currency?: string | null;
  inventoryItemId?: string | null;
  packQuantity?: number | null;
  price?: number | null;
  unitId?: string | null;
};

export type SupplierItemEditorValidationErrors = Partial<
  Record<
    | "supplierId"
    | "inventoryItemId"
    | "supplierSku"
    | "supplierName"
    | "brand"
    | "unitId"
    | "packQuantity"
    | "packUnitId"
    | "price"
    | "currency"
    | "minimumOrderQuantity"
    | "leadTimeDays"
    | "form",
    string
  >
>;

export type PurchaseOrderItemEditorValues = PurchaseOrderItemRecord & {
  clientId?: string | null;
  inventoryItemId?: string | null;
  quantity?: number | null;
  unitId?: string | null;
  unitPrice?: number | null;
};

export type PurchaseOrderItemValidationErrors = Partial<
  Record<
    | "supplierItemId"
    | "inventoryItemId"
    | "quantity"
    | "unitId"
    | "unitPrice"
    | "currency"
    | "notes"
    | "form",
    string
  >
>;

export type PurchaseOrderEditorValues = PurchaseOrderRecord & {
  clientId?: string | null;
  items: PurchaseOrderItemEditorValues[];
  version?: number | null;
};

export type PurchaseOrderEditorValidationErrors = Partial<
  Record<
    | "supplierId"
    | "inventoryLocationId"
    | "supplierReference"
    | "expectedAt"
    | "currency"
    | "notes"
    | "items"
    | "form",
    string
  >
> & {
  lineItems?: Record<string, PurchaseOrderItemValidationErrors>;
};

export const SUPPLIER_STATUS_VALUES = [
  "active",
  "inactive",
] as const satisfies readonly SupplierStatus[];

export const SUPPLIER_PAYMENT_TERM_VALUES = [
  "due_on_receipt",
  "net_15",
  "net_30",
  "net_60",
] as const;

let purchasingDraftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function normalizeNumber(value?: number | null) {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return null;
  }

  return value;
}

function normalizePositiveNumber(value?: number | null) {
  const normalized = normalizeNumber(value);
  return normalized === null ? null : Math.max(0, normalized);
}

function roundCurrency(value: number) {
  return Math.round(value * 100) / 100;
}

function scaleQuantity(value?: number | null) {
  const normalized = normalizePositiveNumber(value);
  return normalized === null ? null : Math.round(normalized * 10000);
}

function scaleUnitPrice(value?: number | null) {
  const normalized = normalizePositiveNumber(value);
  return normalized === null ? null : Math.round(normalized * 10000);
}

export function createPurchasingDraftKey(
  prefix: "supplier" | "supplier-item" | "purchase-order" | "purchase-order-item"
) {
  purchasingDraftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${purchasingDraftCounter}`;
}

export function getPurchaseOrderItemKey(
  item: Pick<PurchaseOrderItemEditorValues, "clientId" | "id" | "itemName">
) {
  return (
    item.id ??
    item.clientId ??
    item.itemName ??
    createPurchasingDraftKey("purchase-order-item")
  );
}

export function calculatePurchaseOrderItemTotal(
  item: Pick<PurchaseOrderItemEditorValues, "quantity" | "unitPrice">
) {
  const quantityScaled = scaleQuantity(item.quantity);
  const unitPriceScaled = scaleUnitPrice(item.unitPrice);

  if (quantityScaled === null || unitPriceScaled === null) {
    return null;
  }

  const cents = Math.round((quantityScaled * unitPriceScaled) / 1000000);
  return cents / 100;
}

export function buildPurchaseOrderDraftTotals(
  values: Pick<
    PurchaseOrderEditorValues,
    "items" | "discount" | "shipping" | "tax" | "currency"
  >
) {
  const subtotalCents = values.items.reduce((total, item) => {
    const itemTotal = calculatePurchaseOrderItemTotal(item);
    return total + Math.round((itemTotal ?? 0) * 100);
  }, 0);
  const discountCents = Math.round((normalizePositiveNumber(values.discount) ?? 0) * 100);
  const shippingCents = Math.round((normalizePositiveNumber(values.shipping) ?? 0) * 100);
  const taxCents = Math.round((normalizePositiveNumber(values.tax) ?? 0) * 100);
  const totalCents = subtotalCents - discountCents + shippingCents + taxCents;

  return {
    currency: values.currency ?? null,
    discount: roundCurrency(discountCents / 100),
    shipping: roundCurrency(shippingCents / 100),
    subtotal: roundCurrency(subtotalCents / 100),
    tax: roundCurrency(taxCents / 100),
    total: roundCurrency(totalCents / 100),
  };
}

export function createSupplierEditorValues(
  values?: Partial<SupplierEditorValues>
): SupplierEditorValues {
  return {
    addressLine1: values?.addressLine1 ?? null,
    addressLine2: values?.addressLine2 ?? null,
    city: values?.city ?? null,
    code: values?.code ?? null,
    companyName: values?.companyName ?? null,
    contactEmail: values?.contactEmail ?? null,
    contactName: values?.contactName ?? null,
    contactPhone: values?.contactPhone ?? null,
    countryCode: values?.countryCode ?? null,
    createdAt: values?.createdAt ?? null,
    createdBy: values?.createdBy ?? null,
    currency: values?.currency ?? "USD",
    email: values?.email ?? null,
    id: values?.id ?? null,
    leadTimeDays: values?.leadTimeDays ?? null,
    metadata: values?.metadata ?? null,
    minimumOrderAmount: values?.minimumOrderAmount ?? null,
    name: values?.name ?? "",
    notes: values?.notes ?? null,
    paymentTerms: values?.paymentTerms ?? null,
    phone: values?.phone ?? null,
    postalCode: values?.postalCode ?? null,
    preferred: values?.preferred ?? false,
    state: values?.state ?? null,
    status: values?.status ?? "active",
    taxId: values?.taxId ?? null,
    updatedAt: values?.updatedAt ?? null,
    version: values?.version ?? null,
    website: values?.website ?? null,
  };
}

export function normalizeSupplierEditorValues(
  values: SupplierEditorValues
): SupplierEditorValues {
  return {
    ...values,
    addressLine1: trimOrNull(values.addressLine1),
    addressLine2: trimOrNull(values.addressLine2),
    city: trimOrNull(values.city),
    code: trimOrNull(values.code),
    companyName: trimOrNull(values.companyName),
    contactEmail: trimOrNull(values.contactEmail)?.toLowerCase() ?? null,
    contactName: trimOrNull(values.contactName),
    contactPhone: trimOrNull(values.contactPhone),
    countryCode: trimOrNull(values.countryCode)?.toUpperCase() ?? null,
    currency: trimOrNull(values.currency)?.toUpperCase() ?? null,
    email: trimOrNull(values.email)?.toLowerCase() ?? null,
    leadTimeDays:
      typeof values.leadTimeDays === "number" && Number.isFinite(values.leadTimeDays)
        ? Math.max(0, Math.trunc(values.leadTimeDays))
        : null,
    minimumOrderAmount: normalizePositiveNumber(values.minimumOrderAmount),
    name: (values.name ?? "").trim(),
    notes: trimOrNull(values.notes),
    paymentTerms: trimOrNull(values.paymentTerms),
    phone: trimOrNull(values.phone),
    postalCode: trimOrNull(values.postalCode),
    state: trimOrNull(values.state),
    taxId: trimOrNull(values.taxId),
    website: trimOrNull(values.website),
  };
}

export function validateSupplierEditorValues(
  values: SupplierEditorValues,
  t: (key: string) => string
): SupplierEditorValidationErrors {
  const errors: SupplierEditorValidationErrors = {};

  if (!values.name?.trim()) {
    errors.name = t("purchasing.forms.supplier.errors.nameRequired");
  }

  if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
    errors.email = t("purchasing.forms.supplier.errors.emailInvalid");
  }

  if (values.contactEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.contactEmail)) {
    errors.contactEmail = t("purchasing.forms.supplier.errors.contactEmailInvalid");
  }

  if (
    values.leadTimeDays !== null &&
    values.leadTimeDays !== undefined &&
    (!Number.isFinite(values.leadTimeDays) || values.leadTimeDays < 0)
  ) {
    errors.leadTimeDays = t("purchasing.forms.supplier.errors.leadTimeInvalid");
  }

  if (
    values.minimumOrderAmount !== null &&
    values.minimumOrderAmount !== undefined &&
    (!Number.isFinite(values.minimumOrderAmount) || values.minimumOrderAmount < 0)
  ) {
    errors.minimumOrderAmount = t("purchasing.forms.supplier.errors.minimumOrderInvalid");
  }

  return errors;
}

export function hasSupplierEditorErrors(errors?: SupplierEditorValidationErrors | null) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createSupplierItemEditorValues(
  values?: Partial<SupplierItemEditorValues>
): SupplierItemEditorValues {
  return {
    active: values?.active ?? true,
    baseUnitFactor: values?.baseUnitFactor ?? null,
    brand: values?.brand ?? null,
    clientId: values?.clientId ?? createPurchasingDraftKey("supplier-item"),
    createdAt: values?.createdAt ?? null,
    currency: values?.currency ?? "USD",
    id: values?.id ?? null,
    inventoryItem: values?.inventoryItem ?? null,
    inventoryItemId: values?.inventoryItemId ?? values?.inventoryItem?.id ?? null,
    leadTimeDays: values?.leadTimeDays ?? null,
    metadata: values?.metadata ?? null,
    minimumOrderQuantity: values?.minimumOrderQuantity ?? null,
    packQuantity: values?.packQuantity ?? null,
    packUnit: values?.packUnit ?? null,
    packUnitId: values?.packUnitId ?? values?.packUnit?.id ?? null,
    preferred: values?.preferred ?? false,
    price: values?.price ?? null,
    priceUpdatedAt: values?.priceUpdatedAt ?? null,
    supplier: values?.supplier ?? null,
    supplierId: values?.supplierId ?? values?.supplier?.id ?? null,
    supplierName: values?.supplierName ?? null,
    supplierSku: values?.supplierSku ?? null,
    unit: values?.unit ?? null,
    unitId: values?.unitId ?? values?.unit?.id ?? null,
    updatedAt: values?.updatedAt ?? null,
  };
}

export function normalizeSupplierItemEditorValues(
  values: SupplierItemEditorValues
): SupplierItemEditorValues {
  return {
    ...values,
    baseUnitFactor: normalizePositiveNumber(values.baseUnitFactor),
    brand: trimOrNull(values.brand),
    currency: trimOrNull(values.currency)?.toUpperCase() ?? null,
    inventoryItemId: trimOrNull(values.inventoryItemId),
    leadTimeDays:
      typeof values.leadTimeDays === "number" && Number.isFinite(values.leadTimeDays)
        ? Math.max(0, Math.trunc(values.leadTimeDays))
        : null,
    minimumOrderQuantity: normalizePositiveNumber(values.minimumOrderQuantity),
    packQuantity: normalizePositiveNumber(values.packQuantity),
    packUnitId: trimOrNull(values.packUnitId),
    price: normalizePositiveNumber(values.price),
    supplierId: trimOrNull(values.supplierId),
    supplierName: trimOrNull(values.supplierName),
    supplierSku: trimOrNull(values.supplierSku),
    unitId: trimOrNull(values.unitId),
  };
}

export function validateSupplierItemEditorValues(
  values: SupplierItemEditorValues,
  t: (key: string) => string
): SupplierItemEditorValidationErrors {
  const errors: SupplierItemEditorValidationErrors = {};

  if (!values.supplierId?.trim()) {
    errors.supplierId = t("purchasing.forms.supplierItem.errors.supplierRequired");
  }

  if (!values.inventoryItemId?.trim()) {
    errors.inventoryItemId = t("purchasing.forms.supplierItem.errors.inventoryItemRequired");
  }

  if (
    values.price !== null &&
    values.price !== undefined &&
    (!Number.isFinite(values.price) || values.price < 0)
  ) {
    errors.price = t("purchasing.forms.supplierItem.errors.priceInvalid");
  }

  if (
    values.minimumOrderQuantity !== null &&
    values.minimumOrderQuantity !== undefined &&
    (!Number.isFinite(values.minimumOrderQuantity) || values.minimumOrderQuantity < 0)
  ) {
    errors.minimumOrderQuantity = t(
      "purchasing.forms.supplierItem.errors.minimumQuantityInvalid"
    );
  }

  if (
    values.packQuantity !== null &&
    values.packQuantity !== undefined &&
    (!Number.isFinite(values.packQuantity) || values.packQuantity < 0)
  ) {
    errors.packQuantity = t("purchasing.forms.supplierItem.errors.packQuantityInvalid");
  }

  return errors;
}

export function hasSupplierItemEditorErrors(errors?: SupplierItemEditorValidationErrors | null) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createPurchaseOrderItemEditorValues(
  values?: Partial<PurchaseOrderItemEditorValues>
): PurchaseOrderItemEditorValues {
  const draft = {
    clientId: values?.clientId ?? createPurchasingDraftKey("purchase-order-item"),
    createdAt: values?.createdAt ?? null,
    currency: values?.currency ?? "USD",
    discount: values?.discount ?? 0,
    id: values?.id ?? null,
    inventoryItem: values?.inventoryItem ?? null,
    inventoryItemId: values?.inventoryItemId ?? values?.inventoryItem?.id ?? null,
    itemName: values?.itemName ?? null,
    notes: values?.notes ?? null,
    position: values?.position ?? null,
    purchaseOrderId: values?.purchaseOrderId ?? null,
    quantity: values?.quantity ?? null,
    quantityCancelled: values?.quantityCancelled ?? 0,
    quantityReceived: values?.quantityReceived ?? 0,
    sourceId: values?.sourceId ?? null,
    sourceType: values?.sourceType ?? null,
    status: values?.status ?? "open",
    supplierItem: values?.supplierItem ?? null,
    supplierItemId: values?.supplierItemId ?? values?.supplierItem?.id ?? null,
    supplierSku: values?.supplierSku ?? null,
    tax: values?.tax ?? 0,
    total: values?.total ?? null,
    unit: values?.unit ?? null,
    unitId: values?.unitId ?? values?.unit?.id ?? null,
    unitPrice: values?.unitPrice ?? null,
    updatedAt: values?.updatedAt ?? null,
  } satisfies PurchaseOrderItemEditorValues;

  return {
    ...draft,
    total: draft.total ?? calculatePurchaseOrderItemTotal(draft),
  };
}

export function normalizePurchaseOrderItemEditorValues(
  values: PurchaseOrderItemEditorValues
): PurchaseOrderItemEditorValues {
  const normalized = {
    ...values,
    currency: trimOrNull(values.currency)?.toUpperCase() ?? null,
    inventoryItemId: trimOrNull(values.inventoryItemId),
    itemName: trimOrNull(values.itemName),
    notes: trimOrNull(values.notes),
    quantity: normalizePositiveNumber(values.quantity),
    supplierItemId: trimOrNull(values.supplierItemId),
    supplierSku: trimOrNull(values.supplierSku),
    unitId: trimOrNull(values.unitId),
    unitPrice: normalizePositiveNumber(values.unitPrice),
  } satisfies PurchaseOrderItemEditorValues;

  return {
    ...normalized,
    total: calculatePurchaseOrderItemTotal(normalized),
  };
}

export function validatePurchaseOrderItemEditorValues(
  values: PurchaseOrderItemEditorValues,
  selectedSupplierId: string | null | undefined,
  t: (key: string) => string
): PurchaseOrderItemValidationErrors {
  const errors: PurchaseOrderItemValidationErrors = {};

  if (!values.supplierItemId?.trim() && !values.inventoryItemId?.trim()) {
    errors.supplierItemId = t("purchasing.forms.purchaseOrderItem.errors.itemRequired");
  }

  if (
    values.supplierItem?.supplierId &&
    selectedSupplierId &&
    values.supplierItem.supplierId !== selectedSupplierId
  ) {
    errors.supplierItemId = t("purchasing.forms.purchaseOrderItem.errors.supplierMismatch");
  }

  if (
    values.quantity === null ||
    values.quantity === undefined ||
    !Number.isFinite(values.quantity) ||
    values.quantity <= 0
  ) {
    errors.quantity = t("purchasing.forms.purchaseOrderItem.errors.quantityRequired");
  }

  if (!values.unitId?.trim()) {
    errors.unitId = t("purchasing.forms.purchaseOrderItem.errors.unitRequired");
  }

  if (
    values.unitPrice === null ||
    values.unitPrice === undefined ||
    !Number.isFinite(values.unitPrice) ||
    values.unitPrice < 0
  ) {
    errors.unitPrice = t("purchasing.forms.purchaseOrderItem.errors.priceRequired");
  }

  return errors;
}

export function hasPurchaseOrderItemEditorErrors(
  errors?: PurchaseOrderItemValidationErrors | null
) {
  return Boolean(errors && Object.values(errors).some(Boolean));
}

export function createPurchaseOrderEditorValues(
  values?: Partial<PurchaseOrderEditorValues>
): PurchaseOrderEditorValues {
  const items = (values?.items ?? []).map((item, index) =>
    createPurchaseOrderItemEditorValues({
      ...item,
      position: item.position ?? index + 1,
    })
  );
  const draftTotals = buildPurchaseOrderDraftTotals({
    currency: values?.currency ?? "USD",
    discount: values?.discount ?? 0,
    items,
    shipping: values?.shipping ?? 0,
    tax: values?.tax ?? 0,
  });

  return {
    approvedAt: values?.approvedAt ?? null,
    approvedBy: values?.approvedBy ?? null,
    cancelledAt: values?.cancelledAt ?? null,
    cancelledBy: values?.cancelledBy ?? null,
    cancellationReason: values?.cancellationReason ?? null,
    clientId: values?.clientId ?? createPurchasingDraftKey("purchase-order"),
    confirmedAt: values?.confirmedAt ?? null,
    createdAt: values?.createdAt ?? null,
    createdBy: values?.createdBy ?? null,
    currency: values?.currency ?? "USD",
    discount: values?.discount ?? 0,
    eventId: values?.eventId ?? null,
    expectedAt: values?.expectedAt ?? null,
    id: values?.id ?? null,
    inventoryLocation: values?.inventoryLocation ?? null,
    inventoryLocationId:
      values?.inventoryLocationId ?? values?.inventoryLocation?.id ?? null,
    itemCount: values?.itemCount ?? items.length,
    items,
    metadata: values?.metadata ?? null,
    notes: values?.notes ?? null,
    number: values?.number ?? null,
    orderedAt: values?.orderedAt ?? null,
    paymentTerms: values?.paymentTerms ?? null,
    receivedAt: values?.receivedAt ?? null,
    receivedItemCount: values?.receivedItemCount ?? null,
    shipping: values?.shipping ?? 0,
    source: values?.source ?? "manual",
    sourceId: values?.sourceId ?? null,
    sourceType: values?.sourceType ?? null,
    status: values?.status ?? "draft",
    submittedAt: values?.submittedAt ?? null,
    subtotal: values?.subtotal ?? draftTotals.subtotal,
    supplier: values?.supplier ?? null,
    supplierId: values?.supplierId ?? values?.supplier?.id ?? null,
    supplierReference: values?.supplierReference ?? null,
    tax: values?.tax ?? 0,
    total: values?.total ?? draftTotals.total,
    updatedAt: values?.updatedAt ?? null,
    updatedBy: values?.updatedBy ?? null,
    version: values?.version ?? null,
  };
}

export function normalizePurchaseOrderEditorValues(
  values: PurchaseOrderEditorValues
): PurchaseOrderEditorValues {
  const items = values.items.map((item, index) => ({
    ...normalizePurchaseOrderItemEditorValues(item),
    position: index + 1,
  }));
  const totals = buildPurchaseOrderDraftTotals({
    currency: values.currency,
    discount: values.discount,
    items,
    shipping: values.shipping,
    tax: values.tax,
  });

  return {
    ...values,
    currency: trimOrNull(values.currency)?.toUpperCase() ?? null,
    expectedAt: trimOrNull(values.expectedAt),
    inventoryLocationId: trimOrNull(values.inventoryLocationId),
    itemCount: items.length,
    items,
    notes: trimOrNull(values.notes),
    paymentTerms: trimOrNull(values.paymentTerms),
    shipping: normalizePositiveNumber(values.shipping) ?? 0,
    subtotal: totals.subtotal,
    supplierId: trimOrNull(values.supplierId),
    supplierReference: trimOrNull(values.supplierReference),
    tax: normalizePositiveNumber(values.tax) ?? 0,
    total: totals.total,
  };
}

export function validatePurchaseOrderEditorValues(
  values: PurchaseOrderEditorValues,
  t: (key: string) => string
): PurchaseOrderEditorValidationErrors {
  const errors: PurchaseOrderEditorValidationErrors = {};

  if (!values.supplierId?.trim()) {
    errors.supplierId = t("purchasing.forms.purchaseOrder.errors.supplierRequired");
  }

  if (!values.items.length) {
    errors.items = t("purchasing.forms.purchaseOrder.errors.itemsRequired");
  }

  const lineItems = values.items.reduce<Record<string, PurchaseOrderItemValidationErrors>>(
    (result, item) => {
      const itemErrors = validatePurchaseOrderItemEditorValues(item, values.supplierId, t);

      if (hasPurchaseOrderItemEditorErrors(itemErrors)) {
        result[getPurchaseOrderItemKey(item)] = itemErrors;
      }

      return result;
    },
    {}
  );

  if (Object.keys(lineItems).length > 0) {
    errors.lineItems = lineItems;
  }

  return errors;
}

export function hasPurchaseOrderEditorErrors(
  errors?: PurchaseOrderEditorValidationErrors | null
) {
  return Boolean(
    errors &&
      (errors.form ||
        errors.supplierId ||
        errors.inventoryLocationId ||
        errors.supplierReference ||
        errors.expectedAt ||
        errors.currency ||
        errors.notes ||
        errors.items ||
        (errors.lineItems && Object.keys(errors.lineItems).length > 0))
  );
}

export function createSupplierEntityOptions(
  suppliers: SupplierRecord[],
  formatLeadTime: (leadTimeDays: number | null | undefined) => string | null,
  formatMinimumOrder: (amount: number | null | undefined, currency?: string | null) => string | null,
  preferredLabel: string
): EntityPickerOption<string>[] {
  return suppliers.map((supplier) => {
    const metadata = [
      supplier.preferred ? preferredLabel : null,
      formatLeadTime(supplier.leadTimeDays),
      formatMinimumOrder(supplier.minimumOrderAmount, supplier.currency),
    ]
      .filter(Boolean)
      .join(" - ");

    return {
      label: supplier.name ?? supplier.companyName ?? supplier.code ?? supplier.id ?? "",
      metadata: metadata || undefined,
      value: supplier.id ?? "",
    };
  });
}

export function createSupplierItemEntityOptions(
  items: SupplierItemRecord[],
  formatPrice: (price: number | null | undefined, currency?: string | null) => string | null
): EntityPickerOption<string>[] {
  return items
    .filter((item) => Boolean(item.id))
    .map((item) => ({
      label:
        item.supplierName ??
        item.inventoryItem?.name ??
        item.supplierSku ??
        item.id ??
        "",
      metadata:
        [item.supplierSku, formatPrice(item.price, item.currency)]
          .filter(Boolean)
          .join(" - ") || undefined,
      value: item.id ?? "",
    }));
}

export type SupplierCurrencyOption = CurrencyOption<string>;
export type SupplierItemUnitOption = QuantityUnitOption<string>;
export type PurchasingInventoryItemOption = EntityPickerOption<string>;
export type PurchasingLocationOption = EntityPickerOption<string>;

export function getInventoryItemNameForPurchasing(
  item?: InventoryItemRecord | null
) {
  return item?.name?.trim() || item?.sku?.trim() || item?.id || null;
}

export function getLocationNameForPurchasing(
  location?: InventoryLocationReference | null
) {
  return location?.name?.trim() || location?.key?.trim() || location?.id || null;
}

export function getUnitNameForPurchasing(unit?: InventoryUnitReference | null) {
  return unit?.symbol?.trim() || unit?.name?.trim() || unit?.key?.trim() || null;
}
