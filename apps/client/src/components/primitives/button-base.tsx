import type { PressableStateCallbackType, ViewStyle } from "react-native";

import type { SemanticStatusTone } from "@/theme/status-config";
import { getSemanticToneAppearance } from "@/theme/status-config";
import type { AppTheme } from "@/theme/themes";

export type ButtonVariant =
  | "primary"
  | "secondary"
  | "outline"
  | "ghost"
  | "destructive";

export type ButtonSize = "sm" | "md" | "lg";

type ButtonStateVisual = {
  background: string;
  border: string;
  text: string;
  shadow: keyof AppTheme["shadows"];
  hoverBackground?: string;
  pressedBackground?: string;
};

export function getButtonSizeStyles(theme: AppTheme, size: ButtonSize) {
  if (size === "sm") {
    return {
      gap: theme.spacing[2],
      icon: theme.iconSizes.sm,
      minHeight: theme.spacing[10],
      paddingHorizontal: theme.spacing[4],
      paddingVertical: theme.spacing[2],
      textVariant: "bodySmall" as const,
    };
  }

  if (size === "lg") {
    return {
      gap: theme.spacing[3],
      icon: theme.iconSizes.lg,
      minHeight: theme.spacing[12],
      paddingHorizontal: theme.spacing[6],
      paddingVertical: theme.spacing[4],
      textVariant: "bodyMedium" as const,
    };
  }

  return {
    gap: theme.spacing[2],
    icon: theme.iconSizes.md,
    minHeight: theme.layout.controlHeight,
    paddingHorizontal: theme.spacing[5],
    paddingVertical: theme.spacing[3],
    textVariant: "label" as const,
  };
}

function getDestructiveTokens(theme: AppTheme): ButtonStateVisual {
  return {
    background: theme.colors.status.danger,
    border: theme.colors.status.danger,
    hoverBackground: theme.colors.status.dangerForeground,
    pressedBackground: theme.colors.status.dangerForeground,
    shadow: "sm",
    text: theme.colors.text.inverse,
  };
}

export function getButtonVariantTokens(
  theme: AppTheme,
  variant: ButtonVariant,
  disabled?: boolean,
  loading?: boolean
): ButtonStateVisual {
  if (disabled || loading) {
    return theme.components.button.disabled;
  }

  if (variant === "destructive") {
    return getDestructiveTokens(theme);
  }

  return theme.components.button[variant];
}

export function getButtonSpinnerVariant(variant: ButtonVariant, disabled?: boolean) {
  if (disabled) {
    return "muted" as const;
  }

  return variant === "primary" || variant === "destructive"
    ? ("inverse" as const)
    : ("neutral" as const);
}

export function getButtonStateStyle(
  theme: AppTheme,
  variant: ButtonVariant,
  size: ButtonSize,
  state: PressableStateCallbackType,
  options: {
    disabled?: boolean;
    focused?: boolean;
    fullWidth?: boolean;
    loading?: boolean;
    shape?: "rounded" | "circle";
  }
): ViewStyle[] {
  const tokens = getButtonVariantTokens(
    theme,
    variant,
    options.disabled,
    options.loading
  );
  const metrics = getButtonSizeStyles(theme, size);
  const backgroundColor = options.disabled || options.loading
    ? tokens.background
    : state.pressed
    ? tokens.pressedBackground ?? tokens.background
    : state.hovered
    ? tokens.hoverBackground ?? tokens.background
    : tokens.background;

  const focusAppearance = getSemanticToneAppearance(theme, "primary");

  return [
    {
      alignItems: "center",
      backgroundColor,
      borderColor: options.focused ? focusAppearance.accent : tokens.border,
      borderCurve: "continuous",
      borderRadius:
        options.shape === "circle" ? theme.radius.full : theme.radius.md,
      borderWidth: variant === "ghost" ? 0 : 1,
      flexDirection: "row",
      gap: metrics.gap,
      justifyContent: "center",
      minHeight: metrics.minHeight,
      opacity: options.disabled ? 0.9 : 1,
      paddingHorizontal: metrics.paddingHorizontal,
      paddingVertical: metrics.paddingVertical,
      width: options.fullWidth ? "100%" : undefined,
    },
    theme.shadows[tokens.shadow],
  ];
}

export function getToneFromVariant(
  variant: Exclude<ButtonVariant, "destructive"> | "destructive"
): SemanticStatusTone | "neutral" {
  if (variant === "primary") {
    return "primary";
  }

  if (variant === "destructive") {
    return "danger";
  }

  return "neutral";
}
