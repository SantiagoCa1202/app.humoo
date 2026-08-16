import { View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type ComponentBlockSpacing = "sm" | "md" | "lg";

export type ComponentBlockProps = {
  accessibilityLabel?: string;
  children: React.ReactNode;
  label?: React.ReactNode;
  spacing?: ComponentBlockSpacing;
};

function getGap(theme: ReturnType<typeof useAppTheme>["theme"], spacing: ComponentBlockSpacing) {
  if (spacing === "sm") {
    return theme.spacing[2];
  }

  if (spacing === "lg") {
    return theme.spacing[4];
  }

  return theme.spacing[3];
}

export function ComponentBlock({
  accessibilityLabel,
  children,
  label,
  spacing = "md",
}: ComponentBlockProps) {
  const { theme } = useAppTheme();
  const gap = getGap(theme, spacing);

  return (
    <View
      accessibilityLabel={accessibilityLabel}
      style={{
        gap,
        width: "100%",
      }}
    >
      {label ? (
        typeof label === "string" || typeof label === "number" ? (
          <Text tone="secondary" variant="overline">
            {label}
          </Text>
        ) : (
          label
        )
      ) : null}
      <View style={{ gap }}>
        {children}
      </View>
    </View>
  );
}
