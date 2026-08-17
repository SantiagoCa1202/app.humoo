import { useTranslation } from "react-i18next";

import {
  EntityPicker,
  type EntityPickerProps,
} from "@/components/primitives/entity-picker";
import {
  createSupplierEntityOptions,
  formatPurchasingCurrency,
  formatSupplierLeadTime,
  type SupplierRecord,
} from "@/features/purchasing";

export type SupplierSelectorProps = Omit<
  EntityPickerProps<string>,
  "entities" | "onChange" | "value"
> & {
  onChange: (supplierId: string) => void;
  suppliers: SupplierRecord[];
  value?: string;
};

export function SupplierSelector({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onChange,
  placeholder,
  searchable = true,
  suppliers,
  value,
}: SupplierSelectorProps) {
  const { t, i18n } = useTranslation("common");

  return (
    <EntityPicker
      accessibilityLabel={
        accessibilityLabel ?? t("purchasing.forms.purchaseOrder.fields.supplier.accessibilityLabel")
      }
      disabled={disabled}
      entities={createSupplierEntityOptions(
        suppliers,
        (leadTimeDays) => formatSupplierLeadTime(leadTimeDays, t),
        (amount, currency) => formatPurchasingCurrency(amount, currency, i18n.language),
        t("purchasing.forms.purchaseOrder.fields.supplier.preferred")
      )}
      error={error}
      helperText={helperText}
      label={label ?? t("purchasing.forms.purchaseOrder.fields.supplier.label")}
      onChange={onChange}
      placeholder={
        placeholder ?? t("purchasing.forms.purchaseOrder.fields.supplier.placeholder")
      }
      searchable={searchable}
      value={value}
    />
  );
}
