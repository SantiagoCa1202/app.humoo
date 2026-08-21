import { Image, View } from "react-native";

import { humooAssets } from "@/theme/brand";
import { spacing } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppLogoProps = {
  kind?: "full" | "mark";
  width?: number;
  showTagline?: boolean;
};

export function AppLogo({
  kind = "full",
  width,
  showTagline = false,
}: AppLogoProps) {
  const { theme } = useAppTheme();
  const fullWidth = width ?? 178;
  const markWidth = width ?? 52;
  const source =
    kind === "mark"
      ? humooAssets.markLight
      : theme.isDark
        ? humooAssets.logoDark
        : humooAssets.logoLight;

  return (
    <View style={{ alignItems: "flex-start", gap: spacing[2] }}>
      <Image
        resizeMode="contain"
        source={source}
        style={{
          height: kind === "mark" ? markWidth : fullWidth * 0.32,
          width: kind === "mark" ? markWidth : fullWidth,
        }}
      />
      {showTagline ? (
        <View style={{ gap: spacing[1] }}>
          <Image
            resizeMode="contain"
            source={source}
            style={{ height: 1, opacity: 0, width: 1 }}
          />
        </View>
      ) : null}
    </View>
  );
}
