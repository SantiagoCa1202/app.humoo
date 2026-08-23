import { Platform, type TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { Select } from "@/components/primitives/select";
import { TextFieldBase } from "@/components/primitives/text-field-base";

export type TimePickerProps = {
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
  step?: number;
  value?: string | null;
};

function normalizeTimeValue(value?: string | null) {
  if (!value) {
    return "";
  }

  const match = /^(\d{2}):(\d{2})/.exec(value);

  return match ? `${match[1]}:${match[2]}` : "";
}

function buildTimeOptions(locale: string, step: number, selectedValue: string) {
  const formatter = new Intl.DateTimeFormat(locale, {
    hour: "numeric",
    minute: "2-digit",
    timeZone: "UTC",
  });
  const options: Array<{ label: string; value: string }> = [];

  for (let totalMinutes = 0; totalMinutes < 24 * 60; totalMinutes += step) {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    const value = `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;

    options.push({
      label: formatter.format(new Date(Date.UTC(2000, 0, 1, hours, minutes))),
      value,
    });
  }

  if (selectedValue && !options.some((option) => option.value === selectedValue)) {
    const [hours, minutes] = selectedValue.split(":").map(Number);

    options.push({
      label: formatter.format(new Date(Date.UTC(2000, 0, 1, hours, minutes))),
      value: selectedValue,
    });
  }

  return options;
}

export function TimePicker({
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
  step = 15,
  value,
}: TimePickerProps) {
  const { i18n, t } = useTranslation("common");
  const normalizedValue = normalizeTimeValue(value);
  const resolvedPlaceholder = placeholder ?? t("forms.timePicker.placeholder");

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
        {...({ step: step * 60, type: "time" } as unknown as TextInputProps)}
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
      options={buildTimeOptions(i18n.language, step, normalizedValue)}
      optional={optional}
      placeholder={resolvedPlaceholder}
      required={required}
      value={normalizedValue || undefined}
    />
  );
}
