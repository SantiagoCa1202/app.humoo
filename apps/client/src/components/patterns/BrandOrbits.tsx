import { View } from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

type BrandOrbitsProps = {
  size?: number;
  align?: "right" | "left";
  compact?: boolean;
};

export function BrandOrbits({
  size = 260,
  align = "right",
  compact = false,
}: BrandOrbitsProps) {
  const { theme } = useAppTheme();
  const sideStyle =
    align === "right"
      ? { right: compact ? -30 : -60 }
      : { left: compact ? -30 : -60 };

  return (
    <View
      pointerEvents="none"
      style={[
        {
          height: size,
          opacity: compact ? 0.75 : 1,
          overflow: "hidden",
          position: "absolute",
          top: compact ? -30 : -20,
          width: size,
        },
        sideStyle,
      ]}
    >
      <View
        style={{
          backgroundColor: theme.colors.brand.primary,
          borderBottomLeftRadius: size,
          borderBottomRightRadius: size,
          borderTopLeftRadius: size,
          borderTopRightRadius: size,
          height: size * 0.7,
          left: size * 0.08,
          position: "absolute",
          top: size * 0.04,
          width: size * 0.7,
        }}
      />
      <View
        style={{
          backgroundColor: theme.colors.brand.soft,
          borderBottomLeftRadius: size,
          borderBottomRightRadius: size,
          borderTopLeftRadius: size,
          borderTopRightRadius: size,
          height: size * 0.52,
          left: size * 0.34,
          position: "absolute",
          top: size * 0.22,
          width: size * 0.52,
        }}
      />
      <View
        style={{
          backgroundColor: theme.colors.background.app,
          borderBottomLeftRadius: size,
          borderBottomRightRadius: size,
          borderTopLeftRadius: size,
          borderTopRightRadius: size,
          height: size * 0.38,
          left: size * 0.21,
          position: "absolute",
          top: size * 0.17,
          width: size * 0.38,
        }}
      />
    </View>
  );
}
