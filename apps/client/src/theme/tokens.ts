import { humooLayout, humooRadius, humooTypography } from "@/theme/brand";

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  xxxl: 40,
};

export type ThemePreference = "system" | "light" | "dark";

type ThemeBackgroundColors = {
  app: string;
  surface: string;
  muted: string;
  subtle: string;
  pressed: string;
};

type ThemeTextColors = {
  primary: string;
  secondary: string;
  muted: string;
  inverse: string;
  disabled: string;
};

type ThemeBorderColors = {
  subtle: string;
  default: string;
  strong: string;
  focus: string;
  error: string;
};

type ThemeBrandColors = {
  primary: string;
  hover: string;
  pressed: string;
  soft: string;
  foreground: string;
};

type ThemeStatusColors = {
  success: string;
  successSoft: string;
  successForeground: string;
  warning: string;
  warningSoft: string;
  warningForeground: string;
  danger: string;
  dangerSoft: string;
  dangerForeground: string;
  info: string;
  infoSoft: string;
  infoForeground: string;
  special: string;
  specialSoft: string;
  specialForeground: string;
};

type ThemeInteractionColors = {
  hover: string;
  pressed: string;
  selected: string;
  selectedSoft: string;
  focus: string;
  disabledBackground: string;
  disabledText: string;
};

type ThemeChatColors = {
  userBackground: string;
  userText: string;
  assistantText: string;
};

type ThemeSkeletonColors = {
  base: string;
  highlight: string;
};

type ThemeTooltipColors = {
  background: string;
  text: string;
};

export type ThemeColors = {
  background: ThemeBackgroundColors;
  text: ThemeTextColors;
  border: ThemeBorderColors;
  brand: ThemeBrandColors;
  status: ThemeStatusColors;
  interaction: ThemeInteractionColors;
  chat: ThemeChatColors;
  skeleton: ThemeSkeletonColors;
  tooltip: ThemeTooltipColors;
  overlay: string;
  shadow: string;
};

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
      shadowColor: string;
    };
    secondary: ComponentVisualState & {
      pressedBackground: string;
    };
    outline: ComponentVisualState & {
      pressedBackground: string;
    };
    ghost: ComponentVisualState & {
      hoverBackground: string;
      pressedBackground: string;
    };
    destructiveSoft: ComponentVisualState & {
      pressedBackground: string;
    };
    destructiveSolid: ComponentVisualState & {
      pressedBackground: string;
    };
    disabled: ComponentVisualState;
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
      shadowColor: string;
    };
    outlined: {
      background: string;
      border: string;
      shadowColor: string;
    };
    muted: {
      background: string;
      border: string;
      shadowColor: string;
    };
    elevated: {
      background: string;
      border: string;
      shadowColor: string;
    };
    selected: {
      background: string;
      border: string;
      shadowColor: string;
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
  layout: typeof humooLayout;
  radius: typeof humooRadius;
  typography: typeof humooTypography;
};

