import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { EntityPicker } from "@/components/primitives/entity-picker";
import { buildTimeZoneOption, getSupportedTimeZones } from "@/utils/timezones";

export type TimezonePickerProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (value: string) => void;
  placeholder?: string;
  searchable?: boolean;
  timeZones?: string[];
  value?: string;
};

export function TimezonePicker({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onChange,
  placeholder,
  searchable = true,
  timeZones,
  value,
}: TimezonePickerProps) {
  const { i18n, t } = useTranslation("common");
  const options = useMemo(
    () =>
      (timeZones ?? getSupportedTimeZones(value)).map((timeZone) =>
        buildTimeZoneOption(timeZone, i18n.language)
      ),
    [i18n.language, timeZones, value]
  );

  return (
    <EntityPicker
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled}
      entities={options}
      error={error}
      helperText={helperText}
      label={label}
      onChange={onChange}
      placeholder={placeholder ?? t("forms.timezone.placeholder")}
      searchable={searchable}
      value={value}
    />
  );
}
