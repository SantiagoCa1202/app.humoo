import { ActivityIndicator, View } from "react-native";

import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type StateBlockProps = {
  title: string;
  description?: string;
  tone?: "loading" | "error" | "empty" | "info" | "success";
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
  const color =
    tone === "error"
      ? theme.colors.danger
      : tone === "success"
      ? theme.colors.success
      : tone === "loading"
      ? theme.colors.primary
      : tone === "empty"
      ? theme.colors.textMuted
      : theme.colors.info;

  return (
    <View
      style={{
        alignItems: "flex-start",
        backgroundColor: `${color}14`,
        borderColor: `${color}3D`,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        gap: 10,
        padding: 16,
      }}
    >
      {tone === "loading" ? (
        <ActivityIndicator color={theme.colors.primary} />
      ) : null}
      <View style={{ gap: 4 }}>
        <AppText
          variant="subtitle"
          style={{
            color: tone === "empty" ? theme.colors.text : color,
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
