import { Pressable, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { AppLogo } from "@/components/patterns/AppLogo";
import { AppText } from "@/components/primitives/AppText";
import { setPreferredLanguage } from "@/i18n";
import { useAppTheme } from "@/theme/ThemeProvider";

type AuthLayoutProps = {
  title: string;
  description: string;
  children: React.ReactNode;
};

const DESKTOP_HERO_FLEX = 19;
const DESKTOP_PANEL_FLEX = 21;
const DESKTOP_FORM_MAX_WIDTH = 520;
const DESKTOP_HERO_COPY_MAX_WIDTH = 380;
const DESKTOP_HERO_LOGO_WIDTH = 340;
const MOBILE_HEADER_MAX_WIDTH = 520;
const MOBILE_LOGO_WIDTH = 220;
const HERO_DECORATION_LARGE = 520;
const HERO_DECORATION_MEDIUM = 360;
const HERO_DECORATION_SMALL = 220;

type LanguageOption = {
  code: "en" | "es";
  label: string;
};

function LanguageToggle({
  options,
  value,
  onChange,
}: {
  options: LanguageOption[];
  value: "en" | "es";
  onChange: (next: "en" | "es") => void;
}) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("auth");

  return (
    <View
      accessibilityLabel={t("changeLanguage")}
      accessibilityRole="tablist"
      style={{
        alignItems: "center",
        backgroundColor: theme.colors.background.surface,
        borderColor: theme.colors.border.subtle,
        borderRadius: theme.radius.full,
        borderWidth: 1,
        flexDirection: "row",
        padding: theme.spacing[1],
      }}
    >
      {options.map((option) => {
        const active = option.code === value;

        return (
          <Pressable
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            key={option.code}
            onPress={() => onChange(option.code)}
            style={({ pressed }) => ({
              backgroundColor: active
                ? theme.colors.brand.soft
                : pressed
                ? theme.colors.background.muted
                : "transparent",
              borderRadius: theme.radius.full,
              opacity: pressed && !active ? 0.82 : 1,
              paddingHorizontal: theme.spacing[3],
              paddingVertical: theme.spacing[2],
            })}
          >
            <AppText
              style={{
                color: active
                  ? theme.colors.brand.foreground
                  : theme.colors.text.secondary,
              }}
              variant="bodySmall"
            >
              {option.label}
            </AppText>
          </Pressable>
        );
      })}
    </View>
  );
}

