import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";
import type { ThemePreference } from "@/theme/tokens";

export function ThemeToggle() {
  const { t } = useTranslation("app");
  const { theme, preference, setPreference } = useAppTheme();

  const options: ThemePreference[] = ["system", "light", "dark"];

  return (
    <View style={{ gap: 10 }}>
      <AppText variant="subtitle">{t("theme")}</AppText>
      <View style={{ flexDirection: "row", gap: 10, flexWrap: "wrap" }}>
        {options.map((option) => {
          const active = preference === option;

          return (
            <Pressable
              key={option}
              onPress={() => setPreference(option)}
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
                {t(option)}
              </AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}
