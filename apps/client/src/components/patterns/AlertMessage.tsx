import { View } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type AlertMessageProps = {
  tone?: "info" | "error" | "success";
  message: string;
};

export function AlertMessage({
  tone = "info",
  message,
}: AlertMessageProps) {
  const { theme } = useAppTheme();
  const color =
    tone === "error"
      ? theme.colors.danger
      : tone === "success"
      ? theme.colors.success
      : theme.colors.info;

  return (
    <View
      style={{
        backgroundColor: `${color}18`,
        borderColor: `${color}55`,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        padding: 14,
      }}
    >
      <AppText style={{ color }}>{message}</AppText>
    </View>
  );
}
