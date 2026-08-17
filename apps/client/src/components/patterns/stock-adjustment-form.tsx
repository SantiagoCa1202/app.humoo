import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { InventoryItemCard } from "@/components/patterns/inventory-item-card";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { Select } from "@/components/primitives/select";
import { TextArea } from "@/components/primitives/text-area";
import { Text } from "@/components/primitives/text";
import {
  createStockAdjustmentValues,
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryUnit,
  hasStockAdjustmentErrors,
  INVENTORY_ADJUSTMENT_TYPE_VALUES,
  normalizeStockAdjustmentValues,
  validateStockAdjustmentValues,
  type InventoryItemRecord,
  type InventoryMovementType,
  type InventoryStockRecord,
  type StockAdjustmentValidationErrors,
  type StockAdjustmentValues,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: StockAdjustmentValidationErrors,
  externalErrors?: StockAdjustmentValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies StockAdjustmentValidationErrors;
}

export type StockAdjustmentFormProps = {
  accessibilityLabel?: string;
  adjustmentTypes?: readonly InventoryMovementType[];
  currentStock?: InventoryStockRecord | null;
  disabled?: boolean;
  item: InventoryItemRecord;
  locationOptions?: EntityPickerOption<string>[];
  onCancel?: () => void;
  onChange?: (value: StockAdjustmentValues) => void;
  onSubmit: (value: StockAdjustmentValues) => void | Promise<void>;
  submitting?: boolean;
  unitOptions?: EntityPickerOption<string>[];
  validationErrors?: StockAdjustmentValidationErrors;
  value?: Partial<StockAdjustmentValues>;
};

export function StockAdjustmentForm({
  accessibilityLabel,
  adjustmentTypes,
  currentStock,
  disabled = false,
  item,
  locationOptions,
  onCancel,
  onChange,
  onSubmit,
  submitting = false,
  unitOptions,
  validationErrors,
  value,
}: StockAdjustmentFormProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(value ?? {});
  const defaultValues = useMemo(
    () =>
      createStockAdjustmentValues({
        ...value,
        currentQuantity:
          value?.currentQuantity ?? getInventoryAvailableQuantity(currentStock ?? item.stock),
        inventoryItemId: value?.inventoryItemId ?? item.id,
        unitId: value?.unitId ?? getInventoryUnit(item, currentStock ?? item.stock)?.id ?? null,
      }),
    [initialSignature, item, currentStock]
  );
  const [values, setValues] = useState<StockAdjustmentValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<StockAdjustmentValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedStock = currentStock ?? item.stock ?? null;
  const resolvedUnit = getInventoryUnit(item, resolvedStock);
  const currentLabel =
    formatInventoryMeasurement(
      getInventoryAvailableQuantity(resolvedStock),
      resolvedUnit,
      i18n.language
    ) ?? t("inventory.labels.unknownStock");
  const adjustmentLabel = formatInventoryMeasurement(
    values.quantity,
    resolvedUnit,
    i18n.language
  );
  const proposedQuantity =
    values.currentQuantity !== null && values.currentQuantity !== undefined
      ? values.type === "adjustment_out"
        ? values.currentQuantity - values.quantity
        : values.currentQuantity + values.quantity
      : null;
  const proposedLabel = formatInventoryMeasurement(
    proposedQuantity,
    resolvedUnit,
    i18n.language
  );
  const resolvedTypes = adjustmentTypes?.length
    ? adjustmentTypes
    : INVENTORY_ADJUSTMENT_TYPE_VALUES;

  const updateValues = (nextValues: StockAdjustmentValues) => {
    setValues(nextValues);
    onChange?.(nextValues);
  };

  const handleSubmit = async () => {
    const normalized = normalizeStockAdjustmentValues(values);
    const nextErrors = validateStockAdjustmentValues(normalized, t);

    if (hasStockAdjustmentErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.adjustment.accessibilityLabel")}
      cancelLabel={t("inventory.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("inventory.actions.confirmAdjustment")}
      submitting={submitting}
      title={t("inventory.adjustment.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <InventoryItemCard compact item={item} showStatus={false} stock={resolvedStock} />
        <Text selectable tone="secondary" variant="bodySmall">
          {t("inventory.adjustment.currentStock", { value: currentLabel })}
        </Text>
        <Select
          accessibilityLabel={t("inventory.adjustment.fields.type.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.type}
          label={t("inventory.adjustment.fields.type.label")}
          onChange={(type) =>
            updateValues({
              ...values,
              type: type as InventoryMovementType,
            })
          }
          options={resolvedTypes.map((type) => ({
            label: t(`inventory.movements.types.${type}`),
            value: type,
          }))}
          placeholder={t("inventory.adjustment.fields.type.placeholder")}
          value={values.type}
        />
        {unitOptions?.length ? (
          <QuantityInput
            accessibilityLabel={t("inventory.adjustment.fields.quantity.accessibilityLabel")}
            disabled={disabled}
            error={resolvedErrors.quantity}
            helperText={
              values.type === "adjustment_out"
                ? t("inventory.adjustment.fields.quantity.decreaseHelper")
                : t("inventory.adjustment.fields.quantity.increaseHelper")
            }
            label={t("inventory.adjustment.fields.quantity.label")}
            onChange={(quantity) => updateValues({ ...values, quantity })}
            onUnitChange={(unitId) => updateValues({ ...values, unitId })}
            step={0.01}
            unit={values.unitId ?? undefined}
            units={unitOptions.map((option) => ({
              label: option.label ?? option.name ?? option.value,
              value: option.value,
            }))}
            value={values.quantity}
          />
        ) : null}
        {locationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.adjustment.fields.location.accessibilityLabel")}
            disabled={disabled}
            entities={locationOptions}
            error={resolvedErrors.locationId}
            label={t("inventory.adjustment.fields.location.label")}
            onChange={(locationId) => updateValues({ ...values, locationId })}
            placeholder={t("inventory.adjustment.fields.location.placeholder")}
            value={values.locationId ?? undefined}
          />
        ) : null}
        <TextArea
          accessibilityLabel={t("inventory.adjustment.fields.reason.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.reason}
          label={t("inventory.adjustment.fields.reason.label")}
          onChangeText={(reason) => updateValues({ ...values, reason })}
          placeholder={t("inventory.adjustment.fields.reason.placeholder")}
          value={values.reason ?? ""}
        />
        {adjustmentLabel && proposedLabel ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text selectable tone="secondary" variant="bodySmall">
              {t("inventory.adjustment.preview.current", { value: currentLabel })}
            </Text>
            <Text selectable tone="secondary" variant="bodySmall">
              {t("inventory.adjustment.preview.adjustment", { value: adjustmentLabel })}
            </Text>
            <Text selectable tone="primary" variant="bodySmall">
              {t("inventory.adjustment.preview.proposed", { value: proposedLabel })}
            </Text>
          </View>
        ) : null}
      </View>
    </FormCard>
  );
}
