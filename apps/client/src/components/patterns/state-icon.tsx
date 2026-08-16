import { View } from "react-native";

import { IconSlot } from "@/components/primitives/icon-slot";
import { Text } from "@/components/primitives/text";
import type { AppStateTone } from "@/theme/status-config";
import { getAppStateAppearance } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type StateIconProps = {
  compact?: boolean;
  icon?: React.ReactNode;
  tone: AppStateTone;
};

const DEFAULT_GLYPHS: Record<AppStateTone, string> = {
  loading: "i",
  empty: "-",
  error: "!",
  forbidden: "!",
  offline: "~",
  success: "OK",
  conflict: "!=",
  info: "i",
};

export function StateIcon({ compact = false, icon, tone }: StateIconProps) {
  const { theme } = useAppTheme();
  const appearance = getAppStateAppearance(theme, tone);
  const size = compact ? theme.iconSizes.xl : theme.iconSizes.xl + theme.spacing[3];
  const glyphVariant = compact ? "label" : "title";

  return (
    <View
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      style={{
        alignItems: "center",
        backgroundColor: theme.colors.background.surface,
        borderColor: appearance.border,
        borderCurve: "continuous",
        borderRadius: theme.radius.full,
        borderWidth: 1,
        height: size,
        justifyContent: "center",
        width: size,
      }}
    >
      {icon ? (
        <IconSlot color={appearance.accent} icon={icon} size={compact ? theme.iconSizes.md : theme.iconSizes.lg} />
      ) : (
        <Text
          tone="default"
          variant={glyphVariant}
          style={{
            color: appearance.accent,
          }}
        >
          {DEFAULT_GLYPHS[tone]}
        </Text>
      )}
    </View>
  );
}
