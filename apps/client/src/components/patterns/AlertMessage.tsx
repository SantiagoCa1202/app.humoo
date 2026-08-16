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
        borderCurve: "continuous",
        borderColor: appearance.border,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        padding: theme.spacing[4],
      }}
    >
      <AppText style={{ color: appearance.accent }} variant="bodySmall">
        {message}
      </AppText>
    </View>
  );
}
