import { ActivityIndicator, View } from "react-native";

import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import {
  getAppStateAppearance,
  type AppStateTone,
} from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type StateBlockProps = {
  title: string;
  description?: string;
  tone?: AppStateTone;
  actionLabel?: string;
  onAction?: () => void | Promise<void>;
};

export function StateBlock({
  title,
  description,
  tone = "info",
  actionLabel,
  onAction,
}: StateBlockProps) {
  const { theme } = useAppTheme();
  const appearance = getAppStateAppearance(theme, tone);

  return (
    <View
      style={{
        alignItems: "flex-start",
        backgroundColor: appearance.background,
        borderColor: appearance.border,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        gap: 10,
        padding: 16,
      }}
    >
      {tone === "loading" ? (
        <ActivityIndicator color={appearance.accent} />
      ) : null}
      <View style={{ gap: 4 }}>
        <AppText
          variant="subtitle"
          style={{
            color: appearance.accent,
          }}
        >
          {title}
        </AppText>
        {description ? <AppText muted>{description}</AppText> : null}
      </View>
      {actionLabel && onAction ? (
        <AppButton label={actionLabel} onPress={onAction} variant="secondary" />
      ) : null}
    </View>
  );
}
