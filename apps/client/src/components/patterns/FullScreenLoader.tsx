import { ActivityIndicator, View } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

export function FullScreenLoader({ label }: { label: string }) {
  const { theme } = useAppTheme();

  return (
    <View
      style={{
        alignItems: "center",
        backgroundColor: theme.colors.background.app,
        flex: 1,
        gap: theme.spacing[3],
        justifyContent: "center",
        padding: theme.spacing[6],
      }}
    >
      <ActivityIndicator color={theme.colors.brand.primary} size="large" />
      <AppText muted>{label}</AppText>
    </View>
  );
}
