import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { NumberField } from "@/components/primitives/number-field";
import { Select, type SelectProps } from "@/components/primitives/select";
import { useAppTheme } from "@/theme/ThemeProvider";

export type QuantityUnitOption<T extends string> = SelectProps<T>["options"][number];

export type QuantityInputProps<T extends string> = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  max?: number;
  min?: number;
  onChange: (value: number) => void;
  onUnitChange: (unit: T) => void;
  step?: number;
  unit?: T;
  units: QuantityUnitOption<T>[];
  value: number;
};

export function QuantityInput<T extends string>({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  max,
  min,
  onChange,
  onUnitChange,
  step,
  unit,
  units,
  value,
}: QuantityInputProps<T>) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <NumberField
        accessibilityLabel={accessibilityLabel ?? label ?? t("forms.quantity.label")}
        disabled={disabled}
        error={error}
        helperText={helperText}
        label={label ?? t("forms.quantity.label")}
        max={max}
        min={min}
        onChange={onChange}
        step={step}
        value={value}
      />
      <Select
        accessibilityLabel={t("forms.quantity.unitLabel")}
        disabled={disabled}
        onChange={onUnitChange}
        options={units}
        placeholder={t("forms.quantity.selectUnit")}
        value={unit}
      />
    </View>
  );
}
