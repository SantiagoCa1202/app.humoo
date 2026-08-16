import React from "react";
import { Pressable, View, type PressableProps } from "react-native";
import { useTranslation } from "react-i18next";

import { IconSlot } from "@/components/primitives/icon-slot";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  getSemanticToneAppearance,
  type SemanticStatusTone,
} from "@/theme/status-config";

type ChipSize = "sm" | "md" | "lg";

export type ChipProps = Omit<PressableProps, "style"> & {
  disabled?: boolean;
  icon?: React.ReactNode;
  label: string;
  onRemove?: () => void;
  removable?: boolean;
  selected?: boolean;
  size?: ChipSize;
  variant?: Exclude<SemanticStatusTone, "special">;
};

function getChipMetrics(theme: ReturnType<typeof useAppTheme>["theme"], size: ChipSize) {
  if (size === "sm") {
    return {
      gap: theme.spacing[1],
      icon: theme.iconSizes.xs,
      paddingHorizontal: theme.spacing[2],
      paddingVertical: theme.spacing[1],
      textVariant: "caption" as const,
    };
  }

  if (size === "lg") {
    return {
      gap: theme.spacing[2],
      icon: theme.iconSizes.md,
      paddingHorizontal: theme.spacing[4],
      paddingVertical: theme.spacing[2],
      textVariant: "bodySmall" as const,
    };
  }

  return {
    gap: theme.spacing[2],
    icon: theme.iconSizes.sm,
    paddingHorizontal: theme.spacing[3],
    paddingVertical: theme.spacing[2],
    textVariant: "label" as const,
  };
}

export function Chip({
  disabled,
  icon,
  label,
  onPress,
  onRemove,
  removable = false,
  selected = false,
  size = "md",
  variant = "neutral",
  ...props
}: ChipProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const metrics = getChipMetrics(theme, size);
  const appearance = selected
    ? getSemanticToneAppearance(theme, "primary")
    : getSemanticToneAppearance(theme, variant);
  const textColor = disabled
    ? theme.components.chip.disabled.text
    : selected
    ? theme.colors.brand.primary
    : appearance.accent;

  return (
    <View
      style={{
        alignSelf: "flex-start",
        flexDirection: "row",
      }}
    >
      <Pressable
        accessibilityRole={onPress ? "button" : "text"}
        disabled={disabled}
        onPress={onPress}
        style={({ hovered, pressed }) => ({
          alignItems: "center",
          backgroundColor: disabled
            ? theme.components.chip.disabled.background
            : selected
            ? theme.colors.brand.soft
            : pressed
            ? theme.colors.interaction.pressed
            : hovered
            ? appearance.background
            : appearance.background,
          borderColor: disabled
            ? theme.components.chip.disabled.border
            : selected
            ? theme.colors.brand.primary
            : appearance.border,
          borderCurve: "continuous",
          borderRadius: theme.radius.full,
          borderWidth: 1,
          flexDirection: "row",
          gap: metrics.gap,
          opacity: disabled ? 0.7 : 1,
          paddingHorizontal: metrics.paddingHorizontal,
          paddingVertical: metrics.paddingVertical,
        })}
        {...props}
      >
        <IconSlot color={textColor} icon={icon} size={metrics.icon} />
        <Text
          variant={metrics.textVariant}
          style={{
            color: textColor,
          }}
        >
          {label}
        </Text>
      </Pressable>
      {removable ? (
        <Pressable
          accessibilityLabel={t("removeLabel", { label })}
          accessibilityRole="button"
          disabled={disabled}
          onPress={onRemove}
          style={{
            justifyContent: "center",
            marginLeft: theme.spacing[1],
            paddingHorizontal: theme.spacing[1],
          }}
        >
          <Text
            variant={metrics.textVariant}
            style={{
              color: textColor,
            }}
          >
            x
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}
