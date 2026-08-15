import {
  humooLayout,
  humooPalette,
  humooRadius,
  humooTypography,
  withAlpha,
} from "@/theme/brand";

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  xxxl: 40,
};

const baseTheme = {
  layout: humooLayout,
  radius: humooRadius,
  typography: humooTypography,
};

type ThemeColors = {
  background: string;
  backgroundAccent: string;
  surface: string;
  surfaceMuted: string;
  surfaceStrong: string;
  border: string;
  borderStrong: string;
  text: string;
  textMuted: string;
  textInverse: string;
  primary: string;
  primaryContrast: string;
  accent: string;
  accentSoft: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
  shadow: string;
};

export type AppTheme = {
  isDark: boolean;
  colors: ThemeColors;
  layout: typeof humooLayout;
  radius: typeof humooRadius;
  typography: typeof humooTypography;
};

export const lightTheme: AppTheme = {
  ...baseTheme,
  isDark: false,
  colors: {
    background: humooPalette.warmCream,
    backgroundAccent: withAlpha(humooPalette.softSand, "88"),
    surface: humooPalette.warmCream,
    surfaceMuted: humooPalette.softSand,
    surfaceStrong: humooPalette.deepForest,
    border: withAlpha(humooPalette.softSand, "CC"),
    borderStrong: withAlpha(humooPalette.deepForest, "26"),
    text: humooPalette.deepForest,
    textMuted: withAlpha(humooPalette.charcoal, "B3"),
    textInverse: humooPalette.warmCream,
    primary: humooPalette.emberOrange,
    primaryContrast: humooPalette.warmCream,
    accent: humooPalette.deepForest,
    accentSoft: humooPalette.sageGreen,
    success: humooPalette.deepForest,
    warning: humooPalette.emberOrange,
    danger: humooPalette.charcoal,
    info: humooPalette.sageGreen,
    shadow: withAlpha(humooPalette.charcoal, "14"),
  },
};

export const darkTheme: AppTheme = {
  ...baseTheme,
  isDark: true,
  colors: {
    background: humooPalette.deepForest,
    backgroundAccent: withAlpha(humooPalette.charcoal, "CC"),
    surface: withAlpha(humooPalette.charcoal, "F2"),
    surfaceMuted: withAlpha(humooPalette.deepForest, "CC"),
    surfaceStrong: humooPalette.emberOrange,
    border: withAlpha(humooPalette.sageGreen, "3D"),
    borderStrong: withAlpha(humooPalette.warmCream, "26"),
    text: humooPalette.warmCream,
    textMuted: withAlpha(humooPalette.softSand, "C9"),
    textInverse: humooPalette.deepForest,
    primary: humooPalette.emberOrange,
    primaryContrast: humooPalette.warmCream,
    accent: humooPalette.sageGreen,
    accentSoft: humooPalette.softSand,
    success: humooPalette.sageGreen,
    warning: humooPalette.emberOrange,
    danger: humooPalette.softSand,
    info: humooPalette.softSand,
    shadow: withAlpha(humooPalette.charcoal, "38"),
  },
};
export type ThemePreference = "system" | "light" | "dark";
