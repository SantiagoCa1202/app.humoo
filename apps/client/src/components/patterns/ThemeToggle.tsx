import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";
import type { ThemePreference } from "@/theme/tokens";
import { useAppTheme } from "@/theme/ThemeProvider";

export function ThemeToggle() {
  const { t } = useTranslation("app");
  const { preference, setPreference } = useAppTheme();

  const options: ThemePreference[] = ["system", "light", "dark"];

  return (
    <View style={{ gap: spacing[2] }}>
      <AppText variant="h4">{t("theme")}</AppText>
      <View style={{ flexDirection: "row", gap: spacing[2], flexWrap: "wrap" }}>
        {options.map((option) => {
          const active = preference === option;

          return (
            <ChoiceChip
              key={option}
              active={active}
              label={t(option)}
              onPress={() => setPreference(option)}
            />
          );
        })}
      </View>
    </View>
  );
}
