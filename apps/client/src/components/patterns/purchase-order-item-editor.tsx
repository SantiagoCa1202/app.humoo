import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { Button } from "@/components/primitives/button";
import { CurrencyInput, type CurrencyOption } from "@/components/primitives/currency-input";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { BaseCard } from "@/components/primitives/base-card";
import { QuantityInput, type QuantityUnitOption } from "@/components/primitives/quantity-input";
import { Text } from "@/components/primitives/text";
import { TextArea } from "@/components/primitives/text-area";
import {
  calculatePurchaseOrderItemTotal,
  createSupplierItemEntityOptions,
  formatPurchasingCurrency,
  getInventoryItemNameForPurchasing,
  getPurchaseOrderItemKey,
  getSupplierName,
  getUnitNameForPurchasing,
  type PurchaseOrderItemEditorValues,
  type PurchaseOrderItemValidationErrors,
  type SupplierItemRecord,
  type SupplierRecord,
} from "@/features/purchasing";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PurchaseOrderItemEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currency?: string | null;
  currencyOptions?: CurrencyOption<string>[];
  disabled?: boolean;
  errors?: PurchaseOrderItemValidationErrors;
  inventoryItems?: EntityPickerOption<string>[];
  onChange: (value: PurchaseOrderItemEditorValues) => void;
  onRemove?: () => void;
  supplier?: SupplierRecord | null;
  supplierItems?: SupplierItemRecord[];
  unitOptions?: QuantityUnitOption<string>[];
  value: PurchaseOrderItemEditorValues;
};

