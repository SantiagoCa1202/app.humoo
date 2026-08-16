import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { AppText } from "@/components/primitives/AppText";
import { setPreferredLanguage } from "@/i18n";
import { spacing } from "@/theme";

export function LanguageSelector() {
  const { i18n, t } = useTranslation("app");

  return (
    <View style={{ gap: spacing[2] }}>
      <AppText variant="h4">{t("language")}</AppText>
      <View style={{ flexDirection: "row", gap: spacing[2] }}>
        {[
          { code: "en", label: "English" },
          { code: "es", label: "Espanol" },
        ].map((option) => {
          const active = i18n.language === option.code;

          return (
            <ChoiceChip
              key={option.code}
              active={active}
              label={option.label}
              onPress={() => setPreferredLanguage(option.code as "en" | "es")}
            />
          );
        })}
      </View>
    </View>
  );
}