const baseTheme = {
  layout: humooLayout,
  radius: humooRadius,
  typography: humooTypography,
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
        shadowColor: colors.shadow,
      },
      secondary: {
        background: colors.background.muted,
        border: colors.border.subtle,
        text: colors.text.primary,
        pressedBackground: colors.background.pressed,
      },
      outline: {
        background: "transparent",
        border: colors.border.default,
        text: colors.text.primary,
        pressedBackground: colors.background.muted,
      },
      ghost: {
        background: "transparent",
        border: "transparent",
        text: colors.text.secondary,
        hoverBackground: colors.background.muted,
        pressedBackground: colors.background.pressed,
      },
      destructiveSoft: {
        background: colors.status.dangerSoft,
        border: colors.status.dangerSoft,
        text: colors.status.danger,
        pressedBackground: colors.status.dangerForeground,
      },
      destructiveSolid: {
        background: colors.status.danger,
        border: colors.status.danger,
        text: colors.text.inverse,
        pressedBackground: colors.status.dangerForeground,
      },
      disabled: {
        background: colors.interaction.disabledBackground,
        border: colors.interaction.disabledBackground,
        text: colors.interaction.disabledText,
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
        shadowColor: "transparent",
      },
      outlined: {
        background: "transparent",
        border: colors.border.default,
        shadowColor: "transparent",
      },
      muted: {
        background: colors.background.muted,
        border: colors.border.subtle,
        shadowColor: "transparent",
      },
      elevated: {
        background: colors.background.surface,
        border: colors.border.subtle,
        shadowColor: colors.shadow,
      },
      selected: {
        background: colors.background.surface,
        border: colors.brand.primary,
        shadowColor: "transparent",
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

const lightColors: ThemeColors = {
  background: {
    app: "#FAF7F2",
    surface: "#FFFFFF",
    muted: "#F4F0EB",
    subtle: "#F8F4EF",
    pressed: "#F3EEE8",
  },
  text: {
    primary: "#282623",
    secondary: "#5D5954",
    muted: "#8C867F",
    inverse: "#FFFFFF",
    disabled: "#AAA39B",
  },
  border: {
    subtle: "#EEEAE5",
    default: "#DDD7CF",
    strong: "#C5BDB4",
    focus: "#E86F3D",
    error: "#D84B4B",
  },
  brand: {
    primary: "#E86F3D",
    hover: "#DA6535",
    pressed: "#CF5D31",
    soft: "#FCE8DE",
    foreground: "#B94E27",
  },
  status: {
    success: "#43865D",
    successSoft: "#E7F3EA",
    successForeground: "#327047",
    warning: "#C88628",
    warningSoft: "#FAEFD9",
    warningForeground: "#96621A",
    danger: "#D84B4B",
    dangerSoft: "#FBE5E5",
    dangerForeground: "#B83D3D",
    info: "#4C78A8",
    infoSoft: "#E7EFF8",
    infoForeground: "#3A628E",
    special: "#8062A6",
    specialSoft: "#EEE8F6",
    specialForeground: "#74569A",
  },
  interaction: {
    hover: "#F4F0EB",
    pressed: "#F3EEE8",
    selected: "#E86F3D",
    selectedSoft: "#FCE8DE",
    focus: "#E86F3D",
    disabledBackground: "#EEEAE4",
    disabledText: "#AAA39B",
  },
  chat: {
    userBackground: "#282623",
    userText: "#FFFFFF",
    assistantText: "#282623",
  },
  skeleton: {
    base: "#E9E4DE",
    highlight: "#F4F0EB",
  },
  tooltip: {
    background: "#282623",
    text: "#FFFFFF",
  },
  overlay: "rgba(23, 22, 21, 0.48)",
  shadow: "rgba(40, 38, 35, 0.08)",
};

const darkColors: ThemeColors = {
  background: {
    app: "#171615",
    surface: "#222120",
    muted: "#2A2826",
    subtle: "#262422",
    pressed: "#2D2B29",
  },
  text: {
    primary: "#F7F3EE",
    secondary: "#BEB8B1",
    muted: "#8E8881",
    inverse: "#282623",
    disabled: "#66615C",
  },
  border: {
    subtle: "#302E2C",
    default: "#3B3936",
    strong: "#57534F",
    focus: "#F0804E",
    error: "#E06A6A",
  },
  brand: {
    primary: "#F0804E",
    hover: "#F58B5D",
    pressed: "#D96A3D",
    soft: "#3C281F",
    foreground: "#FF9A6C",
  },
  status: {
    success: "#64A878",
    successSoft: "#213429",
    successForeground: "#83C594",
    warning: "#DAA448",
    warningSoft: "#3A2E1E",
    warningForeground: "#E5B961",
    danger: "#E06A6A",
    dangerSoft: "#3A2424",
    dangerForeground: "#F08A8A",
    info: "#72A0D0",
    infoSoft: "#202D3B",
    infoForeground: "#8CB7E4",
    special: "#9E7BC4",
    specialSoft: "#30273C",
    specialForeground: "#C0A1E4",
  },
  interaction: {
    hover: "#2A2826",
    pressed: "#2D2B29",
    selected: "#F0804E",
    selectedSoft: "#3C281F",
    focus: "#F0804E",
    disabledBackground: "#292725",
    disabledText: "#66615C",
  },
  chat: {
    userBackground: "#EEE9E2",
    userText: "#282623",
    assistantText: "#F7F3EE",
  },
  skeleton: {
    base: "#302E2C",
    highlight: "#3D3A37",
  },
  tooltip: {
    background: "#F1EDE8",
    text: "#282623",
  },
  overlay: "rgba(0, 0, 0, 0.64)",
  shadow: "rgba(0, 0, 0, 0.35)",
};

export const lightTheme: AppTheme = {
  ...baseTheme,
  isDark: false,
  colors: lightColors,
  components: createComponentTokens(lightColors),
};

export const darkTheme: AppTheme = {
  ...baseTheme,
  isDark: true,
  colors: darkColors,
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
