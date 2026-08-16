import { Link } from "expo-router";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AuthLayout } from "@/components/patterns/AuthLayout";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

export default function WelcomeScreen() {
  const { t } = useTranslation(["auth", "common"]);

  return (
    <AuthLayout
      description={t("auth:welcomeBody")}
      title={t("auth:welcomeTitle")}
    >
      <View style={{ gap: spacing[3] }}>
        <Link href="/(public)/login" asChild>
          <AppButton label={t("auth:login")} />
        </Link>
        <Link href="/(public)/register" asChild>
          <AppButton label={t("auth:register")} variant="secondary" />
        </Link>
      </View>
      <AppText muted>{t("common:appName")} at `humoo.ai`</AppText>
    </AuthLayout>
  );
}
