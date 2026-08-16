import { useMemo } from "react";
import type { TextInputProps } from "react-native";
import { useTranslation } from "react-i18next";

import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { TextFieldBase } from "@/components/primitives/text-field-base";

export type NumberFieldProps = Omit<
  TextInputProps,
  "keyboardType" | "onChange" | "onChangeText" | "value"
> & {
  accessibilityLabel?: string;
  error?: string;
  helperText?: string;
  label?: string;
  max?: number;
  min?: number;
  onChange: (value: number) => void;
  prefix?: string;
  step?: number;
  suffix?: string;
  value: number;
};

function clamp(value: number, min?: number, max?: number) {
  let nextValue = value;

  if (typeof min === "number" && nextValue < min) {
    nextValue = min;
  }

  if (typeof max === "number" && nextValue > max) {
    nextValue = max;
  }

  return nextValue;
}

export function NumberField({
  accessibilityLabel,
  error,
  helperText,
  label,
  max,
  min,
  onChange,
  prefix,
  step = 1,
  suffix,
  value,
  ...props
}: NumberFieldProps) {
  const { t } = useTranslation("common");
  const canDecrement = typeof min === "number" ? value > min : true;
  const canIncrement = typeof max === "number" ? value < max : true;
  const valueLabel = useMemo(() => `${value}`, [value]);

  return (
    <TextFieldBase
      accessibilityLabel={accessibilityLabel ?? label}
      error={error}
      helperText={helperText}
      keyboardType="numeric"
      label={label}
      leftAdornment={
        prefix ? (
          <Text tone="secondary" variant="bodySmall">
            {prefix}
          </Text>
        ) : (
          <IconButton
            accessibilityLabel={t("decreaseValue", {
              field: label ?? t("value"),
            })}
            disabled={!canDecrement || props.editable === false}
            icon={<Text variant="bodySmall">-</Text>}
            onPress={() => onChange(clamp(value - step, min, max))}
            shape="circle"
            size="sm"
            variant="ghost"
          />
        )
      }
      onChangeText={(nextValue) => {
        const normalized = nextValue.replace(/[^0-9.-]/g, "");
        const parsed = Number(normalized);

        if (!Number.isNaN(parsed)) {
          onChange(clamp(parsed, min, max));
        }
      }}
      rightAdornment={
        suffix ? (
          <Text tone="secondary" variant="bodySmall">
            {suffix}
          </Text>
        ) : (
          <IconButton
            accessibilityLabel={t("increaseValue", {
              field: label ?? t("value"),
            })}
            disabled={!canIncrement || props.editable === false}
            icon={<Text variant="bodySmall">+</Text>}
            onPress={() => onChange(clamp(value + step, min, max))}
            shape="circle"
            size="sm"
            variant="ghost"
          />
        )
      }
      value={valueLabel}
      {...props}
    />
  );
}
