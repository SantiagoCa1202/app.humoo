import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { FormCard } from "@/components/patterns/form-card";
import { InventoryItemCard } from "@/components/patterns/inventory-item-card";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { Select } from "@/components/primitives/select";
import { TextArea } from "@/components/primitives/text-area";
import { Text } from "@/components/primitives/text";
import {
  createStockMovementValues,
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryMovementDirection,
  getInventoryUnit,
  hasStockMovementErrors,
  INVENTORY_MOVEMENT_FORM_TYPE_VALUES,
  normalizeStockMovementValues,
  validateStockMovementValues,
  type InventoryItemRecord,
  type InventoryMovementType,
  type InventoryStockRecord,
  type StockMovementValidationErrors,
  type StockMovementValues,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: StockMovementValidationErrors,
  externalErrors?: StockMovementValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies StockMovementValidationErrors;
}

export type StockMovementFormProps = {
  accessibilityLabel?: string;
  currentStock?: InventoryStockRecord | null;
  disabled?: boolean;
  initialValues?: Partial<StockMovementValues>;
  item: InventoryItemRecord;
  locationOptions?: EntityPickerOption<string>[];
  lotOptions?: EntityPickerOption<string>[];
  movementTypes?: readonly InventoryMovementType[];
  onCancel?: () => void;
  onChange?: (value: StockMovementValues) => void;
  onSubmit: (value: StockMovementValues) => void | Promise<void>;
  showOccurredAt?: boolean;
  submitting?: boolean;
  submitLabel?: string;
  timeZone?: string;
  title?: React.ReactNode;
  unitOptions?: EntityPickerOption<string>[];
  validationErrors?: StockMovementValidationErrors;
};

