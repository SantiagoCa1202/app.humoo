import type { ViewStyle } from "react-native";

export type ShadowScaleName = "none" | "sm" | "md" | "lg";
export type ThemeShadows = Record<ShadowScaleName, ViewStyle>;

export function createShadows(color: string): ThemeShadows {
  return {
    none: {
      shadowColor: "transparent",
      shadowOffset: { width: 0, height: 0 },
      shadowOpacity: 0,
      shadowRadius: 0,
    },
    sm: {
      shadowColor: color,
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 1,
      shadowRadius: 14,
    },
    md: {
      shadowColor: color,
      shadowOffset: { width: 0, height: 10 },
      shadowOpacity: 1,
      shadowRadius: 22,
    },
    lg: {
      shadowColor: color,
      shadowOffset: { width: 0, height: 16 },
      shadowOpacity: 1,
      shadowRadius: 30,
    },
  };
}
