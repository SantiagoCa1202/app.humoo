import { ActivityIndicator, View } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

export function FullScreenLoader({ label }: { label: string }) {
  const { theme } = useAppTheme();

  return (
    <View
      style={{
        alignItems: "center",
        backgroundColor: theme.colors.background,
        flex: 1,
        gap: 14,
        justifyContent: "center",
        padding: 24,
      }}
    >
      <ActivityIndicator color={theme.colors.primary} size="large" />
      <AppText muted>{label}</AppText>
    </View>
  );
}
