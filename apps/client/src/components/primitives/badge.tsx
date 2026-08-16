import React from "react";
import { View } from "react-native";

import { IconSlot } from "@/components/primitives/icon-slot";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  getSemanticToneAppearance,
  type SemanticStatusTone,
} from "@/theme/status-config";

type BadgeSize = "sm" | "md" | "lg";
type BadgeShape = "pill" | "rounded";

export type BadgeProps = {
  children?: React.ReactNode;
  dot?: boolean;
  icon?: React.ReactNode;
  label?: string;
  outline?: boolean;
  shape?: BadgeShape;
  size?: BadgeSize;
  variant?: SemanticStatusTone;
};

function getBadgeMetrics(theme: ReturnType<typeof useAppTheme>["theme"], size: BadgeSize) {
  if (size === "sm") {
    return {
      dot: theme.spacing[2],
      gap: theme.spacing[1],
      icon: theme.iconSizes.xs,
      paddingHorizontal: theme.spacing[2],
      paddingVertical: theme.spacing[1],
      textVariant: "caption" as const,
    };
  }

  if (size === "lg") {
    return {
      dot: theme.spacing[2],
      gap: theme.spacing[2],
      icon: theme.iconSizes.md,
      paddingHorizontal: theme.spacing[4],
      paddingVertical: theme.spacing[2],
      textVariant: "bodySmall" as const,
    };
  }

  return {
    dot: theme.spacing[2],
    gap: theme.spacing[2],
    icon: theme.iconSizes.sm,
    paddingHorizontal: theme.spacing[3],
    paddingVertical: theme.spacing[1],
    textVariant: "caption" as const,
  };
}

export function Badge({
  children,
  dot = false,
  icon,
  label,
  outline = false,
  shape = "pill",
  size = "md",
  variant = "neutral",
}: BadgeProps) {
  const { theme } = useAppTheme();
  const appearance = getSemanticToneAppearance(theme, variant);
  const metrics = getBadgeMetrics(theme, size);
  const content = children ?? label;

  return (
    <View
      style={{
        alignItems: "center",
        alignSelf: "flex-start",
        backgroundColor: outline ? "transparent" : appearance.background,
        borderCurve: "continuous",
        borderColor: appearance.border,
        borderRadius: shape === "pill" ? theme.radius.full : theme.radius.md,
        borderWidth: 1,
        flexDirection: "row",
        gap: metrics.gap,
        paddingHorizontal: metrics.paddingHorizontal,
        paddingVertical: metrics.paddingVertical,
      }}
    >
      {dot ? (
        <View
          style={{
            backgroundColor: appearance.accent,
            borderRadius: theme.radius.full,
            height: metrics.dot,
            width: metrics.dot,
          }}
        />
      ) : null}
      <IconSlot color={appearance.accent} icon={icon} size={metrics.icon} />
      {typeof content === "string" || typeof content === "number" ? (
        <Text
          tone="default"
          variant={metrics.textVariant}
          style={{ color: appearance.accent }}
        >
          {content}
        </Text>
      ) : (
        content
      )}
    </View>
  );
}
