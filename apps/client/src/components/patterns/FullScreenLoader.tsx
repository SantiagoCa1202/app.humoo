import { View } from "react-native";

import { Spinner } from "@/components/primitives/spinner";
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
      <Spinner centered label={label} size="lg" variant="primary" />
    </View>
  );
}
