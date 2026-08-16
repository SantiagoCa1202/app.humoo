import { useMemo, useState } from "react";
import { Pressable, View } from "react-native";

import {
  SelectBase,
  type SelectOption,
} from "@/components/primitives/select-base";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SelectProps<T extends string> = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (value: T) => void;
  options: SelectOption<T>[];
  optional?: boolean;
  placeholder?: string;
  required?: boolean;
  value?: T;
};

export function Select<T extends string>({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onChange,
  options,
  optional = false,
  placeholder,
  required = false,
  value,
}: SelectProps<T>) {
  const { theme } = useAppTheme();
  const [open, setOpen] = useState(false);
  const selectedOption = useMemo(
    () => options.find((option) => option.value === value),
    [options, value]
  );

  return (
    <SelectBase
      accessibilityLabel={accessibilityLabel}
      disabled={disabled}
      error={error}
      helperText={helperText}
      label={label}
      onDismiss={() => setOpen(false)}
      onOpen={() => setOpen(true)}
      open={open}
      optional={optional}
      placeholder={placeholder}
      renderOptions={() => (
        <View style={{ gap: theme.spacing[1] }}>
          {options.map((option) => {
            const isSelected = option.value === value;

            return (
              <Pressable
                key={option.value}
                accessibilityRole="button"
                disabled={option.disabled}
                onPress={() => {
                  onChange(option.value);
                  setOpen(false);
                }}
                style={({ hovered, pressed }) => ({
                  backgroundColor: isSelected
                    ? theme.colors.brand.soft
                    : pressed || hovered
                    ? theme.colors.background.subtle
                    : "transparent",
                  borderColor: isSelected
                    ? theme.colors.brand.primary
                    : "transparent",
                  borderCurve: "continuous",
                  borderRadius: theme.radius.md,
                  borderWidth: 1,
                  opacity: option.disabled ? 0.6 : 1,
                  paddingHorizontal: theme.spacing[3],
                  paddingVertical: theme.spacing[3],
                })}
              >
                <Text
                  tone={isSelected ? "primary" : option.disabled ? "muted" : "default"}
                  variant="body"
                >
                  {option.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
      )}
      required={required}
      triggerContent={<Text variant="body">{selectedOption?.label}</Text>}
      triggerValueEmpty={!selectedOption}
    />
  );
}
