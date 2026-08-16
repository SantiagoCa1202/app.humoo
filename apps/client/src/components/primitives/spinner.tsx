import { ActivityIndicator, View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type SpinnerSize = "sm" | "md" | "lg" | "xl";
type SpinnerVariant = "primary" | "neutral" | "muted" | "inverse";

export type SpinnerProps = {
  size?: SpinnerSize;
  variant?: SpinnerVariant;
  label?: string;
  centered?: boolean;
  accessibilityLabel?: string;
};

export function Spinner({
  size = "md",
  variant = "primary",
  label,
  centered = false,
  accessibilityLabel,
}: SpinnerProps) {
  const { theme } = useAppTheme();
  const indicatorSize =
    size === "sm"
      ? theme.iconSizes.sm
      : size === "lg"
      ? theme.iconSizes.lg
      : size === "xl"
      ? theme.iconSizes.xl
      : theme.iconSizes.md;
  const toneColor =
    variant === "neutral"
      ? theme.colors.text.secondary
      : variant === "muted"
      ? theme.colors.text.muted
      : variant === "inverse"
      ? theme.colors.text.inverse
      : theme.colors.brand.primary;
  const labelTone =
    variant === "neutral"
      ? "secondary"
      : variant === "muted"
      ? "muted"
      : variant === "inverse"
      ? "inverse"
      : "primary";

  if (!label && !centered) {
    return (
      <ActivityIndicator
        accessibilityLabel={accessibilityLabel}
        color={toneColor}
        size={indicatorSize}
      />
    );
  }

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? label ?? "Loading"}
      style={{
        alignItems: "center",
        gap: theme.spacing[2],
        justifyContent: "center",
      }}
    >
      <ActivityIndicator color={toneColor} size={indicatorSize} />
      {label ? (
        <Text tone={labelTone} variant="bodySmall">
          {label}
        </Text>
      ) : null}
    </View>
  );
}
