import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppText } from "@/components/primitives/AppText";
import { setPreferredLanguage } from "@/i18n";
import { useAppTheme } from "@/theme/ThemeProvider";

export function LanguageSelector() {
  const { i18n, t } = useTranslation("app");
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: 10 }}>
      <AppText variant="subtitle">{t("language")}</AppText>
      <View style={{ flexDirection: "row", gap: 10 }}>
        {[
          { code: "en", label: "English" },
          { code: "es", label: "Espanol" },
        ].map((option) => {
          const active = i18n.language === option.code;

          return (
            <Pressable
              key={option.code}
              onPress={() => setPreferredLanguage(option.code as "en" | "es")}
              style={{
                backgroundColor: active
                  ? theme.colors.primary
                  : theme.colors.surfaceMuted,
                borderRadius: theme.radius.pill,
                paddingHorizontal: 14,
                paddingVertical: 10,
              }}
            >
              <AppText
                variant="body"
                style={{
                  color: active
                    ? theme.colors.primaryContrast
                    : theme.colors.text,
                }}
              >
                {option.label}
              </AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}
