import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Checkbox } from "@/components/primitives/checkbox";
import { CurrencyInput, type CurrencyOption } from "@/components/primitives/currency-input";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { FormCard } from "@/components/patterns/form-card";
import { NumberField } from "@/components/primitives/number-field";
import { TextField } from "@/components/primitives/text-field";
import {
  createSupplierItemEditorValues,
  getInventoryItemNameForPurchasing,
  getSupplierName,
  getUnitNameForPurchasing,
  hasSupplierItemEditorErrors,
  normalizeSupplierItemEditorValues,
  validateSupplierItemEditorValues,
  type SupplierCurrencyOption,
  type SupplierItemEditorMode,
  type SupplierItemEditorValidationErrors,
  type SupplierItemEditorValues,
  type SupplierItemUnitOption,
  type SupplierRecord,
} from "@/features/purchasing";
import { Select } from "@/components/primitives/select";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: SupplierItemEditorValidationErrors,
  externalErrors?: SupplierItemEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies SupplierItemEditorValidationErrors;
}

export type SupplierItemEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currencyOptions?: SupplierCurrencyOption[];
  disabled?: boolean;
  initialValues?: Partial<SupplierItemEditorValues>;
  inventoryItemOptions?: EntityPickerOption<string>[];
  mode?: SupplierItemEditorMode;
  onCancel?: () => void;
  onSubmit: (value: SupplierItemEditorValues) => void | Promise<void>;
  submitting?: boolean;
  supplier?: SupplierRecord | null;
  supplierOptions?: SupplierRecord[];
  unitOptions?: SupplierItemUnitOption[];
  validationErrors?: SupplierItemEditorValidationErrors;
};

