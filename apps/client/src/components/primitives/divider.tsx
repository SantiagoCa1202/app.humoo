import { StyleSheet, View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type DividerVariant = "subtle" | "default" | "strong" | "accent";
type DividerOrientation = "horizontal" | "vertical";
type DividerSpacing = "none" | "sm" | "md" | "lg";
type DividerInset = DividerSpacing | number;

export type DividerProps = {
  variant?: DividerVariant;
  orientation?: DividerOrientation;
  spacing?: DividerSpacing;
  inset?: DividerInset;
  label?: string;
};

export function Divider({
  variant = "default",
  orientation = "horizontal",
  spacing = "md",
  inset = 0,
  label,
}: DividerProps) {
  const { theme } = useAppTheme();
  const color =
    variant === "subtle"
      ? theme.colors.border.subtle
      : variant === "strong"
      ? theme.colors.border.strong
      : variant === "accent"
      ? theme.colors.brand.primary
      : theme.colors.border.default;
  const spacingValue =
    spacing === "none"
      ? theme.spacing[0]
      : spacing === "sm"
      ? theme.spacing[2]
      : spacing === "lg"
      ? theme.spacing[6]
      : theme.spacing[4];
  const insetValue =
    typeof inset === "number"
      ? inset
      : inset === "sm"
      ? theme.spacing[2]
      : inset === "lg"
      ? theme.spacing[6]
      : inset === "md"
      ? theme.spacing[4]
      : theme.spacing[0];

  const lineStyle =
    orientation === "vertical"
      ? {
          backgroundColor: color,
          height: "100%" as const,
          marginHorizontal: spacingValue,
          marginTop: insetValue,
          minHeight: theme.spacing[6],
          width: StyleSheet.hairlineWidth,
        }
      : {
          backgroundColor: color,
          flex: 1,
          height: StyleSheet.hairlineWidth,
        };

  if (orientation === "vertical" || !label) {
    return <View style={lineStyle} />;
  }

  return (
    <View
      style={{
        alignItems: "center",
        flexDirection: "row",
        gap: theme.spacing[2],
        marginVertical: spacingValue,
        paddingLeft: insetValue,
        width: "100%",
      }}
    >
      <View style={lineStyle} />
      <Text tone="muted" variant="caption">
        {label}
      </Text>
      <View style={lineStyle} />
    </View>
  );
}
