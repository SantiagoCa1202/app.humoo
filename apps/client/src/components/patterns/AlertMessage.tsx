import { View } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { getAlertAppearance, type AlertTone } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type AlertMessageProps = {
  tone?: AlertTone;
  message: string;
};

export function AlertMessage({
  tone = "info",
  message,
}: AlertMessageProps) {
  const { theme } = useAppTheme();
  const appearance = getAlertAppearance(theme, tone);

  return (
    <View
      style={{
        backgroundColor: appearance.background,
        borderColor: appearance.border,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        padding: 14,
      }}
    >
      <AppText style={{ color: appearance.accent }}>{message}</AppText>
    </View>
  );
}
