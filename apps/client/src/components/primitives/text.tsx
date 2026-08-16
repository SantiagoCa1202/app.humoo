import {
  Text as ReactNativeText,
  type StyleProp,
  type TextProps as ReactNativeTextProps,
  type TextStyle,
} from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";
import {
  getTypographyStyle,
  type AppTextVariant,
} from "@/theme/typography";

export type TextVariant = AppTextVariant;

export type TextTone =
  | "default"
  | "secondary"
  | "muted"
  | "primary"
  | "success"
  | "warning"
  | "danger"
  | "inverse";

export type TextProps = ReactNativeTextProps & {
  variant?: TextVariant;
  tone?: TextTone;
  style?: StyleProp<TextStyle>;
};

export function Text({
  style,
  variant = "body",
  tone = "default",
  ...props
}: TextProps) {
  const { theme } = useAppTheme();
  const variantStyle = getTypographyStyle(variant);
  const toneColor =
    tone === "secondary"
      ? theme.colors.text.secondary
      : tone === "muted"
      ? theme.colors.text.muted
      : tone === "primary"
      ? theme.colors.brand.primary
      : tone === "success"
      ? theme.colors.status.success
      : tone === "warning"
      ? theme.colors.status.warning
      : tone === "danger"
      ? theme.colors.status.danger
      : tone === "inverse"
      ? theme.colors.text.inverse
      : theme.colors.text.primary;

  return (
    <ReactNativeText
      {...props}
      style={[
        {
          color: toneColor,
        },
        variantStyle,
        style,
      ]}
    />
  );
}
