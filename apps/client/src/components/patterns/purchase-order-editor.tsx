import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { FormCard } from "@/components/patterns/form-card";
import { PurchaseOrderItemEditor } from "@/components/patterns/purchase-order-item-editor";
import { PurchaseOrderSummary } from "@/components/patterns/purchase-order-summary";
import { PurchaseOrderStatusBadge } from "@/components/patterns/purchase-order-status-badge";
import { SupplierSelector } from "@/components/patterns/supplier-selector";
import { Button } from "@/components/primitives/button";
import type { CurrencyOption } from "@/components/primitives/currency-input";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { NumberField } from "@/components/primitives/number-field";
import { Select } from "@/components/primitives/select";
import { Text } from "@/components/primitives/text";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createPurchaseOrderEditorValues,
  createPurchaseOrderItemEditorValues,
  getPurchaseOrderItemKey,
  getPurchaseOrderStatusValue,
  hasPurchaseOrderEditorErrors,
  normalizePurchaseOrderEditorValues,
  validatePurchaseOrderEditorValues,
  type PurchaseOrderEditorMode,
  type PurchaseOrderEditorValidationErrors,
  type PurchaseOrderEditorValues,
  type SupplierItemRecord,
  type SupplierRecord,
} from "@/features/purchasing";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: PurchaseOrderEditorValidationErrors,
  externalErrors?: PurchaseOrderEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
    lineItems: {
      ...(localErrors.lineItems ?? {}),
      ...(externalErrors.lineItems ?? {}),
    },
  } satisfies PurchaseOrderEditorValidationErrors;
}

export type PurchaseOrderEditorProps = {
  accessibilityLabel?: string;
  currencyOptions?: CurrencyOption<string>[];
  disabled?: boolean;
  initialValues?: Partial<PurchaseOrderEditorValues>;
  inventoryItems?: EntityPickerOption<string>[];
  locationOptions?: EntityPickerOption<string>[];
  mode?: PurchaseOrderEditorMode;
  onCancel?: () => void;
  onSubmit: (value: PurchaseOrderEditorValues) => void | Promise<void>;
  submitting?: boolean;
  supplierItems?: SupplierItemRecord[];
  supplierOptions: SupplierRecord[];
  unitOptions?: { label: string; value: string }[];
  validationErrors?: PurchaseOrderEditorValidationErrors;
};

