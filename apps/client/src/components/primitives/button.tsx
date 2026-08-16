import React from "react";
import {
  Pressable,
  View,
  type PressableProps,
  type StyleProp,
  type ViewStyle,
} from "react-native";

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
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ButtonProps = Omit<PressableProps, "children" | "onPress" | "style"> & {
  accessibilityLabel?: string;
  children?: React.ReactNode;
  containerStyle?: StyleProp<ViewStyle>;
  fullWidth?: boolean;
  label?: string;
  leftIcon?: React.ReactNode;
  loading?: boolean;
  onPress?: () => void | Promise<void>;
  rightIcon?: React.ReactNode;
  size?: ButtonSize;
  variant?: ButtonVariant;
};

export function Button({
  accessibilityLabel,
  children,
  containerStyle,
  disabled,
  fullWidth,
  label,
  leftIcon,
  loading,
  onPress,
  rightIcon,
  size = "md",
  variant = "primary",
  ...props
}: ButtonProps) {
  const { theme } = useAppTheme();
  const content = children ?? label;
  const isDisabled = Boolean(disabled);
  const isLoading = Boolean(loading);
  const tokens = getButtonVariantTokens(theme, variant, isDisabled, isLoading);
  const metrics = getButtonSizeStyles(theme, size);

  return (
    <Pressable
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityRole="button"
      disabled={isDisabled || isLoading}
      onPress={onPress ? () => void onPress() : undefined}
      style={(state) => [
        ...getButtonStateStyle(theme, variant, size, state, {
          disabled: isDisabled,
          fullWidth,
          loading: isLoading,
        }),
        containerStyle as ViewStyle,
      ]}
      {...props}
    >
      <View
        style={{
          alignItems: "center",
          flexDirection: "row",
          gap: metrics.gap,
          justifyContent: "center",
        }}
      >
        {isLoading ? (
          <Spinner
            size={size === "lg" ? "md" : "sm"}
            variant={getButtonSpinnerVariant(variant, isDisabled)}
          />
        ) : (
          <IconSlot
            color={tokens.text}
            icon={leftIcon}
            size={metrics.icon}
          />
        )}
        {typeof content === "string" || typeof content === "number" ? (
          <Text
            tone={tokens.text === theme.colors.text.inverse ? "inverse" : "default"}
            variant={metrics.textVariant}
            style={{ color: tokens.text }}
          >
            {content}
          </Text>
        ) : (
          content
        )}
        {!isLoading ? (
          <IconSlot
            color={tokens.text}
            icon={rightIcon}
            size={metrics.icon}
          />
        ) : null}
      </View>
    </Pressable>
  );
}
