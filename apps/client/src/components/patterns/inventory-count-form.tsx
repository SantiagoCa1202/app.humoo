import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { InventoryItemCard } from "@/components/patterns/inventory-item-card";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { TextArea } from "@/components/primitives/text-area";
import { Text } from "@/components/primitives/text";
import {
  createInventoryCountValues,
  formatInventoryDifference,
  formatInventoryMeasurement,
  getInventoryCountDifference,
  getInventoryUnit,
  hasInventoryCountErrors,
  normalizeInventoryCountValues,
  validateInventoryCountValues,
  type InventoryCountItemRecord,
  type InventoryCountValidationErrors,
  type InventoryCountValues,
  type InventoryItemRecord,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: InventoryCountValidationErrors,
  externalErrors?: InventoryCountValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies InventoryCountValidationErrors;
}

export type InventoryCountFormProps = {
  accessibilityLabel?: string;
  countItem?: Partial<InventoryCountItemRecord>;
  disabled?: boolean;
  expectedQuantity?: number | null;
  item: InventoryItemRecord;
  onCancel?: () => void;
  onSubmit: (value: InventoryCountValues) => void | Promise<void>;
  submitting?: boolean;
  unitOptions?: EntityPickerOption<string>[];
  validationErrors?: InventoryCountValidationErrors;
};

export function InventoryCountForm({
  accessibilityLabel,
  countItem,
  disabled = false,
  expectedQuantity,
  item,
  onCancel,
  onSubmit,
  submitting = false,
  unitOptions,
  validationErrors,
}: InventoryCountFormProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(countItem ?? {});
  const defaultValues = useMemo(
    () =>
      createInventoryCountValues({
        ...countItem,
        expectedQuantity:
          expectedQuantity ??
          countItem?.expectedQuantity ??
          item.stock?.availableQuantity ??
          item.stock?.onHandQuantity ??
          null,
        inventoryItem: countItem?.inventoryItem ?? item,
        inventoryItemId: countItem?.inventoryItemId ?? item.id,
        unitId:
          countItem?.unitId ?? getInventoryUnit(item, item.stock)?.id ?? null,
        unit: countItem?.unit ?? getInventoryUnit(item, item.stock),
      }),
    [initialSignature, countItem, item, expectedQuantity]
  );
  const [values, setValues] = useState<InventoryCountValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<InventoryCountValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedUnit = values.unit ?? getInventoryUnit(item, item.stock);
  const expectedLabel =
    formatInventoryMeasurement(values.expectedQuantity, resolvedUnit, i18n.language) ??
    t("inventory.labels.unknownStock");
  const difference = getInventoryCountDifference(values);
  const differenceLabel = formatInventoryDifference(
    difference,
    resolvedUnit,
    i18n.language
  );

  const handleSubmit = async () => {
    const normalized = normalizeInventoryCountValues(values);
    const nextErrors = validateInventoryCountValues(normalized, t);

    if (hasInventoryCountErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.count.accessibilityLabel")}
      cancelLabel={t("inventory.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("inventory.actions.countInventory")}
      submitting={submitting}
      title={t("inventory.count.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <InventoryItemCard compact item={item} showStatus={false} />
        <Text selectable tone="secondary" variant="bodySmall">
          {t("inventory.count.expected", { value: expectedLabel })}
        </Text>
        {unitOptions?.length ? (
          <QuantityInput
            accessibilityLabel={t("inventory.count.fields.countedQuantity.accessibilityLabel")}
            disabled={disabled}
            error={resolvedErrors.countedQuantity}
            label={t("inventory.count.fields.countedQuantity.label")}
            onChange={(countedQuantity) =>
              setValues((current) => ({ ...current, countedQuantity }))
            }
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
            value={values.countedQuantity ?? 0}
          />
        ) : null}
        {differenceLabel ? (
          <Text
            selectable
            tone={difference === 0 ? "secondary" : "warning"}
            variant="bodySmall"
          >
            {t("inventory.count.difference", { value: differenceLabel })}
          </Text>
        ) : null}
        <TextArea
          accessibilityLabel={t("inventory.count.fields.notes.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          label={t("inventory.count.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("inventory.count.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
      </View>
    </FormCard>
  );
}
