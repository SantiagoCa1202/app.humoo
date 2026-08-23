import { useEffect, useMemo, useState } from "react";
import { View, type TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { DatePicker } from "@/components/primitives/date-picker";
import { TimePicker } from "@/components/primitives/time-picker";
import {
  formatDateTimePreview,
  formatIsoForDateTimeInput,
  isValidTimeZone,
  localDateTimeInputToIso,
} from "@/utils/date-time";
import { useAppTheme } from "@/theme/ThemeProvider";

export type DateTimeFieldProps = Omit<
  TextInputProps,
  "helperText" | "keyboardType" | "onChange" | "onChangeText" | "value"
> & {
  error?: string;
  helperText?: string;
  label?: string;
  locale?: string;
  onChange: (value: string | null) => void;
  optional?: boolean;
  required?: boolean;
  timeZone: string;
  value?: string | null;
};

function splitLocalValue(value: string, timeZone: string) {
  const localValue = formatIsoForDateTimeInput(value, timeZone);

  return {
    date: localValue.slice(0, 10),
    time: localValue.slice(11, 16),
  };
}

export function DateTimeField({
  accessibilityLabel,
  editable,
  error,
  helperText,
  label,
  locale,
  onBlur,
  onChange,
  onFocus,
  optional = false,
  required = false,
  timeZone,
  value,
}: DateTimeFieldProps) {
  const { i18n, t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialValue = value ? splitLocalValue(value, timeZone) : { date: "", time: "" };
  const [dateValue, setDateValue] = useState(initialValue.date);
  const [timeValue, setTimeValue] = useState(initialValue.time);

  useEffect(() => {
    const nextValue = value ? splitLocalValue(value, timeZone) : { date: "", time: "" };
    setDateValue(nextValue.date);
    setTimeValue(nextValue.time);
  }, [timeZone, value]);

  const preview = useMemo(
    () => formatDateTimePreview(value, locale ?? i18n.language, timeZone),
    [i18n.language, locale, timeZone, value]
  );
  const resolvedHelperText = useMemo(() => {
    if (!isValidTimeZone(timeZone)) {
      return t("forms.dateTimeField.invalidTimeZone");
    }

    if (preview && helperText) {
      return `${helperText} ${t("forms.dateTimeField.preview", { value: preview })}`;
    }

    if (preview) {
      return t("forms.dateTimeField.preview", { value: preview });
    }

    return helperText ?? t("forms.dateTimeField.helper");
  }, [helperText, preview, t, timeZone]);

  const emitValue = (nextDate: string, nextTime: string) => {
    if (!nextDate || !nextTime) {
      onChange(null);
      return;
    }

    onChange(localDateTimeInputToIso(`${nextDate}T${nextTime}`, timeZone));
  };

  return (
    <View style={{ gap: theme.spacing[3] }}>
      <DatePicker
        accessibilityLabel={accessibilityLabel ?? label}
        disabled={editable === false}
        error={error}
        helperText={resolvedHelperText}
        label={label}
        onBlur={onBlur}
        onChange={(nextDate) => {
          const resolvedDate = nextDate ?? "";
          setDateValue(resolvedDate);
          emitValue(resolvedDate, timeValue);
        }}
        onFocus={onFocus}
        optional={optional}
        required={required}
        timeZone={timeZone}
        value={dateValue}
      />
      <TimePicker
        accessibilityLabel={`${accessibilityLabel ?? label ?? t("forms.dateTimeField.dateLabel")} ${t("forms.dateTimeField.timeLabel")}`}
        disabled={editable === false}
        label={t("forms.dateTimeField.timeLabel")}
        onChange={(nextTime) => {
          const resolvedTime = nextTime ?? "";
          setTimeValue(resolvedTime);
          emitValue(dateValue, resolvedTime);
        }}
        value={timeValue}
      />
    </View>
  );
}
