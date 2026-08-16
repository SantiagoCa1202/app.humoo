export const humooTypography = {
  family: {
    displaySemiBold: "Manrope_600SemiBold",
    displayBold: "Manrope_700Bold",
    interfaceRegular: "PlusJakartaSans_400Regular",
    interfaceMedium: "PlusJakartaSans_500Medium",
    interfaceSemiBold: "PlusJakartaSans_600SemiBold",
  },
  size: {
    overline: 12,
    caption: 13,
    body: 16,
    bodyLarge: 18,
    title: 28,
    hero: 48,
    metric: 40,
  },
} as const;

export const humooLayout = {
  appMaxWidth: 1240,
  authMaxWidth: 1180,
  sidebarWidth: 272,
  cardPadding: 22,
  screenPadding: 24,
  controlHeight: 54,
} as const;

export const humooRadius = {
  sm: 12,
  md: 18,
  lg: 28,
  xl: 40,
  pill: 999,
} as const;

export const humooAssets = {
  logoLight: require("../../assets/branding/humoo_logo_light.png"),
  logoDark: require("../../assets/branding/humo_logo_dark.png"),
  markLight: require("../../assets/branding/humoo_icon_logo_light.png"),
} as const;
