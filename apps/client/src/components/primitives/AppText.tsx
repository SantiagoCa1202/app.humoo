import { Text, type TextProps } from "react-native";

import {
  getTypographyStyle,
  type AppTextVariant,
} from "@/theme/typography";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppTextProps = TextProps & {
  variant?: AppTextVariant;
  muted?: boolean;
};

export function AppText({
  style,
  variant = "body",
  muted,
  ...props
}: AppTextProps) {
  const { theme } = useAppTheme();
  const variantStyle = getTypographyStyle(variant);

  return (
    <Text
      {...props}
      style={[
        {
          color: muted ? theme.colors.text.muted : theme.colors.text.primary,
        },
        variantStyle,
        style,
      ]}
    />
  );
}
