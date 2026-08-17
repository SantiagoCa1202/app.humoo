import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { StockMovementForm } from "@/components/patterns/stock-movement-form";
import {
  INVENTORY_ADJUSTMENT_TYPE_VALUES,
  type InventoryItemRecord,
  type InventoryMovementType,
  type InventoryStockRecord,
  type StockAdjustmentValidationErrors,
  type StockAdjustmentValues,
} from "@/features/inventory";
import { useTranslation } from "react-i18next";

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
  const { t } = useTranslation("common");
  const resolvedTypes = adjustmentTypes?.length
    ? adjustmentTypes
    : INVENTORY_ADJUSTMENT_TYPE_VALUES;

  return (
    <StockMovementForm
      accessibilityLabel={accessibilityLabel ?? t("inventory.adjustment.accessibilityLabel")}
      currentStock={currentStock}
      disabled={disabled}
      initialValues={value}
      item={item}
      locationOptions={locationOptions}
      movementTypes={resolvedTypes}
      onCancel={onCancel}
      onChange={
        onChange
          ? (movementValues) =>
              onChange({
                currentQuantity: movementValues.currentQuantity,
                inventoryItemId: movementValues.inventoryItemId ?? null,
                locationId: movementValues.inventoryLocationId ?? null,
                notes: movementValues.notes ?? null,
                quantity: movementValues.quantity ?? 0,
                reason: movementValues.reason ?? null,
                type: (movementValues.type ?? "adjustment_in") as InventoryMovementType,
                unitId: movementValues.unitId ?? null,
                version: movementValues.version ?? null,
              })
          : undefined
      }
      onSubmit={async (movementValues) =>
        onSubmit({
          currentQuantity: movementValues.currentQuantity,
          inventoryItemId: movementValues.inventoryItemId ?? null,
          locationId: movementValues.inventoryLocationId ?? null,
          notes: movementValues.notes ?? null,
          quantity: movementValues.quantity ?? 0,
          reason: movementValues.reason ?? null,
          type: (movementValues.type ?? "adjustment_in") as InventoryMovementType,
          unitId: movementValues.unitId ?? null,
          version: movementValues.version ?? null,
        })
      }
      showOccurredAt={false}
      submitting={submitting}
      submitLabel={t("inventory.actions.confirmAdjustment")}
      title={t("inventory.adjustment.title")}
      unitOptions={unitOptions}
      validationErrors={validationErrors}
    />
  );
}
