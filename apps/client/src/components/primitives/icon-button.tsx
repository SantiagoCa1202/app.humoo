import React from "react";
import { Pressable, type PressableProps, type StyleProp, type ViewStyle } from "react-native";

import {
  getButtonSizeStyles,
  getButtonSpinnerVariant,
  getButtonStateStyle,
  getButtonVariantTokens,
  type ButtonSize,
  type ButtonVariant,
} from "@/components/primitives/button-base";
import { IconSlot } from "@/components/primitives/icon-slot";
import { Spinner } from "@/components/primitives/spinner";
import { useAppTheme } from "@/theme/ThemeProvider";

export type IconButtonProps = Omit<PressableProps, "onPress" | "style"> & {
  accessibilityLabel: string;
  containerStyle?: StyleProp<ViewStyle>;
  disabled?: boolean;
  icon: React.ReactNode;
  loading?: boolean;
  onPress?: () => void | Promise<void>;
  shape?: "rounded" | "circle";
  size?: ButtonSize;
  variant?: ButtonVariant;
};

export function IconButton({
  accessibilityLabel,
  containerStyle,
  disabled,
  icon,
  loading,
  onPress,
  shape = "rounded",
  size = "md",
  variant = "secondary",
  ...props
}: IconButtonProps) {
  const { theme } = useAppTheme();
  const tokens = getButtonVariantTokens(theme, variant, disabled, loading);
  const metrics = getButtonSizeStyles(theme, size);

  return (
    <Pressable
      accessibilityLabel={accessibilityLabel}
      accessibilityRole="button"
      disabled={disabled || loading}
      onPress={onPress ? () => void onPress() : undefined}
      style={(state) => [
        ...getButtonStateStyle(theme, variant, size, state, {
          disabled,
          loading,
          shape,
        }),
        {
          paddingHorizontal: size === "sm" ? theme.spacing[2] : theme.spacing[3],
          width: metrics.minHeight,
        },
        containerStyle as ViewStyle,
      ]}
      {...props}
    >
      {loading ? (
        <Spinner
          size={size === "lg" ? "md" : "sm"}
          variant={getButtonSpinnerVariant(variant, disabled)}
        />
      ) : (
        <IconSlot color={tokens.text} icon={icon} size={metrics.icon} />
      )}
    </Pressable>
  );
}
