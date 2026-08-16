import { useState } from "react";
import {
  Pressable,
  View,
  type PressableProps,
  type StyleProp,
  type ViewProps,
  type ViewStyle,
} from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

type CardVariant = "default" | "outlined" | "muted" | "elevated";
type CardPadding = "none" | "sm" | "md" | "lg" | "xl";
type CardRadius = "sm" | "md" | "lg" | "xl";

export type BaseCardProps = Omit<ViewProps, "style"> &
  Pick<
    PressableProps,
    "accessibilityHint" | "accessibilityLabel" | "onPress" | "onLongPress"
  > & {
    children?: React.ReactNode;
    disabled?: boolean;
    padding?: CardPadding;
    radius?: CardRadius;
    selected?: boolean;
    style?: StyleProp<ViewStyle>;
    variant?: CardVariant;
  };

function getPaddingValue(theme: ReturnType<typeof useAppTheme>["theme"], padding: CardPadding) {
  if (padding === "none") {
    return theme.spacing[0];
  }

  if (padding === "sm") {
    return theme.spacing[3];
  }

  if (padding === "md") {
    return theme.spacing[4];
  }

  if (padding === "xl") {
    return theme.spacing[8];
  }

  return theme.spacing[6];
}

function getRadiusValue(theme: ReturnType<typeof useAppTheme>["theme"], radius: CardRadius) {
  if (radius === "sm") {
    return theme.radius.sm;
  }

  if (radius === "md") {
    return theme.radius.md;
  }

  if (radius === "xl") {
    return theme.radius.xl;
  }

  return theme.radius.lg;
}

export function BaseCard({
  accessibilityHint,
  accessibilityLabel,
  children,
  disabled = false,
  onLongPress,
  onPress,
  padding = "lg",
  radius = "lg",
  selected = false,
  style,
  variant = "default",
  ...props
}: BaseCardProps) {
  const { theme } = useAppTheme();
  const [isFocused, setIsFocused] = useState(false);
  const tokens = theme.components.card[variant];
  const paddingValue = getPaddingValue(theme, padding);
  const radiusValue = getRadiusValue(theme, radius);
  const baseStyle: ViewStyle = {
    backgroundColor: disabled
      ? theme.colors.interaction.disabledBackground
      : tokens.background,
    borderColor: isFocused || selected ? theme.colors.brand.primary : tokens.border,
    borderCurve: "continuous",
    borderRadius: radiusValue,
    borderWidth: 1,
    opacity: disabled ? 0.72 : 1,
    padding: paddingValue,
  };

  if (!onPress && !onLongPress) {
    return (
      <View
        accessibilityLabel={accessibilityLabel}
        accessibilityState={{ disabled, selected }}
        style={[baseStyle, theme.shadows[tokens.shadow], style]}
        {...props}
      >
        {children}
      </View>
    );
  }

  return (
    <Pressable
      accessibilityHint={accessibilityHint}
      accessibilityLabel={accessibilityLabel}
      accessibilityRole="button"
      accessibilityState={{ disabled, selected }}
      disabled={disabled}
      onBlur={() => setIsFocused(false)}
      onFocus={() => setIsFocused(true)}
      onLongPress={onLongPress}
      onPress={onPress}
      style={({ hovered, pressed }) => [
        baseStyle,
        {
          backgroundColor: disabled
            ? theme.colors.interaction.disabledBackground
            : pressed
            ? theme.colors.background.pressed
            : hovered
            ? variant === "outlined"
              ? theme.colors.background.subtle
              : tokens.background
            : tokens.background,
        },
        theme.shadows[tokens.shadow],
        style as ViewStyle,
      ]}
      {...props}
    >
      {children}
    </Pressable>
  );
}
