import { View, type ViewProps } from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

export function Card({ style, ...props }: ViewProps) {
  const { theme } = useAppTheme();

  return (
    <View
      {...props}
      style={[
        {
          backgroundColor: theme.colors.surface,
          borderColor: theme.colors.borderStrong,
          borderRadius: theme.radius.lg,
          borderWidth: 1,
          padding: theme.layout.cardPadding,
          shadowColor: theme.colors.shadow,
          shadowOffset: { width: 0, height: 14 },
          shadowOpacity: 1,
          shadowRadius: 30,
        },
        style,
      ]}
    />
  );
}
