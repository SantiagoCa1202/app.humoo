import { View } from "react-native";

import { Divider } from "@/components/primitives/divider";
import { useAppTheme } from "@/theme/ThemeProvider";

type CardSectionPadding = "none" | "sm" | "md" | "lg" | "xl";

export type CardContentProps = {
  bottomDivider?: boolean;
  children?: React.ReactNode;
  padding?: CardSectionPadding;
  topDivider?: boolean;
};

function getPaddingValue(
  theme: ReturnType<typeof useAppTheme>["theme"],
  padding: CardSectionPadding
) {
  if (padding === "none") {
    return theme.spacing[0];
  }

  if (padding === "sm") {
    return theme.spacing[3];
  }

  if (padding === "md") {
    return theme.spacing[4];
  }

  if (padding === "xl") {
    return theme.spacing[8];
  }

  return theme.spacing[6];
}

export function CardContent({
  bottomDivider = false,
  children,
  padding = "none",
  topDivider = false,
}: CardContentProps) {
  const { theme } = useAppTheme();
  const paddingValue = getPaddingValue(theme, padding);

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {topDivider ? <Divider spacing="none" /> : null}
      <View style={{ padding: paddingValue }}>{children}</View>
      {bottomDivider ? <Divider spacing="none" /> : null}
    </View>
  );
}