export function PurchaseOrderItemEditor({
  accessibilityLabel,
  compact = false,
  currency,
  currencyOptions,
  disabled = false,
  errors,
  inventoryItems,
  onChange,
  onRemove,
  supplier,
  supplierItems = [],
  unitOptions,
  value,
}: PurchaseOrderItemEditorProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const supplierItemOptions = createSupplierItemEntityOptions(
    supplierItems,
    (price, code) => formatPurchasingCurrency(price, code, i18n.language)
  );
  const resolvedCurrency = value.currency ?? currency ?? supplier?.currency ?? null;
  const resolvedCurrencyOptions =
    currencyOptions?.length
      ? currencyOptions
      : resolvedCurrency
      ? [{ label: resolvedCurrency, value: resolvedCurrency }]
      : [];
  const lineTotal = calculatePurchaseOrderItemTotal(value);
  const selectedSupplierItem =
    supplierItems.find((item) => item.id === value.supplierItemId) ?? value.supplierItem ?? null;
  const selectedSupplierName = getSupplierName(supplier ?? selectedSupplierItem?.supplier);
  const supplierMismatch =
    supplier?.id &&
    selectedSupplierItem?.supplierId &&
    selectedSupplierItem.supplierId !== supplier.id;

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ??
        t("purchasing.forms.purchaseOrderItem.accessibilityLabel", {
          item: value.itemName ?? getInventoryItemNameForPurchasing(value.inventoryItem) ?? "",
        })
      }
      padding="lg"
      variant="muted"
    >
      <View style={{ gap: theme.spacing[4] }}>
        {supplierMismatch ? (
          <AlertCard
            description={t("purchasing.forms.purchaseOrderItem.warnings.supplierMismatch")}
            title={t("purchasing.forms.purchaseOrderItem.warnings.title")}
            tone="warning"
            variant="muted"
          />
        ) : null}
        {supplierItems.length ? (
          <EntityPicker
            accessibilityLabel={t(
              "purchasing.forms.purchaseOrderItem.fields.supplierItem.accessibilityLabel"
            )}
            disabled={disabled}
            entities={supplierItemOptions}
            error={errors?.supplierItemId}
            label={t("purchasing.forms.purchaseOrderItem.fields.supplierItem.label")}
            onChange={(supplierItemId) => {
              const nextSupplierItem =
                supplierItems.find((item) => item.id === supplierItemId) ?? null;

              onChange({
                ...value,
                currency: value.currency ?? nextSupplierItem?.currency ?? resolvedCurrency,
                inventoryItem: nextSupplierItem?.inventoryItem ?? value.inventoryItem ?? null,
                inventoryItemId:
                  nextSupplierItem?.inventoryItemId ??
                  nextSupplierItem?.inventoryItem?.id ??
                  value.inventoryItemId ??
                  null,
                itemName:
                  nextSupplierItem?.supplierName ??
                  nextSupplierItem?.inventoryItem?.name ??
                  value.itemName ??
                  null,
                supplierItem: nextSupplierItem,
                supplierItemId,
                supplierSku: nextSupplierItem?.supplierSku ?? value.supplierSku ?? null,
                total:
                  value.total ??
                  calculatePurchaseOrderItemTotal({
                    quantity: value.quantity,
                    unitPrice: nextSupplierItem?.price ?? value.unitPrice,
                  }),
                unit: nextSupplierItem?.unit ?? value.unit ?? null,
                unitId:
                  nextSupplierItem?.unitId ??
                  nextSupplierItem?.unit?.id ??
                  value.unitId ??
                  null,
                unitPrice: nextSupplierItem?.price ?? value.unitPrice ?? null,
              });
            }}
            placeholder={t("purchasing.forms.purchaseOrderItem.fields.supplierItem.placeholder")}
            value={value.supplierItemId ?? undefined}
          />
        ) : null}
        {inventoryItems?.length ? (
          <EntityPicker
            accessibilityLabel={t(
              "purchasing.forms.purchaseOrderItem.fields.inventoryItem.accessibilityLabel"
            )}
            disabled={disabled}
            entities={inventoryItems}
            error={errors?.inventoryItemId}
            label={t("purchasing.forms.purchaseOrderItem.fields.inventoryItem.label")}
            onChange={(inventoryItemId) =>
              onChange({
                ...value,
                inventoryItemId,
              })
            }
            placeholder={t("purchasing.forms.purchaseOrderItem.fields.inventoryItem.placeholder")}
            value={value.inventoryItemId ?? undefined}
          />
        ) : null}
        {selectedSupplierName ? (
          <Text selectable tone="secondary" variant="bodySmall">
            {t("purchasing.forms.purchaseOrderItem.fields.supplierValue", {
              value: selectedSupplierName,
            })}
          </Text>
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: compact ? 220 : 320 }}>
            {unitOptions?.length ? (
              <QuantityInput
                accessibilityLabel={t(
                  "purchasing.forms.purchaseOrderItem.fields.quantity.accessibilityLabel"
                )}
                disabled={disabled}
                error={errors?.quantity}
                label={t("purchasing.forms.purchaseOrderItem.fields.quantity.label")}
                min={0}
                onChange={(quantity) => onChange({ ...value, quantity })}
                onUnitChange={(unitId) => {
                  const matchedUnit = unitOptions.find((option) => option.value === unitId);
                  onChange({
                    ...value,
                    unit: matchedUnit ? { id: unitId, symbol: matchedUnit.label } : null,
                    unitId,
                  });
                }}
                step={0.01}
                unit={value.unitId ?? undefined}
                units={unitOptions}
                value={value.quantity ?? 0}
              />
            ) : (
              <View style={{ gap: theme.spacing[2] }}>
                <Text tone="muted" variant="caption">
                  {t("purchasing.forms.purchaseOrderItem.fields.quantity.label")}
                </Text>
                <Text selectable variant="bodySmall">
                  {value.quantity ?? 0}
                </Text>
                {getUnitNameForPurchasing(value.unit) ? (
                  <Text selectable tone="secondary" variant="caption">
                    {getUnitNameForPurchasing(value.unit)}
                  </Text>
                ) : null}
              </View>
            )}
          </View>
          <View style={{ flex: 1, minWidth: compact ? 220 : 320 }}>
            <CurrencyInput
              accessibilityLabel={t(
                "purchasing.forms.purchaseOrderItem.fields.unitPrice.accessibilityLabel"
              )}
              currencies={resolvedCurrencyOptions}
              currency={resolvedCurrency ?? undefined}
              disabled={disabled}
              error={errors?.unitPrice}
              helperText={
                lineTotal
                  ? t("purchasing.forms.purchaseOrderItem.fields.lineTotal.helper", {
                      value:
                        formatPurchasingCurrency(lineTotal, resolvedCurrency, i18n.language) ??
                        "",
                    })
                  : undefined
              }
              label={t("purchasing.forms.purchaseOrderItem.fields.unitPrice.label")}
              onChange={(unitPrice) => onChange({ ...value, unitPrice })}
              onCurrencyChange={(nextCurrency) =>
                onChange({ ...value, currency: nextCurrency })
              }
              value={value.unitPrice ?? 0}
            />
          </View>
        </View>
        <TextArea
          accessibilityLabel={t("purchasing.forms.purchaseOrderItem.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={errors?.notes}
          label={t("purchasing.forms.purchaseOrderItem.fields.notes.label")}
          onChangeText={(notes) => onChange({ ...value, notes })}
          placeholder={t("purchasing.forms.purchaseOrderItem.fields.notes.placeholder")}
          value={value.notes ?? ""}
        />
        <View
          style={{
            alignItems: "center",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[3],
            justifyContent: "space-between",
          }}
        >
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("purchasing.forms.purchaseOrderItem.fields.lineTotal.label")}
            </Text>
            <Text selectable tone="primary" variant="title">
              {formatPurchasingCurrency(lineTotal, resolvedCurrency, i18n.language) ??
                t("purchasing.forms.purchaseOrderItem.fields.lineTotal.empty")}
            </Text>
          </View>
          {onRemove ? (
            <Button
              label={t("purchasing.actions.removeItem")}
              onPress={onRemove}
              size="sm"
              variant="destructive"
            />
          ) : null}
        </View>
      </View>
    </BaseCard>
  );
}
