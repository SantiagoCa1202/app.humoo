import { Pressable, View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RadioOption<T extends string> = {
  description?: string;
  disabled?: boolean;
  label: string;
  value: T;
};

export type RadioGroupProps<T extends string> = {
  accessibilityLabel?: string;
  direction?: "horizontal" | "vertical";
  disabled?: boolean;
  label?: string;
  onChange: (value: T) => void;
  options: RadioOption<T>[];
  value?: T;
};

export function RadioGroup<T extends string>({
  accessibilityLabel,
  direction = "vertical",
  disabled = false,
  label,
  onChange,
  options,
  value,
}: RadioGroupProps<T>) {
  const { theme } = useAppTheme();

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityRole="radiogroup"
      style={{ gap: theme.spacing[2] }}
    >
      {label ? <Text variant="label">{label}</Text> : null}
      <View
        style={{
          flexDirection: direction === "horizontal" ? "row" : "column",
          flexWrap: direction === "horizontal" ? "wrap" : "nowrap",
          gap: theme.spacing[2],
        }}
      >
        {options.map((option) => {
          const isSelected = option.value === value;
          const isDisabled = disabled || option.disabled === true;

          return (
            <Pressable
              key={option.value}
              accessibilityLabel={option.label}
              accessibilityRole="radio"
              accessibilityState={{ checked: isSelected, disabled: isDisabled }}
              disabled={isDisabled}
              onPress={() => onChange(option.value)}
              style={({ hovered, pressed }) => ({
                alignItems: "flex-start",
                backgroundColor:
                  !isDisabled && (pressed || hovered)
                    ? theme.colors.background.subtle
                    : "transparent",
                borderCurve: "continuous",
                borderRadius: theme.radius.md,
                flexDirection: "row",
                gap: theme.spacing[3],
                minWidth: direction === "horizontal" ? 180 : undefined,
                opacity: isDisabled ? 0.7 : 1,
                padding: theme.spacing[2],
              })}
            >
              <View
                style={{
                  alignItems: "center",
                  borderColor: isSelected
                    ? theme.colors.brand.primary
                    : theme.colors.border.strong,
                  borderRadius: theme.radius.full,
                  borderWidth: 1,
                  height: theme.spacing[5],
                  justifyContent: "center",
                  marginTop: theme.spacing[1],
                  width: theme.spacing[5],
                }}
              >
                {isSelected ? (
                  <View
                    style={{
                      backgroundColor: theme.colors.brand.primary,
                      borderRadius: theme.radius.full,
                      height: theme.spacing[2],
                      width: theme.spacing[2],
                    }}
                  />
                ) : null}
              </View>
              <View style={{ flex: 1, gap: theme.spacing[1] }}>
                <Text tone={isDisabled ? "muted" : "default"} variant="body">
                  {option.label}
                </Text>
                {option.description ? (
                  <Text tone="muted" variant="caption">
                    {option.description}
                  </Text>
                ) : null}
              </View>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}
