import { View } from "react-native";

import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { Spinner } from "@/components/primitives/spinner";
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
        borderCurve: "continuous",
        borderColor: appearance.border,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        gap: theme.spacing[2],
        padding: theme.spacing[4],
      }}
    >
      {tone === "loading" ? (
        <Spinner variant="primary" />
      ) : null}
      <View style={{ gap: theme.spacing[1] }}>
        <AppText
          variant="h4"
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