export function AuthLayout({ title, description, children }: AuthLayoutProps) {
  const { theme } = useAppTheme();
  const { t, i18n } = useTranslation("auth");
  const { width } = useWindowDimensions();

  const isDesktop = width >= theme.breakpoints.lg;
  const activeLanguage = i18n.language.startsWith("es") ? "es" : "en";
  const languageOptions: LanguageOption[] = [
    { code: "es", label: t("languageSpanish") },
    { code: "en", label: t("languageEnglish") },
  ];

  const heroBackground = theme.isDark
    ? theme.colors.background.surface
    : theme.colors.background.subtle;
  const heroOutline = theme.isDark
    ? theme.colors.border.strong
    : theme.colors.border.default;
  const heroSoft = theme.isDark
    ? theme.colors.background.muted
    : theme.colors.brand.soft;
  const panelBackground = theme.colors.background.surface;
  const dividerColor = theme.colors.border.subtle;

  const handleLanguageChange = async (next: "en" | "es") => {
    if (next === activeLanguage) {
      return;
    }

    await setPreferredLanguage(next);
  };

  if (!isDesktop) {
    return (
      <View
        style={{
          backgroundColor: theme.colors.background.app,
          flex: 1,
          paddingHorizontal: theme.spacing[5],
          paddingVertical: theme.spacing[6],
        }}
      >
        <View style={{ alignItems: "flex-end", marginBottom: theme.spacing[6] }}>
          <LanguageToggle
            onChange={handleLanguageChange}
            options={languageOptions}
            value={activeLanguage}
          />
        </View>

        <View
          style={{
            alignSelf: "center",
            gap: theme.spacing[6],
            maxWidth: MOBILE_HEADER_MAX_WIDTH,
            width: "100%",
          }}
        >
          <View style={{ gap: theme.spacing[4] }}>
            <AppLogo width={MOBILE_LOGO_WIDTH} />
            <View style={{ gap: theme.spacing[2] }}>
              <AppText variant="h1">{title}</AppText>
              <AppText tone="secondary" variant="body">
                {description}
              </AppText>
            </View>
          </View>

          {children}
        </View>
      </View>
    );
  }

  return (
    <View
      style={{
        backgroundColor: theme.colors.background.app,
        flex: 1,
        flexDirection: "row",
      }}
    >
      <View
        style={{
          backgroundColor: heroBackground,
          flex: DESKTOP_HERO_FLEX,
          justifyContent: "center",
          overflow: "hidden",
          paddingHorizontal: theme.spacing[12],
          paddingVertical: theme.spacing[12],
          position: "relative",
        }}
      >
        <View
          pointerEvents="none"
          style={{
            backgroundColor: heroSoft,
            borderRadius: theme.radius.full,
            height: HERO_DECORATION_LARGE,
            left: -theme.spacing[16],
            opacity: theme.isDark ? 0.18 : 0.36,
            position: "absolute",
            top: -theme.spacing[16],
            width: HERO_DECORATION_LARGE,
          }}
        />
        <View
          pointerEvents="none"
          style={{
            borderColor: heroOutline,
            borderRadius: theme.radius.full,
            borderWidth: 1,
            height: HERO_DECORATION_MEDIUM,
            opacity: theme.isDark ? 0.28 : 0.54,
            position: "absolute",
            right: -theme.spacing[10],
            top: theme.spacing[12],
            width: HERO_DECORATION_MEDIUM,
          }}
        />
        <View
          pointerEvents="none"
          style={{
            borderColor: heroOutline,
            borderRadius: theme.radius.full,
            borderWidth: 1,
            bottom: -theme.spacing[12],
            height: HERO_DECORATION_SMALL,
            left: theme.spacing[8],
            opacity: theme.isDark ? 0.22 : 0.48,
            position: "absolute",
            width: HERO_DECORATION_SMALL,
          }}
        />

        <View
          style={{
            alignSelf: "center",
            maxWidth: DESKTOP_HERO_COPY_MAX_WIDTH + theme.spacing[10],
            width: "100%",
          }}
        >
          <View style={{ gap: theme.spacing[4] }}>
            <AppLogo width={DESKTOP_HERO_LOGO_WIDTH} />
            <AppText variant="bodyMedium">{t("heroTagline")}</AppText>
          </View>

          <View
            style={{
              backgroundColor: dividerColor,
              height: 1,
              marginVertical: theme.spacing[6],
              width: DESKTOP_HERO_COPY_MAX_WIDTH - theme.spacing[2],
            }}
          />

          <View style={{ gap: theme.spacing[1], maxWidth: DESKTOP_HERO_COPY_MAX_WIDTH }}>
            <AppText variant="h1">{t("heroLine1")}</AppText>
            <AppText variant="h1">{t("heroLine2")}</AppText>
            <AppText tone="primary" variant="h1">
              {t("heroLine3")}
            </AppText>
          </View>
        </View>
      </View>

      <View
        style={{
          backgroundColor: panelBackground,
          flex: DESKTOP_PANEL_FLEX,
          justifyContent: "center",
          minWidth: 0,
          paddingHorizontal: theme.spacing[10],
          paddingVertical: theme.spacing[10],
          position: "relative",
        }}
      >
        <View
          style={{
            alignItems: "flex-end",
            position: "absolute",
            right: theme.spacing[8],
            top: theme.spacing[8],
          }}
        >
          <LanguageToggle
            onChange={handleLanguageChange}
            options={languageOptions}
            value={activeLanguage}
          />
        </View>

        <View
          style={{
            alignSelf: "center",
            gap: theme.spacing[6],
            maxWidth: DESKTOP_FORM_MAX_WIDTH,
            width: "100%",
          }}
        >
          <View style={{ gap: theme.spacing[2] }}>
            <AppText
              style={{ letterSpacing: -1 }}
              variant="display"
            >
              {title}
            </AppText>
            <AppText tone="secondary" variant="bodyMedium">
              {description}
            </AppText>
          </View>

          {children}
        </View>
      </View>
    </View>
  );
}
