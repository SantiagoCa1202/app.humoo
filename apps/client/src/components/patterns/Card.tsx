import { View, type ViewProps } from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

type CardProps = ViewProps & {
  variant?: "default" | "outlined" | "muted" | "elevated" | "selected";
};

export function Card({
  style,
  variant = "elevated",
  ...props
}: CardProps) {
  const { theme } = useAppTheme();
  const tokens = theme.components.card[variant];

  return (
    <View
      {...props}
      style={[
        {
          backgroundColor: tokens.background,
          borderColor: tokens.border,
          borderRadius: theme.radius.lg,
          borderWidth: 1,
          padding: theme.layout.cardPadding,
          shadowColor: tokens.shadowColor,
          shadowOffset: { width: 0, height: 14 },
          shadowOpacity: tokens.shadowColor === "transparent" ? 0 : 1,
          shadowRadius: tokens.shadowColor === "transparent" ? 0 : 30,
        },
        style,
      ]}
    />
  );
}
