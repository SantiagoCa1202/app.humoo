import { Platform, type TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { Select } from "@/components/primitives/select";
import { TextFieldBase } from "@/components/primitives/text-field-base";
import {
  formatIsoForDateTimeInput,
  isValidTimeZone,
} from "@/utils/date-time";

export type DatePickerProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onBlur?: TextInputProps["onBlur"];
  onChange: (value: string | null) => void;
  onFocus?: TextInputProps["onFocus"];
  optional?: boolean;
  placeholder?: string;
  required?: boolean;
  timeZone?: string;
  value?: string | null;
};

function resolveTimeZone(timeZone?: string) {
  return isValidTimeZone(timeZone)
    ? timeZone!
    : Intl.DateTimeFormat().resolvedOptions().timeZone;
}

function normalizeDateValue(value?: string | null, timeZone?: string) {
  if (!value) {
    return "";
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }

  return formatIsoForDateTimeInput(value, resolveTimeZone(timeZone)).slice(0, 10);
}

function buildDateOptions(value: string, locale: string) {
  const selectedYear = Number(value.slice(0, 4));
  const currentYear = new Date().getFullYear();
  const year = /^\d{4}-\d{2}-\d{2}$/.test(value) ? selectedYear : currentYear;
  const start = Date.UTC(Math.min(year, currentYear) - 1, 0, 1);
  const end = Date.UTC(Math.max(year, currentYear) + 2, 11, 31);
  const formatter = new Intl.DateTimeFormat(locale, {
    dateStyle: "medium",
    timeZone: "UTC",
  });
  const options: Array<{ label: string; value: string }> = [];

  for (let timestamp = start; timestamp <= end; timestamp += 86_400_000) {
    const date = new Date(timestamp);
    const dateValue = date.toISOString().slice(0, 10);

    options.push({
      label: formatter.format(date),
      value: dateValue,
    });
  }

  return options;
}

export function DatePicker({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onBlur,
  onChange,
  onFocus,
  optional = false,
  placeholder,
  required = false,
  timeZone,
  value,
}: DatePickerProps) {
  const { i18n, t } = useTranslation("common");
  const normalizedValue = normalizeDateValue(value, timeZone);
  const resolvedPlaceholder = placeholder ?? t("forms.datePicker.placeholder");

  if (Platform.OS === "web") {
    return (
      <TextFieldBase
        accessibilityLabel={accessibilityLabel ?? label}
        editable={!disabled}
        error={error}
        helperText={helperText}
        label={label}
        onBlur={onBlur}
        onChangeText={(nextValue) => onChange(nextValue || null)}
        onFocus={onFocus}
        optional={optional}
        placeholder={resolvedPlaceholder}
        required={required}
        value={normalizedValue}
        {...({ type: "date" } as unknown as TextInputProps)}
      />
    );
  }

  return (
    <Select
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled}
      error={error}
      helperText={helperText}
      label={label}
      onChange={(nextValue) => onChange(nextValue)}
      options={buildDateOptions(normalizedValue, i18n.language)}
      optional={optional}
      placeholder={resolvedPlaceholder}
      required={required}
      value={normalizedValue || undefined}
    />
  );
}
