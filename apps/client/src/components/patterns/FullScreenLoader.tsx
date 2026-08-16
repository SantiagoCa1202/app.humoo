import { View } from "react-native";

import { LoadingState } from "@/components/patterns/loading-state";
import { useAppTheme } from "@/theme/ThemeProvider";

export function FullScreenLoader({ label }: { label?: string }) {
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
      <LoadingState compact description={label} />
    </View>
  );
}
