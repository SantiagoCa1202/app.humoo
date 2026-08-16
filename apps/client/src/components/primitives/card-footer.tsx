import { View } from "react-native";

import { Divider } from "@/components/primitives/divider";
import { useAppTheme } from "@/theme/ThemeProvider";

type CardSectionPadding = "none" | "sm" | "md" | "lg" | "xl";
type CardFooterAlign = "left" | "center" | "right" | "between";

export type CardFooterProps = {
  align?: CardFooterAlign;
  children?: React.ReactNode;
  divider?: boolean;
  padding?: CardSectionPadding;
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

export function CardFooter({
  align = "left",
  children,
  divider = false,
  padding = "none",
}: CardFooterProps) {
  const { theme } = useAppTheme();
  const paddingValue = getPaddingValue(theme, padding);

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {divider ? <Divider spacing="none" /> : null}
      <View
        style={{
          alignItems: "center",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[2],
          justifyContent:
            align === "center"
              ? "center"
              : align === "right"
              ? "flex-end"
              : align === "between"
              ? "space-between"
              : "flex-start",
          padding: paddingValue,
        }}
      >
        {children}
      </View>
    </View>
  );
}
