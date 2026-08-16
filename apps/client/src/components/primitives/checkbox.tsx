import { Pressable, View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type CheckboxProps = {
  accessibilityLabel?: string;
  checked: boolean;
  description?: string;
  disabled?: boolean;
  indeterminate?: boolean;
  label?: string;
  onChange: (nextValue: boolean) => void;
};

export function Checkbox({
  accessibilityLabel,
  checked,
  description,
  disabled = false,
  indeterminate = false,
  label,
  onChange,
}: CheckboxProps) {
  const { theme } = useAppTheme();
  const isActive = checked || indeterminate;
  const boxBackground = disabled
    ? theme.colors.interaction.disabledBackground
    : isActive
    ? theme.colors.brand.primary
    : "transparent";
  const boxBorder = disabled
    ? theme.colors.interaction.disabledBackground
    : isActive
    ? theme.colors.brand.primary
    : theme.colors.border.strong;
  const indicator = indeterminate ? "-" : checked ? "x" : "";

  return (
    <Pressable
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityRole="checkbox"
      accessibilityState={{
        checked: indeterminate ? "mixed" : checked,
        disabled,
      }}
      disabled={disabled}
      onPress={() => onChange(!checked)}
      style={({ hovered, pressed }) => ({
        alignItems: "flex-start",
        backgroundColor:
          !disabled && (pressed || hovered)
            ? theme.colors.background.subtle
            : "transparent",
        borderCurve: "continuous",
        borderRadius: theme.radius.md,
        flexDirection: "row",
        gap: theme.spacing[3],
        opacity: disabled ? 0.7 : 1,
        padding: theme.spacing[2],
      })}
    >
      <View
        style={{
          alignItems: "center",
          backgroundColor: boxBackground,
          borderColor: boxBorder,
          borderCurve: "continuous",
          borderRadius: theme.radius.sm,
          borderWidth: 1,
          height: theme.spacing[5],
          justifyContent: "center",
          marginTop: theme.spacing[1],
          width: theme.spacing[5],
        }}
      >
        {indicator ? (
          <Text
            tone="inverse"
            variant="caption"
            style={{
              color: disabled
                ? theme.colors.interaction.disabledText
                : theme.colors.text.inverse,
            }}
          >
            {indicator}
          </Text>
        ) : null}
      </View>
      {(label || description) ? (
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          {label ? (
            <Text tone={disabled ? "muted" : "default"} variant="body">
              {label}
            </Text>
          ) : null}
          {description ? (
            <Text tone="muted" variant="caption">
              {description}
            </Text>
          ) : null}
        </View>
      ) : null}
    </Pressable>
  );
}