export function PurchaseOrderEditor({
  accessibilityLabel,
  currencyOptions,
  disabled = false,
  initialValues,
  inventoryItems,
  locationOptions,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  supplierItems = [],
  supplierOptions,
  unitOptions,
  validationErrors,
}: PurchaseOrderEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createPurchaseOrderEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<PurchaseOrderEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<PurchaseOrderEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedSupplier =
    supplierOptions.find((supplier) => supplier.id === values.supplierId) ??
    values.supplier ??
    null;
  const supplierSpecificItems = values.supplierId
    ? supplierItems.filter(
        (item) =>
          item.supplierId === values.supplierId || item.supplier?.id === values.supplierId
      )
    : supplierItems;
  const incompatibleItems = values.items.filter((item) => {
    const supplierItemSupplierId = item.supplierItem?.supplierId ?? null;

    return Boolean(
      values.supplierId &&
        supplierItemSupplierId &&
        supplierItemSupplierId !== values.supplierId
    );
  });
  const resolvedTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC";
  const existingStatus = getPurchaseOrderStatusValue(values.status);
  const showCurrencyField = Boolean(currencyOptions?.length);
  const canEditOrder = mode === "create" || values.status === "draft";
  const canEditCommercialFields = canEditOrder;

  const handleSubmit = async () => {
    const normalized = normalizePurchaseOrderEditorValues(values);
    const nextErrors = validatePurchaseOrderEditorValues(normalized, t);

    if (hasPurchaseOrderEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  const addItem = () => {
    setValues((current) => ({
      ...current,
      items: [
        ...current.items,
        createPurchaseOrderItemEditorValues({
          currency: current.currency ?? resolvedSupplier?.currency ?? "USD",
        }),
      ],
    }));
  };

  return (
    <FormCard
      accessibilityLabel={
        accessibilityLabel ?? t("purchasing.forms.purchaseOrder.accessibilityLabel")
      }
      cancelLabel={t("purchasing.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={canEditOrder ? handleSubmit : undefined}
      submitLabel={
        mode === "edit"
          ? t("purchasing.actions.saveChanges")
          : t("purchasing.actions.createPurchaseOrder")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("purchasing.forms.purchaseOrder.editTitle")
          : t("purchasing.forms.purchaseOrder.createTitle")
      }
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        {existingStatus ? (
          <PurchaseOrderStatusBadge showDot={false} size="sm" status={existingStatus} />
        ) : null}
        {values.number ? (
          <TextField
            editable={false}
            label={t("purchasing.forms.purchaseOrder.fields.number.label")}
            value={values.number}
          />
        ) : null}
        <SupplierSelector
          disabled={disabled || !canEditOrder}
          error={resolvedErrors.supplierId}
          suppliers={supplierOptions}
          value={values.supplierId ?? undefined}
          onChange={(supplierId) => {
            const nextSupplier =
              supplierOptions.find((supplier) => supplier.id === supplierId) ?? null;

            setValues((current) => ({
              ...current,
              currency: current.currency ?? nextSupplier?.currency ?? "USD",
              paymentTerms: current.paymentTerms ?? nextSupplier?.paymentTerms ?? null,
              supplier: nextSupplier,
              supplierId,
            }));
          }}
        />
        {incompatibleItems.length ? (
          <AlertCard
            description={t("purchasing.forms.purchaseOrder.warnings.supplierChanged")}
            title={t("purchasing.forms.purchaseOrder.warnings.title")}
            tone="warning"
            variant="muted"
          />
        ) : null}
        {locationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t(
              "purchasing.forms.purchaseOrder.fields.inventoryLocation.accessibilityLabel"
            )}
            disabled={disabled || !canEditOrder}
            entities={locationOptions}
            error={resolvedErrors.inventoryLocationId}
            label={t("purchasing.forms.purchaseOrder.fields.inventoryLocation.label")}
            onChange={(inventoryLocationId) =>
              setValues((current) => ({ ...current, inventoryLocationId }))
            }
            placeholder={t(
              "purchasing.forms.purchaseOrder.fields.inventoryLocation.placeholder"
            )}
            value={values.inventoryLocationId ?? undefined}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.purchaseOrder.fields.supplierReference.accessibilityLabel"
              )}
              editable={!disabled && canEditOrder}
              error={resolvedErrors.supplierReference}
              label={t("purchasing.forms.purchaseOrder.fields.supplierReference.label")}
              onChangeText={(supplierReference) =>
                setValues((current) => ({ ...current, supplierReference }))
              }
              placeholder={t(
                "purchasing.forms.purchaseOrder.fields.supplierReference.placeholder"
              )}
              value={values.supplierReference ?? ""}
            />
          </View>
          {showCurrencyField ? (
            <View style={{ flex: 1, minWidth: 220 }}>
              <Select
                accessibilityLabel={t(
                  "purchasing.forms.purchaseOrder.fields.currency.accessibilityLabel"
                )}
                disabled={disabled || !canEditCommercialFields}
                error={resolvedErrors.currency}
                label={t("purchasing.forms.purchaseOrder.fields.currency.label")}
                onChange={(currency) => setValues((current) => ({ ...current, currency }))}
                options={currencyOptions ?? []}
                placeholder={t(
                  "purchasing.forms.purchaseOrder.fields.currency.placeholder"
                )}
                value={values.currency ?? undefined}
              />
            </View>
          ) : null}
        </View>
        <DateTimeField
          editable={!disabled && canEditOrder}
          error={resolvedErrors.expectedAt}
          label={t("purchasing.forms.purchaseOrder.fields.expectedAt.label")}
          onChange={(expectedAt) => setValues((current) => ({ ...current, expectedAt }))}
          timeZone={resolvedTimeZone}
          value={values.expectedAt}
        />
        {values.paymentTerms ? (
          <TextField
            editable={false}
            label={t("purchasing.forms.purchaseOrder.fields.paymentTerms.label")}
            value={values.paymentTerms}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 180 }}>
            <NumberField
              accessibilityLabel={t(
                "purchasing.forms.purchaseOrder.fields.discount.accessibilityLabel"
              )}
              disabled={disabled || !canEditCommercialFields}
              label={t("purchasing.forms.purchaseOrder.fields.discount.label")}
              onChange={(discount) => setValues((current) => ({ ...current, discount }))}
              step={0.01}
              value={values.discount ?? 0}
            />
          </View>
          <View style={{ flex: 1, minWidth: 180 }}>
            <NumberField
              accessibilityLabel={t(
                "purchasing.forms.purchaseOrder.fields.shipping.accessibilityLabel"
              )}
              disabled={disabled || !canEditCommercialFields}
              label={t("purchasing.forms.purchaseOrder.fields.shipping.label")}
              onChange={(shipping) => setValues((current) => ({ ...current, shipping }))}
              step={0.01}
              value={values.shipping ?? 0}
            />
          </View>
          <View style={{ flex: 1, minWidth: 180 }}>
            <NumberField
              accessibilityLabel={t("purchasing.forms.purchaseOrder.fields.tax.accessibilityLabel")}
              disabled={disabled || !canEditCommercialFields}
              label={t("purchasing.forms.purchaseOrder.fields.tax.label")}
              onChange={(tax) => setValues((current) => ({ ...current, tax }))}
              step={0.01}
              value={values.tax ?? 0}
            />
          </View>
        </View>
        <TextArea
          accessibilityLabel={t("purchasing.forms.purchaseOrder.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled && canEditOrder}
          error={resolvedErrors.notes}
          label={t("purchasing.forms.purchaseOrder.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("purchasing.forms.purchaseOrder.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
        <View style={{ gap: theme.spacing[3] }}>
          <View
            style={{
              alignItems: "center",
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[3],
              justifyContent: "space-between",
            }}
          >
            <Text variant="h4">{t("purchasing.forms.purchaseOrder.items.title")}</Text>
            <Button
              disabled={disabled || !canEditOrder}
              label={t("purchasing.actions.addItem")}
              onPress={addItem}
              size="sm"
              variant="secondary"
            />
          </View>
          {resolvedErrors.items ? (
            <Text selectable tone="danger" variant="bodySmall">
              {resolvedErrors.items}
            </Text>
          ) : null}
          {values.items.length === 0 ? (
            <AlertCard
              description={t("purchasing.forms.purchaseOrder.emptyItems.description")}
              title={t("purchasing.forms.purchaseOrder.emptyItems.title")}
              tone="info"
              variant="muted"
            />
          ) : (
            values.items.map((item) => {
              const itemKey = getPurchaseOrderItemKey(item);

              return (
                <PurchaseOrderItemEditor
                  compact={false}
                  currency={values.currency}
                  currencyOptions={currencyOptions}
                  disabled={disabled || !canEditOrder}
                  errors={resolvedErrors.lineItems?.[itemKey]}
                  inventoryItems={inventoryItems}
                  key={itemKey}
                  onChange={(nextItem) =>
                    setValues((current) => ({
                      ...current,
                      items: current.items.map((candidate) =>
                        getPurchaseOrderItemKey(candidate) === itemKey ? nextItem : candidate
                      ),
                    }))
                  }
                  onRemove={() =>
                    setValues((current) => ({
                      ...current,
                      items: current.items.filter(
                        (candidate) => getPurchaseOrderItemKey(candidate) !== itemKey
                      ),
                    }))
                  }
                  supplier={resolvedSupplier}
                  supplierItems={supplierSpecificItems}
                  unitOptions={unitOptions}
                  value={item}
                />
              );
            })
          )}
        </View>
        <PurchaseOrderSummary
          compact={false}
          currency={values.currency}
          discount={values.discount}
          expectedDelivery={values.expectedAt}
          items={values.items}
          shipping={values.shipping}
          subtotal={values.subtotal}
          supplier={resolvedSupplier}
          tax={values.tax}
          total={values.total}
        />
      </View>
    </FormCard>
  );
}
