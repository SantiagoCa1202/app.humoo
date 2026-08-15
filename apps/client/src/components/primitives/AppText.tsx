import { Text, type TextProps } from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

type AppTextProps = TextProps & {
  variant?:
    | "body"
    | "bodyLarge"
    | "subtitle"
    | "title"
    | "hero"
    | "caption"
    | "overline"
    | "metric";
  muted?: boolean;
};

export function AppText({
  style,
  variant = "body",
  muted,
  ...props
}: AppTextProps) {
  const { theme } = useAppTheme();
  const variantStyle = {
    overline: {
      fontFamily: theme.typography.family.interfaceSemiBold,
      fontSize: theme.typography.size.overline,
      letterSpacing: 1.2,
      lineHeight: 18,
      textTransform: "uppercase" as const,
    },
    caption: {
      fontFamily: theme.typography.family.interfaceMedium,
      fontSize: theme.typography.size.caption,
      lineHeight: 18,
    },
    body: {
      fontFamily: theme.typography.family.interfaceRegular,
      fontSize: theme.typography.size.body,
      lineHeight: 24,
    },
    bodyLarge: {
      fontFamily: theme.typography.family.interfaceMedium,
      fontSize: theme.typography.size.bodyLarge,
      lineHeight: 28,
    },
    subtitle: {
      fontFamily: theme.typography.family.interfaceSemiBold,
      fontSize: theme.typography.size.bodyLarge,
      lineHeight: 28,
    },
    title: {
      fontFamily: theme.typography.family.displaySemiBold,
      fontSize: theme.typography.size.title,
      lineHeight: 34,
    },
    hero: {
      fontFamily: theme.typography.family.displayBold,
      fontSize: theme.typography.size.hero,
      lineHeight: 52,
    },
    metric: {
      fontFamily: theme.typography.family.displayBold,
      fontSize: theme.typography.size.metric,
      lineHeight: 42,
    },
  }[variant];

  return (
    <Text
      {...props}
      style={[
        {
          color: muted ? theme.colors.textMuted : theme.colors.text,
        },
        variantStyle,
        style,
      ]}
    />
  );
}
