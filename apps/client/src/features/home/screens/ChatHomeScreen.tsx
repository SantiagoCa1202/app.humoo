import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

export default function ChatHomeScreen() {
  const { t } = useTranslation("app");
  const { session } = useAuth();

  return (
    <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
      <Card style={{ gap: spacing[2] }}>
        <AppText variant="title">
          {session?.user.firstName ?? session?.user.name}, welcome back.
        </AppText>
        <AppText muted variant="bodyLarge">
          This provisional screen validates the private shell, organization
          context, and future AI surface.
        </AppText>
      </Card>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[4] }}>
        <Card style={{ flex: 1, minWidth: 240, gap: spacing[2] }}>
          <AppText variant="overline">Today overview</AppText>
          <AppText muted>0 events synced</AppText>
          <AppText muted>0 prep lists generated</AppText>
          <AppText muted>0 inventory alerts</AppText>
        </Card>
        <Card style={{ flex: 1, minWidth: 240, gap: spacing[2] }}>
          <AppText variant="overline">{t("quickActions")}</AppText>
          <AppText muted>Ask what needs prep this week.</AppText>
          <AppText muted>Review missing ingredients once the API is connected.</AppText>
          <AppText muted>Open the future calendar and operations modules.</AppText>
        </Card>
      </View>
      <Card style={{ gap: spacing[2] }}>
        <AppText variant="overline">Chat area</AppText>
        <AppText muted>
          The first conversational components should land here after the Laravel
          API and AI contracts are unblocked.
        </AppText>
      </Card>
    </AppShell>
  );
}
