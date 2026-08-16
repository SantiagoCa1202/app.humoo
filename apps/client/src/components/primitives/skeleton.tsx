import { useEffect, useMemo, useRef } from "react";
import {
  Animated,
  View,
  type DimensionValue,
  type StyleProp,
  type ViewProps,
  type ViewStyle,
} from "react-native";

import { useAppTheme } from "@/theme/ThemeProvider";

type SkeletonVariant = "rect" | "circle" | "text";
type SkeletonRadius = "sm" | "md" | "lg" | "xl" | "2xl" | "full" | number;

export type SkeletonProps = Omit<ViewProps, "style"> & {
  width?: DimensionValue;
  height?: DimensionValue;
  radius?: SkeletonRadius;
  variant?: SkeletonVariant;
  animated?: boolean;
  style?: StyleProp<ViewStyle>;
};

export function Skeleton({
  width,
  height,
  radius,
  variant = "rect",
  animated = true,
  style,
  ...props
}: SkeletonProps) {
  const { theme } = useAppTheme();
  const shimmer = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (!animated) {
      shimmer.stopAnimation();
      shimmer.setValue(0);
      return;
    }

    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(shimmer, {
          duration: theme.motion.skeletonCycle / 2,
          toValue: 1,
          useNativeDriver: true,
        }),
        Animated.timing(shimmer, {
          duration: theme.motion.skeletonCycle / 2,
          toValue: 0,
          useNativeDriver: true,
        }),
      ])
    );

    animation.start();

    return () => {
      animation.stop();
    };
  }, [animated, shimmer, theme.motion.skeletonCycle]);

  const resolvedHeight =
    height ??
    (variant === "circle"
      ? theme.spacing[10]
      : variant === "text"
      ? theme.typography.styles.body.lineHeight
      : theme.spacing[6]);
  const resolvedWidth =
    width ??
    (variant === "circle" ? resolvedHeight : ("100%" as DimensionValue));
  const resolvedRadius =
    typeof radius === "number"
      ? radius
      : radius
      ? theme.radius[radius]
      : variant === "circle"
      ? theme.radius.full
      : variant === "text"
      ? theme.radius.sm
      : theme.radius.md;
  const overlayOpacity = useMemo(
    () =>
      shimmer.interpolate({
        inputRange: [0, 1],
        outputRange: [0.25, 0.9],
      }),
    [shimmer]
  );

  return (
    <View
      {...props}
      style={[
        {
          backgroundColor: theme.colors.skeleton.base,
          borderRadius: resolvedRadius,
          height: resolvedHeight,
          overflow: "hidden",
          width: resolvedWidth,
        },
        style,
      ]}
    >
      {animated ? (
        <Animated.View
          pointerEvents="none"
          style={{
            backgroundColor: theme.colors.skeleton.highlight,
            bottom: 0,
            left: 0,
            opacity: overlayOpacity,
            position: "absolute",
            right: 0,
            top: 0,
          }}
        />
      ) : null}
    </View>
  );
}