export function SupplierItemEditor({
  accessibilityLabel,
  compact = false,
  currencyOptions,
  disabled = false,
  initialValues,
  inventoryItemOptions,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  supplier,
  supplierOptions,
  unitOptions,
  validationErrors,
}: SupplierItemEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () =>
      createSupplierItemEditorValues({
        ...initialValues,
        supplier: initialValues?.supplier ?? supplier ?? null,
        supplierId: initialValues?.supplierId ?? supplier?.id ?? null,
      }),
    [initialSignature, supplier]
  );
  const [values, setValues] = useState<SupplierItemEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<SupplierItemEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedCurrencyOptions =
    currencyOptions?.length
      ? currencyOptions
      : values.currency
      ? [{ label: values.currency, value: values.currency }]
      : [];

  const handleSubmit = async () => {
    const normalized = normalizeSupplierItemEditorValues(values);
    const nextErrors = validateSupplierItemEditorValues(normalized, t);

    if (hasSupplierItemEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("purchasing.forms.supplierItem.accessibilityLabel")}
      cancelLabel={t("purchasing.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={
        mode === "edit"
          ? t("purchasing.actions.saveChanges")
          : t("purchasing.actions.createSupplierItem")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("purchasing.forms.supplierItem.editTitle")
          : t("purchasing.forms.supplierItem.createTitle")
      }
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        {supplier ? (
          <TextField
            editable={false}
            label={t("purchasing.forms.supplierItem.fields.supplier.label")}
            value={getSupplierName(supplier) ?? ""}
          />
        ) : supplierOptions?.length ? (
          <Select
            accessibilityLabel={t(
              "purchasing.forms.supplierItem.fields.supplier.accessibilityLabel"
            )}
            disabled={disabled}
            error={resolvedErrors.supplierId}
            label={t("purchasing.forms.supplierItem.fields.supplier.label")}
            onChange={(supplierId) => {
              const matchedSupplier =
                supplierOptions.find((option) => option.id === supplierId) ?? null;
              setValues((current) => ({
                ...current,
                currency: current.currency ?? matchedSupplier?.currency ?? "USD",
                supplier: matchedSupplier,
                supplierId,
              }));
            }}
            options={supplierOptions
              .filter((option) => Boolean(option.id))
              .map((option) => ({
                label: getSupplierName(option) ?? option.id ?? "",
                value: option.id ?? "",
              }))}
            placeholder={t("purchasing.forms.supplierItem.fields.supplier.placeholder")}
            value={values.supplierId ?? undefined}
          />
        ) : null}
        {inventoryItemOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t(
              "purchasing.forms.supplierItem.fields.inventoryItem.accessibilityLabel"
            )}
            disabled={disabled}
            entities={inventoryItemOptions}
            error={resolvedErrors.inventoryItemId}
            label={t("purchasing.forms.supplierItem.fields.inventoryItem.label")}
            onChange={(inventoryItemId) =>
              setValues((current) => ({ ...current, inventoryItemId }))
            }
            placeholder={t("purchasing.forms.supplierItem.fields.inventoryItem.placeholder")}
            value={values.inventoryItemId ?? undefined}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplierItem.fields.supplierSku.accessibilityLabel"
              )}
              editable={!disabled}
              error={resolvedErrors.supplierSku}
              label={t("purchasing.forms.supplierItem.fields.supplierSku.label")}
              onChangeText={(supplierSku) =>
                setValues((current) => ({ ...current, supplierSku }))
              }
              placeholder={t("purchasing.forms.supplierItem.fields.supplierSku.placeholder")}
              value={values.supplierSku ?? ""}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <TextField
              accessibilityLabel={t(
                "purchasing.forms.supplierItem.fields.supplierName.accessibilityLabel"
              )}
              editable={!disabled}
              label={t("purchasing.forms.supplierItem.fields.supplierName.label")}
              onChangeText={(supplierName) =>
                setValues((current) => ({ ...current, supplierName }))
              }
              placeholder={t("purchasing.forms.supplierItem.fields.supplierName.placeholder")}
              value={values.supplierName ?? ""}
            />
          </View>
        </View>
        <TextField
          accessibilityLabel={t("purchasing.forms.supplierItem.fields.brand.accessibilityLabel")}
          editable={!disabled}
          label={t("purchasing.forms.supplierItem.fields.brand.label")}
          onChangeText={(brand) => setValues((current) => ({ ...current, brand }))}
          placeholder={t("purchasing.forms.supplierItem.fields.brand.placeholder")}
          value={values.brand ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            {unitOptions?.length ? (
              <Select
                accessibilityLabel={t(
                  "purchasing.forms.supplierItem.fields.unit.accessibilityLabel"
                )}
                disabled={disabled}
                error={resolvedErrors.unitId}
                label={t("purchasing.forms.supplierItem.fields.unit.label")}
                onChange={(unitId) => {
                  const matchedUnit = unitOptions.find((option) => option.value === unitId);
                  setValues((current) => ({
                    ...current,
                    unit: matchedUnit ? { id: unitId, symbol: matchedUnit.label } : null,
                    unitId,
                  }));
                }}
                options={unitOptions}
                placeholder={t("purchasing.forms.supplierItem.fields.unit.placeholder")}
                value={values.unitId ?? undefined}
              />
            ) : (
              <TextField
                editable={false}
                label={t("purchasing.forms.supplierItem.fields.unit.label")}
                value={getUnitNameForPurchasing(values.unit) ?? ""}
              />
            )}
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <NumberField
              accessibilityLabel={t(
                "purchasing.forms.supplierItem.fields.packQuantity.accessibilityLabel"
              )}
              disabled={disabled}
              error={resolvedErrors.packQuantity}
              label={t("purchasing.forms.supplierItem.fields.packQuantity.label")}
              min={0}
              onChange={(packQuantity) =>
                setValues((current) => ({ ...current, packQuantity }))
              }
              step={0.01}
              value={values.packQuantity ?? 0}
            />
          </View>
        </View>
        {unitOptions?.length ? (
          <Select
            accessibilityLabel={t(
              "purchasing.forms.supplierItem.fields.packUnit.accessibilityLabel"
            )}
            disabled={disabled}
            error={resolvedErrors.packUnitId}
            label={t("purchasing.forms.supplierItem.fields.packUnit.label")}
            onChange={(packUnitId) => {
              const matchedUnit = unitOptions.find((option) => option.value === packUnitId);
              setValues((current) => ({
                ...current,
                packUnit: matchedUnit ? { id: packUnitId, symbol: matchedUnit.label } : null,
                packUnitId,
              }));
            }}
            options={unitOptions}
            placeholder={t("purchasing.forms.supplierItem.fields.packUnit.placeholder")}
            value={values.packUnitId ?? undefined}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: compact ? 220 : 320 }}>
            <CurrencyInput
              accessibilityLabel={t("purchasing.forms.supplierItem.fields.price.accessibilityLabel")}
              currencies={resolvedCurrencyOptions}
              currency={values.currency ?? undefined}
              disabled={disabled}
              error={resolvedErrors.price}
              label={t("purchasing.forms.supplierItem.fields.price.label")}
              onChange={(price) => setValues((current) => ({ ...current, price }))}
              onCurrencyChange={(currency) =>
                setValues((current) => ({ ...current, currency }))
              }
              value={values.price ?? 0}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <NumberField
              accessibilityLabel={t(
                "purchasing.forms.supplierItem.fields.minimumOrderQuantity.accessibilityLabel"
              )}
              disabled={disabled}
              error={resolvedErrors.minimumOrderQuantity}
              label={t("purchasing.forms.supplierItem.fields.minimumOrderQuantity.label")}
              min={0}
              onChange={(minimumOrderQuantity) =>
                setValues((current) => ({ ...current, minimumOrderQuantity }))
              }
              step={0.01}
              value={values.minimumOrderQuantity ?? 0}
            />
          </View>
        </View>
        <NumberField
          accessibilityLabel={t(
            "purchasing.forms.supplierItem.fields.leadTimeDays.accessibilityLabel"
          )}
          disabled={disabled}
          error={resolvedErrors.leadTimeDays}
          label={t("purchasing.forms.supplierItem.fields.leadTimeDays.label")}
          min={0}
          onChange={(leadTimeDays) => setValues((current) => ({ ...current, leadTimeDays }))}
          step={1}
          value={values.leadTimeDays ?? 0}
        />
        <Checkbox
          accessibilityLabel={t("purchasing.forms.supplierItem.fields.preferred.accessibilityLabel")}
          checked={Boolean(values.preferred)}
          disabled={disabled}
          label={t("purchasing.forms.supplierItem.fields.preferred.label")}
          onChange={(preferred) => setValues((current) => ({ ...current, preferred }))}
        />
        <Checkbox
          accessibilityLabel={t("purchasing.forms.supplierItem.fields.active.accessibilityLabel")}
          checked={values.active !== false}
          disabled={disabled}
          label={t("purchasing.forms.supplierItem.fields.active.label")}
          onChange={(active) => setValues((current) => ({ ...current, active }))}
        />
      </View>
    </FormCard>
  );
}
