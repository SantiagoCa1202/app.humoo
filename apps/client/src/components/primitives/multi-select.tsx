import { useMemo, useState } from "react";
import { Pressable, View } from "react-native";

import { Checkbox } from "@/components/primitives/checkbox";
import {
  SelectBase,
  SelectChips,
  type SelectOption,
} from "@/components/primitives/select-base";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MultiSelectProps<T extends string> = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (values: T[]) => void;
  options: SelectOption<T>[];
  optional?: boolean;
  placeholder?: string;
  required?: boolean;
  values: T[];
};

export function MultiSelect<T extends string>({
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
  values,
}: MultiSelectProps<T>) {
  const { theme } = useAppTheme();
  const [open, setOpen] = useState(false);
  const selectedOptions = useMemo(
    () => options.filter((option) => values.includes(option.value)),
    [options, values]
  );

  const toggleValue = (nextValue: T) => {
    if (values.includes(nextValue)) {
      onChange(values.filter((value) => value !== nextValue));
      return;
    }

    onChange([...values, nextValue]);
  };

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
            const isSelected = values.includes(option.value);

            return (
              <Pressable
                key={option.value}
                disabled={option.disabled}
                onPress={() => toggleValue(option.value)}
              >
                <Checkbox
                  checked={isSelected}
                  disabled={option.disabled}
                  label={option.label}
                  onChange={() => toggleValue(option.value)}
                />
              </Pressable>
            );
          })}
        </View>
      )}
      required={required}
      triggerContent={
        selectedOptions.length > 0 ? (
          <SelectChips
            disabled={disabled}
            labels={selectedOptions.map((option) => option.label)}
            onRemove={(optionLabel) => {
              const optionToRemove = selectedOptions.find(
                (option) => option.label === optionLabel
              );

              if (optionToRemove) {
                toggleValue(optionToRemove.value);
              }
            }}
          />
        ) : (
          <Text variant="body">{placeholder}</Text>
        )
      }
      triggerValueEmpty={selectedOptions.length === 0}
    />
  );
}