export function StockMovementForm({
  accessibilityLabel,
  currentStock,
  disabled = false,
  initialValues,
  item,
  locationOptions,
  lotOptions,
  movementTypes,
  onCancel,
  onChange,
  onSubmit,
  showOccurredAt = true,
  submitting = false,
  submitLabel,
  timeZone,
  title,
  unitOptions,
  validationErrors,
}: StockMovementFormProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () =>
      createStockMovementValues({
        ...initialValues,
        currentQuantity:
          initialValues?.currentQuantity ??
          getInventoryAvailableQuantity(currentStock ?? item.stock),
        inventoryItem: initialValues?.inventoryItem ?? item,
        inventoryItemId: initialValues?.inventoryItemId ?? item.id,
        inventoryLocationId:
          initialValues?.inventoryLocationId ??
          currentStock?.location?.id ??
          item.location?.id ??
          null,
        unitId:
          initialValues?.unitId ??
          getInventoryUnit(item, currentStock ?? item.stock)?.id ??
          null,
        unit: initialValues?.unit ?? getInventoryUnit(item, currentStock ?? item.stock),
      }),
    [initialSignature, currentStock, item]
  );
  const [values, setValues] = useState<StockMovementValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<StockMovementValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedStock = currentStock ?? item.stock ?? null;
  const resolvedUnit = values.unit ?? getInventoryUnit(item, resolvedStock);
  const currentLabel =
    formatInventoryMeasurement(
      values.currentQuantity ?? getInventoryAvailableQuantity(resolvedStock),
      resolvedUnit,
      i18n.language
    ) ?? t("inventory.labels.unknownStock");
  const quantityLabel = formatInventoryMeasurement(values.quantity, resolvedUnit, i18n.language);
  const resolvedMovementTypes = movementTypes?.length
    ? movementTypes
    : INVENTORY_MOVEMENT_FORM_TYPE_VALUES;
  const direction = getInventoryMovementDirection(values.type);
  const movementQuantity = values.quantity ?? 0;
  const proposedQuantity =
    values.currentQuantity !== null && values.currentQuantity !== undefined
      ? direction === "out"
        ? values.currentQuantity - movementQuantity
        : direction === "in"
        ? values.currentQuantity + movementQuantity
        : null
      : null;
  const proposedLabel = formatInventoryMeasurement(
    proposedQuantity,
    resolvedUnit,
    i18n.language
  );
  const resolvedTimeZone =
    timeZone ??
    Intl.DateTimeFormat().resolvedOptions().timeZone ??
    "UTC";

  const updateValues = (nextValues: StockMovementValues) => {
    setValues(nextValues);
    onChange?.(nextValues);
  };

  const handleSubmit = async () => {
    const normalized = normalizeStockMovementValues(values);
    const nextErrors = validateStockMovementValues(normalized, t);

    if (hasStockMovementErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.movementForm.accessibilityLabel")}
      cancelLabel={t("inventory.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={submitLabel ?? t("inventory.actions.saveMovement")}
      submitting={submitting}
      title={title ?? t("inventory.movementForm.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <InventoryItemCard compact item={item} showStatus={false} stock={resolvedStock} />
        <Text selectable tone="secondary" variant="bodySmall">
          {t("inventory.movementForm.currentStock", { value: currentLabel })}
        </Text>
        <Select
          accessibilityLabel={t("inventory.movementForm.fields.type.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.type}
          label={t("inventory.movementForm.fields.type.label")}
          onChange={(type) =>
            updateValues({
              ...values,
              type: type as InventoryMovementType,
            })
          }
          options={resolvedMovementTypes.map((type) => ({
            label: t(`inventory.movements.types.${type}`),
            value: type,
          }))}
          placeholder={t("inventory.movementForm.fields.type.placeholder")}
          value={values.type ?? undefined}
        />
        {unitOptions?.length ? (
          <QuantityInput
            accessibilityLabel={t("inventory.movementForm.fields.quantity.accessibilityLabel")}
            disabled={disabled}
            error={resolvedErrors.quantity}
            helperText={
              direction === "out"
                ? t("inventory.movementForm.fields.quantity.outHelper")
                : direction === "in"
                ? t("inventory.movementForm.fields.quantity.inHelper")
                : undefined
            }
            label={t("inventory.movementForm.fields.quantity.label")}
            onChange={(quantity) => updateValues({ ...values, quantity })}
            onUnitChange={(unitId) => {
              const option = unitOptions.find((candidate) => candidate.value === unitId);
              updateValues({
                ...values,
                unit: option ? { id: unitId, symbol: option.label } : null,
                unitId,
              });
            }}
            step={0.01}
            unit={values.unitId ?? undefined}
            units={unitOptions.map((option) => ({
              label: option.label ?? option.name ?? option.value,
              value: option.value,
            }))}
            value={movementQuantity}
          />
        ) : null}
        {locationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.movementForm.fields.location.accessibilityLabel")}
            disabled={disabled}
            entities={locationOptions}
            error={resolvedErrors.locationId}
            label={t("inventory.movementForm.fields.location.label")}
            onChange={(inventoryLocationId) =>
              updateValues({ ...values, inventoryLocationId })
            }
            placeholder={t("inventory.movementForm.fields.location.placeholder")}
            value={values.inventoryLocationId ?? undefined}
          />
        ) : null}
        {lotOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.movementForm.fields.lot.accessibilityLabel")}
            disabled={disabled}
            entities={lotOptions}
            error={resolvedErrors.stockLotId}
            label={t("inventory.movementForm.fields.lot.label")}
            onChange={(stockLotId) => updateValues({ ...values, stockLotId })}
            placeholder={t("inventory.movementForm.fields.lot.placeholder")}
            value={values.stockLotId ?? undefined}
          />
        ) : null}
        {showOccurredAt ? (
          <DateTimeField
            editable={!disabled}
            error={resolvedErrors.occurredAt}
            label={t("inventory.movementForm.fields.occurredAt.label")}
            onChange={(occurredAt) => updateValues({ ...values, occurredAt })}
            timeZone={resolvedTimeZone}
            value={values.occurredAt}
          />
        ) : null}
        <TextArea
          accessibilityLabel={t("inventory.movementForm.fields.reason.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.reason}
          label={t("inventory.movementForm.fields.reason.label")}
          onChangeText={(reason) => updateValues({ ...values, reason })}
          placeholder={t("inventory.movementForm.fields.reason.placeholder")}
          value={values.reason ?? ""}
        />
        <TextArea
          accessibilityLabel={t("inventory.movementForm.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.notes}
          label={t("inventory.movementForm.fields.notes.label")}
          onChangeText={(notes) => updateValues({ ...values, notes })}
          placeholder={t("inventory.movementForm.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
        {quantityLabel && proposedLabel ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text selectable tone="secondary" variant="bodySmall">
              {t("inventory.movementForm.preview.current", { value: currentLabel })}
            </Text>
            <Text selectable tone="secondary" variant="bodySmall">
              {t("inventory.movementForm.preview.movement", { value: quantityLabel })}
            </Text>
            <Text selectable tone="primary" variant="bodySmall">
              {t("inventory.movementForm.preview.proposed", { value: proposedLabel })}
            </Text>
          </View>
        ) : null}
      </View>
    </FormCard>
  );
}
