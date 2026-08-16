import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { AppText } from "@/components/primitives/AppText";
import { setPreferredLanguage } from "@/i18n";

export function LanguageSelector() {
  const { i18n, t } = useTranslation("app");

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
