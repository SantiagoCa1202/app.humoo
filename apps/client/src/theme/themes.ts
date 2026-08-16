import {
  darkColors,
  lightColors,
  type ThemeColors,
} from "@/theme/colors";
import { humooBreakpoints } from "@/theme/breakpoints";
import { humooIconSizes } from "@/theme/icon";
import { humooLayout } from "@/theme/layout";
import { humooMotion } from "@/theme/motion";
import { humooRadius } from "@/theme/radius";
import { createShadows, type ShadowScaleName } from "@/theme/shadows";
import { spacing, type SpacingScale } from "@/theme/spacing";
import { humooTypography } from "@/theme/typography";

export type ThemePreference = "system" | "light" | "dark";

type ComponentVisualState = {
  background: string;
  border: string;
  text: string;
};

type ThemeComponents = {
  button: {
    primary: ComponentVisualState & {
      hoverBackground: string;
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    secondary: ComponentVisualState & {
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    outline: ComponentVisualState & {
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    ghost: ComponentVisualState & {
      hoverBackground: string;
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    destructiveSoft: ComponentVisualState & {
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    destructiveSolid: ComponentVisualState & {
      pressedBackground: string;
      shadow: ShadowScaleName;
    };
    disabled: ComponentVisualState & {
      shadow: ShadowScaleName;
    };
  };
  input: {
    background: string;
    text: string;
    placeholder: string;
    border: string;
    focusBorder: string;
    errorBorder: string;
    errorText: string;
    disabledBackground: string;
    disabledText: string;
    selection: string;
  };
  card: {
    default: {
      background: string;
      border: string;
      shadow: ShadowScaleName;
    };
    outlined: {
      background: string;
      border: string;
      shadow: ShadowScaleName;
    };
    muted: {
      background: string;
      border: string;
      shadow: ShadowScaleName;
    };
    elevated: {
      background: string;
      border: string;
      shadow: ShadowScaleName;
    };
    selected: {
      background: string;
      border: string;
      shadow: ShadowScaleName;
    };
  };
  chip: {
    default: ComponentVisualState & {
      hoverBackground: string;
      pressedBackground: string;
    };
    selected: ComponentVisualState & {
      hoverBackground: string;
      pressedBackground: string;
    };
    disabled: ComponentVisualState;
  };
  navigation: {
    sidebar: ComponentVisualState;
    sidebarItem: ComponentVisualState & {
      activeBackground: string;
      activeBorder: string;
      activeText: string;
    };
  };
  heroPanel: ComponentVisualState;
};

export type AppTheme = {
  isDark: boolean;
  colors: ThemeColors;
  components: ThemeComponents;
  spacing: SpacingScale;
  radius: typeof humooRadius;
  typography: typeof humooTypography;
  breakpoints: typeof humooBreakpoints;
  motion: typeof humooMotion;
  iconSizes: typeof humooIconSizes;
  layout: typeof humooLayout;
  shadows: ReturnType<typeof createShadows>;
};

const baseTheme = {
  spacing,
  radius: humooRadius,
  typography: humooTypography,
  breakpoints: humooBreakpoints,
  motion: humooMotion,
  iconSizes: humooIconSizes,
  layout: humooLayout,
};

function createComponentTokens(colors: ThemeColors): ThemeComponents {
  return {
    button: {
      primary: {
        background: colors.brand.primary,
        border: colors.brand.primary,
        text: colors.text.inverse,
        hoverBackground: colors.brand.hover,
        pressedBackground: colors.brand.pressed,
        shadow: "sm",
      },
      secondary: {
        background: colors.background.muted,
        border: colors.border.subtle,
        text: colors.text.primary,
        pressedBackground: colors.background.pressed,
        shadow: "none",
      },
      outline: {
        background: "transparent",
        border: colors.border.default,
        text: colors.text.primary,
        pressedBackground: colors.background.muted,
        shadow: "none",
      },
      ghost: {
        background: "transparent",
        border: "transparent",
        text: colors.text.secondary,
        hoverBackground: colors.background.muted,
        pressedBackground: colors.background.pressed,
        shadow: "none",
      },
      destructiveSoft: {
        background: colors.status.dangerSoft,
        border: colors.status.dangerSoft,
        text: colors.status.danger,
        pressedBackground: colors.status.dangerForeground,
        shadow: "none",
      },
      destructiveSolid: {
        background: colors.status.danger,
        border: colors.status.danger,
        text: colors.text.inverse,
        pressedBackground: colors.status.dangerForeground,
        shadow: "sm",
      },
      disabled: {
        background: colors.interaction.disabledBackground,
        border: colors.interaction.disabledBackground,
        text: colors.interaction.disabledText,
        shadow: "none",
      },
    },
    input: {
      background: colors.background.surface,
      text: colors.text.primary,
      placeholder: colors.text.muted,
      border: colors.border.default,
      focusBorder: colors.border.focus,
      errorBorder: colors.border.error,
      errorText: colors.status.danger,
      disabledBackground: colors.interaction.disabledBackground,
      disabledText: colors.interaction.disabledText,
      selection: colors.brand.primary,
    },
    card: {
      default: {
        background: colors.background.surface,
        border: colors.border.default,
        shadow: "none",
      },
      outlined: {
        background: "transparent",
        border: colors.border.default,
        shadow: "none",
      },
      muted: {
        background: colors.background.muted,
        border: colors.border.subtle,
        shadow: "none",
      },
      elevated: {
        background: colors.background.surface,
        border: colors.border.subtle,
        shadow: "sm",
      },
      selected: {
        background: colors.background.surface,
        border: colors.brand.primary,
        shadow: "none",
      },
    },
    chip: {
      default: {
        background: colors.background.surface,
        border: colors.border.default,
        text: colors.text.secondary,
        hoverBackground: colors.background.muted,
        pressedBackground: colors.background.pressed,
      },
      selected: {
        background: colors.brand.soft,
        border: colors.brand.primary,
        text: colors.brand.primary,
        hoverBackground: colors.brand.soft,
        pressedBackground: colors.background.pressed,
      },
      disabled: {
        background: colors.interaction.disabledBackground,
        border: colors.interaction.disabledBackground,
        text: colors.interaction.disabledText,
      },
    },
    navigation: {
      sidebar: {
        background: colors.brand.primary,
        border: colors.brand.primary,
        text: colors.text.inverse,
      },
      sidebarItem: {
        background: "transparent",
        border: "transparent",
        text: colors.text.inverse,
        activeBackground: colors.brand.pressed,
        activeBorder: colors.brand.pressed,
        activeText: colors.text.inverse,
      },
    },
    heroPanel: {
      background: colors.brand.primary,
      border: colors.brand.pressed,
      text: colors.text.inverse,
    },
  };
}

export const lightTheme: AppTheme = {
  ...baseTheme,
  isDark: false,
  colors: lightColors,
  shadows: createShadows(lightColors.shadow),
  components: createComponentTokens(lightColors),
};

export const darkTheme: AppTheme = {
  ...baseTheme,
  isDark: true,
  colors: darkColors,
  shadows: createShadows(darkColors.shadow),
  components: createComponentTokens(darkColors),
};

export function resolveTheme(
  preference: ThemePreference,
  systemScheme?: "light" | "dark" | null
) {
  const resolvedPreference =
    preference === "system" ? systemScheme ?? "light" : preference;

  return resolvedPreference === "dark" ? darkTheme : lightTheme;
}
