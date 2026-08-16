import { useEffect, useRef, useState, type ReactNode } from "react";
import { Platform, Pressable, View, type ViewStyle } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type TooltipPlacement = "top" | "bottom" | "left" | "right";

export type TooltipProps = {
  children: ReactNode;
  content: ReactNode;
  delay?: number;
  disabled?: boolean;
  placement?: TooltipPlacement;
  width?: number;
};

export function Tooltip({
  children,
  content,
  delay = 150,
  disabled = false,
  placement = "top",
  width,
}: TooltipProps) {
  const { theme } = useAppTheme();
  const [visible, setVisible] = useState(false);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearTooltipTimeout = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  };

  useEffect(() => clearTooltipTimeout, []);

  const show = () => {
    if (disabled) {
      return;
    }

    clearTooltipTimeout();
    timeoutRef.current = setTimeout(() => {
      setVisible(true);
    }, delay);
  };

  const hide = () => {
    clearTooltipTimeout();
    setVisible(false);
  };

  const tooltipWidth = width ?? theme.spacing[16];
  const placementStyle: ViewStyle =
    placement === "bottom"
      ? { marginTop: theme.spacing[2], top: "100%" }
      : placement === "left"
      ? {
          right: 0,
          top: 0,
          transform: [{ translateX: -(tooltipWidth + theme.spacing[2]) }],
        }
      : placement === "right"
      ? {
          left: 0,
          top: 0,
          transform: [{ translateX: theme.spacing[2] }],
        }
      : { bottom: "100%", marginBottom: theme.spacing[2] };

  return (
    <View
      style={{
        alignSelf: "flex-start",
        position: "relative",
      }}
    >
      <Pressable
        accessibilityRole="button"
        disabled={disabled}
        delayLongPress={delay}
        onBlur={hide}
        onFocus={show}
        onHoverIn={Platform.OS === "web" ? show : undefined}
        onHoverOut={Platform.OS === "web" ? hide : undefined}
        onLongPress={Platform.OS === "web" ? undefined : show}
        onPressIn={Platform.OS === "web" ? undefined : show}
        onPressOut={Platform.OS === "web" ? undefined : hide}
      >
        {children}
      </Pressable>
      {visible ? (
        <View
          pointerEvents="none"
          style={[
            {
              backgroundColor: theme.colors.tooltip.background,
              borderCurve: "continuous",
              borderRadius: theme.radius.md,
              maxWidth: tooltipWidth,
              paddingHorizontal: theme.spacing[3],
              paddingVertical: theme.spacing[2],
              position: "absolute",
              zIndex: 10,
            },
            placementStyle,
          ]}
        >
          {typeof content === "string" ? (
            <Text
              tone="inverse"
              variant="caption"
              style={{ color: theme.colors.tooltip.text }}
            >
              {content}
            </Text>
          ) : (
            content
          )}
        </View>
      ) : null}
    </View>
  );
}
