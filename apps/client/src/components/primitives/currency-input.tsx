import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { NumberField } from "@/components/primitives/number-field";
import { Select, type SelectProps } from "@/components/primitives/select";
import { useAppTheme } from "@/theme/ThemeProvider";

export type CurrencyOption<T extends string> = SelectProps<T>["options"][number];

export type CurrencyInputProps<T extends string> = {
  accessibilityLabel?: string;
  currencies: CurrencyOption<T>[];
  currency?: T;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (value: number) => void;
  onCurrencyChange: (currency: T) => void;
  value: number;
};

function getCurrencySymbol(code?: string) {
  if (code === "USD" || code === "CAD" || code === "MXN") {
    return "$";
  }

  if (code === "EUR") {
    return "EUR";
  }

  if (code === "GBP") {
    return "GBP";
  }

  return code;
}

export function CurrencyInput<T extends string>({
  accessibilityLabel,
  currencies,
  currency,
  disabled = false,
  error,
  helperText,
  label,
  onChange,
  onCurrencyChange,
  value,
}: CurrencyInputProps<T>) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <NumberField
        accessibilityLabel={accessibilityLabel ?? label ?? t("forms.currency.label")}
        disabled={disabled}
        error={error}
        helperText={helperText}
        label={label ?? t("forms.currency.label")}
        onChange={onChange}
        prefix={getCurrencySymbol(currency)}
        value={value}
        style={{
          fontVariant: ["tabular-nums"],
        }}
      />
      <Select
        accessibilityLabel={t("forms.currency.currencyLabel")}
        disabled={disabled}
        onChange={onCurrencyChange}
        options={currencies}
        placeholder={t("forms.currency.selectCurrency")}
        value={currency}
      />
    </View>
  );
}
