import type { TextStyle } from "react-native";

const typographyFamily = {
  displaySemiBold: "Manrope_600SemiBold",
  displayBold: "Manrope_700Bold",
  interfaceRegular: "PlusJakartaSans_400Regular",
  interfaceMedium: "PlusJakartaSans_500Medium",
  interfaceSemiBold: "PlusJakartaSans_600SemiBold",
} as const;

export const typographyStyles = {
  display: {
    fontFamily: typographyFamily.displayBold,
    fontSize: 38,
    lineHeight: 44,
    fontWeight: "800",
  },
  h1: {
    fontFamily: typographyFamily.displayBold,
    fontSize: 32,
    lineHeight: 38,
    fontWeight: "800",
  },
  h2: {
    fontFamily: typographyFamily.displayBold,
    fontSize: 26,
    lineHeight: 32,
    fontWeight: "700",
  },
  h3: {
    fontFamily: typographyFamily.displayBold,
    fontSize: 20,
    lineHeight: 26,
    fontWeight: "700",
  },
  h4: {
    fontFamily: typographyFamily.interfaceSemiBold,
    fontSize: 17,
    lineHeight: 23,
    fontWeight: "700",
  },
  body: {
    fontFamily: typographyFamily.interfaceRegular,
    fontSize: 15,
    lineHeight: 23,
    fontWeight: "400",
  },
  bodyMedium: {
    fontFamily: typographyFamily.interfaceSemiBold,
    fontSize: 15,
    lineHeight: 22,
    fontWeight: "600",
  },
  bodySmall: {
    fontFamily: typographyFamily.interfaceRegular,
    fontSize: 13,
    lineHeight: 19,
    fontWeight: "400",
  },
  label: {
    fontFamily: typographyFamily.interfaceSemiBold,
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "700",
  },
  caption: {
    fontFamily: typographyFamily.interfaceRegular,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: "400",
  },
  overline: {
    fontFamily: typographyFamily.displayBold,
    fontSize: 11,
    lineHeight: 15,
    fontWeight: "800",
    letterSpacing: 1.4,
    textTransform: "uppercase" as const,
  },
} satisfies Record<string, TextStyle>;

export const typographyAliases = {
  bodyLarge: "bodyMedium",
  subtitle: "h4",
  title: "h2",
  hero: "display",
  metric: "display",
} as const;

export const humooTypography = {
  family: typographyFamily,
  styles: typographyStyles,
  aliases: typographyAliases,
} as const;

export type TypographyStyleName = keyof typeof typographyStyles;
export type TypographyAliasName = keyof typeof typographyAliases;
export type AppTextVariant = TypographyStyleName | TypographyAliasName;

export function getTypographyStyle(variant: AppTextVariant) {
  const resolved =
    variant in typographyAliases
      ? typographyAliases[variant as TypographyAliasName]
      : variant;

  return typographyStyles[resolved as TypographyStyleName];
}
