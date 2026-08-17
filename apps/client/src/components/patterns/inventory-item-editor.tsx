import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { NumberField } from "@/components/primitives/number-field";
import { RadioGroup } from "@/components/primitives/radio-group";
import { Select } from "@/components/primitives/select";
import { TextField } from "@/components/primitives/text-field";
import {
  createInventoryItemEditorValues,
  hasInventoryItemEditorErrors,
  normalizeInventoryItemEditorValues,
  validateInventoryItemEditorValues,
  type InventoryItemEditorMode,
  type InventoryItemEditorValidationErrors,
  type InventoryItemEditorValues,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: InventoryItemEditorValidationErrors,
  externalErrors?: InventoryItemEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies InventoryItemEditorValidationErrors;
}

export type InventoryItemEditorProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  initialValues?: Partial<InventoryItemEditorValues>;
  locationOptions?: EntityPickerOption<string>[];
  mode?: InventoryItemEditorMode;
  onCancel?: () => void;
  onSubmit: (value: InventoryItemEditorValues) => void | Promise<void>;
  submitting?: boolean;
  supplierOptions?: EntityPickerOption<string>[];
  unitOptions?: EntityPickerOption<string>[];
  validationErrors?: InventoryItemEditorValidationErrors;
};

export function InventoryItemEditor({
  accessibilityLabel,
  disabled = false,
  initialValues,
  locationOptions,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  supplierOptions,
  unitOptions,
  validationErrors,
}: InventoryItemEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createInventoryItemEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<InventoryItemEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<InventoryItemEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const title =
    mode === "edit"
      ? t("inventory.editor.editTitle")
      : t("inventory.editor.createTitle");
  const submitLabel =
    mode === "edit"
      ? t("inventory.actions.saveChanges")
      : t("inventory.actions.createItem");

  const handleSubmit = async () => {
    const normalized = normalizeInventoryItemEditorValues(values);
    const nextErrors = validateInventoryItemEditorValues(normalized, t);

    if (hasInventoryItemEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("inventory.editor.accessibilityLabel")}
      cancelLabel={t("inventory.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={submitLabel}
      submitting={submitting}
      title={title}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          accessibilityLabel={t("inventory.editor.fields.name.accessibilityLabel")}
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("inventory.editor.fields.name.label")}
          onChangeText={(name) => setValues((current) => ({ ...current, name }))}
          placeholder={t("inventory.editor.fields.name.placeholder")}
          required
          value={values.name}
        />
        <TextField
          accessibilityLabel={t("inventory.editor.fields.sku.accessibilityLabel")}
          editable={!disabled}
          error={resolvedErrors.sku}
          label={t("inventory.editor.fields.sku.label")}
          onChangeText={(sku) => setValues((current) => ({ ...current, sku }))}
          placeholder={t("inventory.editor.fields.sku.placeholder")}
          value={values.sku ?? ""}
        />
        {unitOptions?.length ? (
          <Select
            accessibilityLabel={t("inventory.editor.fields.baseUnit.accessibilityLabel")}
            disabled={disabled}
            error={resolvedErrors.baseUnitId}
            label={t("inventory.editor.fields.baseUnit.label")}
            onChange={(baseUnitId) => {
              const unit = unitOptions.find((option) => option.value === baseUnitId);
              setValues((current) => ({
                ...current,
                baseUnit: unit ? { id: baseUnitId, symbol: unit.label } : null,
                baseUnitId,
              }));
            }}
            options={unitOptions.map((option) => ({
              label: option.label ?? option.name ?? option.value,
              value: option.value,
            }))}
            placeholder={t("inventory.editor.fields.baseUnit.placeholder")}
            value={values.baseUnitId ?? undefined}
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <NumberField
              accessibilityLabel={t("inventory.editor.fields.minimumQuantity.accessibilityLabel")}
              disabled={disabled}
              error={resolvedErrors.minimumQuantity}
              label={t("inventory.editor.fields.minimumQuantity.label")}
              min={0}
              onChange={(minimumQuantity) =>
                setValues((current) => ({ ...current, minimumQuantity }))
              }
              step={0.01}
              value={values.minimumQuantity ?? 0}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <NumberField
              accessibilityLabel={t("inventory.editor.fields.reorderQuantity.accessibilityLabel")}
              disabled={disabled}
              error={resolvedErrors.reorderQuantity}
              label={t("inventory.editor.fields.reorderQuantity.label")}
              min={0}
              onChange={(reorderQuantity) =>
                setValues((current) => ({ ...current, reorderQuantity }))
              }
              step={0.01}
              value={values.reorderQuantity ?? 0}
            />
          </View>
        </View>
        {locationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.editor.fields.location.accessibilityLabel")}
            disabled={disabled}
            entities={locationOptions}
            error={resolvedErrors.locationId}
            label={t("inventory.editor.fields.location.label")}
            onChange={(locationId) =>
              setValues((current) => ({ ...current, locationId }))
            }
            placeholder={t("inventory.editor.fields.location.placeholder")}
            value={values.locationId ?? undefined}
          />
        ) : null}
        {supplierOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("inventory.editor.fields.supplier.accessibilityLabel")}
            disabled={disabled}
            entities={supplierOptions}
            error={resolvedErrors.preferredSupplierId}
            label={t("inventory.editor.fields.supplier.label")}
            onChange={(preferredSupplierId) =>
              setValues((current) => ({ ...current, preferredSupplierId }))
            }
            placeholder={t("inventory.editor.fields.supplier.placeholder")}
            value={values.preferredSupplierId ?? undefined}
          />
        ) : null}
        <RadioGroup
          accessibilityLabel={t("inventory.editor.fields.active.accessibilityLabel")}
          direction="horizontal"
          disabled={disabled}
          label={t("inventory.editor.fields.active.label")}
          onChange={(nextValue) =>
            setValues((current) => ({ ...current, active: nextValue === "active" }))
          }
          options={[
            {
              label: t("inventory.editor.fields.active.options.active"),
              value: "active",
            },
            {
              label: t("inventory.editor.fields.active.options.inactive"),
              value: "inactive",
            },
          ]}
          value={values.active === false ? "inactive" : "active"}
        />
      </View>
    </FormCard>
  );
}
