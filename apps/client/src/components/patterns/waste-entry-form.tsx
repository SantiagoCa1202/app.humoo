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
import {
  createWasteEntryValues,
  formatInventoryMeasurement,
  getInventoryAvailableQuantity,
  getInventoryWasteReasonTranslationKey,
  getInventoryUnit,
  hasWasteEntryErrors,
  INVENTORY_WASTE_REASON_VALUES,
  normalizeWasteEntryValues,
  validateWasteEntryValues,
  type InventoryItemRecord,
  type InventoryLotRecord,
  type InventoryStockRecord,
  type InventoryWasteReason,
  type WasteEntryValidationErrors,
  type WasteEntryValues,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: WasteEntryValidationErrors,
  externalErrors?: WasteEntryValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies WasteEntryValidationErrors;
}

export type WasteEntryFormProps = {
  accessibilityLabel?: string;
  currentStock?: InventoryStockRecord | null;
  disabled?: boolean;
  initialValues?: Partial<WasteEntryValues>;
  item: InventoryItemRecord;
  locationOptions?: EntityPickerOption<string>[];
  lotOptions?: EntityPickerOption<string>[];
  onCancel?: () => void;
  onSubmit: (value: WasteEntryValues) => void | Promise<void>;
  reasons?: readonly InventoryWasteReason[];
  submitting?: boolean;
  timeZone?: string;
  unitOptions?: EntityPickerOption<string>[];
  validationErrors?: WasteEntryValidationErrors;
};

export function WasteEntryForm({
  accessibilityLabel,
  currentStock,
  disabled = false,
  initialValues,
  item,
  locationOptions,
  lotOptions,
  onCancel,
  onSubmit,
  reasons,
  submitting = false,
  timeZone,
  unitOptions,
  validationErrors,
}: WasteEntryFormProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () =>
      createWasteEntryValues({
        ...initialValues,
        currentQuantity:
          initialValues?.currentQuantity ??
          getInventoryAvailableQuantity(currentStock ?? item.stock),
        inventoryItem: initialValues?.inventoryItem ?? item,
        inventoryItemId: initialValues?.inventoryItemId ?? item.id,
        locationId:
          initialValues?.locationId ??
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
  const [values, setValues] = useState<WasteEntryValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<WasteEntryValidationErrors>({});

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
  const resolvedTimeZone =
    timeZone ??
    Intl.DateTimeFormat().resolvedOptions().timeZone ??
    "UTC";
  const resolvedReasons = reasons?.length ? reasons : INVENTORY_WASTE_REASON_VALUES;

  const handleSubmit = async () => {
    const normalized = normalizeWasteEntryValues(values);
    const nextErrors = validateWasteEntryValues(normalized, t);

    if (hasWasteEntryErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.waste.accessibilityLabel")}
      cancelLabel={t("inventory.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("inventory.actions.recordWaste")}
      submitting={submitting}
      title={t("inventory.waste.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <InventoryItemCard compact item={item} showStatus={false} stock={resolvedStock} />
        <Select
          accessibilityLabel={t("inventory.waste.fields.reason.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.reason}
          label={t("inventory.waste.fields.reason.label")}
          onChange={(reason) =>
            setValues((current) => ({
              ...current,
              reason: reason as InventoryWasteReason,
            }))
          }
          options={resolvedReasons.map((reason) => ({
            label: t(getInventoryWasteReasonTranslationKey(reason)),
            value: reason,
          }))}
          placeholder={t("inventory.waste.fields.reason.placeholder")}
          value={values.reason ?? undefined}
        />
        {unitOptions?.length ? (
          <QuantityInput
            accessibilityLabel={t("inventory.waste.fields.quantity.accessibilityLabel")}
            disabled={disabled}
            error={resolvedErrors.quantity}
            helperText={t("inventory.waste.currentStock", { value: currentLabel })}
            label={t("inventory.waste.fields.quantity.label")}
            onChange={(quantity) => setValues((current) => ({ ...current, quantity }))}
            onUnitChange={(unitId) => {
              const option = unitOptions.find((candidate) => candidate.value === unitId);
              setValues((current) => ({
                ...current,
                unit: option ? { id: unitId, symbol: option.label } : null,
                unitId,
              }));
            }}
            step={0.01}
            unit={values.unitId ?? undefined}
            units={unitOptions.map((option) => ({
              label: option.label ?? option.name ?? option.value,
              value: option.value,
            }))}
            value={values.quantity ?? 0}
          />
        ) : null}
        {locationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.waste.fields.location.accessibilityLabel")}
            disabled={disabled}
            entities={locationOptions}
            error={resolvedErrors.locationId}
            label={t("inventory.waste.fields.location.label")}
            onChange={(locationId) => setValues((current) => ({ ...current, locationId }))}
            placeholder={t("inventory.waste.fields.location.placeholder")}
            value={values.locationId ?? undefined}
          />
        ) : null}
        {lotOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.waste.fields.lot.accessibilityLabel")}
            disabled={disabled}
            entities={lotOptions}
            error={resolvedErrors.stockLotId}
            label={t("inventory.waste.fields.lot.label")}
            onChange={(stockLotId) => setValues((current) => ({ ...current, stockLotId }))}
            placeholder={t("inventory.waste.fields.lot.placeholder")}
            value={values.stockLotId ?? undefined}
          />
        ) : null}
        <DateTimeField
          editable={!disabled}
          label={t("inventory.waste.fields.occurredAt.label")}
          onChange={(occurredAt) => setValues((current) => ({ ...current, occurredAt }))}
          timeZone={resolvedTimeZone}
          value={values.occurredAt}
        />
        <TextArea
          accessibilityLabel={t("inventory.waste.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.notes}
          label={t("inventory.waste.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("inventory.waste.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
      </View>
    </FormCard>
  );
}
