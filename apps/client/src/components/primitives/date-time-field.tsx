import { useEffect, useMemo, useState } from "react";
import { Platform, type TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { TextFieldBase } from "@/components/primitives/text-field-base";
import {
  formatDateTimePreview,
  formatIsoForDateTimeInput,
  isValidTimeZone,
  localDateTimeInputToIso,
  parseLocalDateTimeInput,
} from "@/utils/date-time";

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

export function DateTimeField({
  helperText,
  locale,
  onChange,
  optional,
  placeholder,
  required,
  timeZone,
  value,
  ...props
}: DateTimeFieldProps) {
  const { i18n, t } = useTranslation("common");
  const [inputValue, setInputValue] = useState(() =>
    formatIsoForDateTimeInput(value, timeZone)
  );

  useEffect(() => {
    setInputValue(formatIsoForDateTimeInput(value, timeZone));
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

  return (
    <TextFieldBase
      autoCapitalize="none"
      autoCorrect={false}
      error={props.error}
      helperText={resolvedHelperText}
      inputMode="numeric"
      keyboardType={Platform.OS === "ios" ? "numbers-and-punctuation" : "numeric"}
      label={props.label}
      onChangeText={(nextValue) => {
        setInputValue(nextValue);

        if (!nextValue.trim()) {
          onChange(null);
          return;
        }

        if (!parseLocalDateTimeInput(nextValue)) {
          return;
        }

        const nextIsoValue = localDateTimeInputToIso(nextValue, timeZone);

        if (nextIsoValue) {
          onChange(nextIsoValue);
        }
      }}
      optional={optional}
      placeholder={placeholder ?? t("forms.dateTimeField.placeholder")}
      required={required}
      value={inputValue}
      {...(Platform.OS === "web"
        ? ({
            type: "datetime-local",
          } as unknown as object)
        : {})}
      {...props}
    />
  );
}
