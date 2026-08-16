import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AssistantMessage } from "@/components/patterns/assistant-message";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { UserMessage } from "@/components/patterns/user-message";
import { AppText } from "@/components/primitives/AppText";
import { humooContentWidths, spacing } from "@/theme";

export default function ChatHomeScreen() {
  const { t } = useTranslation("app");
  const { session } = useAuth();

  return (
    <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
      <Card style={{ gap: spacing[2] }}>
        <AppText variant="title">
          {t("chatWelcomeBack", {
            name: session?.user.firstName ?? session?.user.name,
          })}
        </AppText>
        <AppText muted variant="bodyLarge">
          {t("chatWelcomeBody")}
        </AppText>
      </Card>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[4] }}>
        <Card style={{ flex: 1, minWidth: 240, gap: spacing[2] }}>
          <AppText variant="overline">{t("chatOverviewTitle")}</AppText>
          <AppText muted>{t("chatOverviewEvents")}</AppText>
          <AppText muted>{t("chatOverviewPrep")}</AppText>
          <AppText muted>{t("chatOverviewAlerts")}</AppText>
        </Card>
        <Card style={{ flex: 1, minWidth: 240, gap: spacing[2] }}>
          <AppText variant="overline">{t("quickActions")}</AppText>
          <AppText muted>{t("chatQuickActionPrep")}</AppText>
          <AppText muted>{t("chatQuickActionInventory")}</AppText>
          <AppText muted>{t("chatQuickActionModules")}</AppText>
        </Card>
      </View>
      <View style={{ gap: spacing[4], maxWidth: humooContentWidths.chat }}>
        <AppText variant="overline">{t("chatAreaTitle")}</AppText>
        <AssistantMessage
          name={t("chatAssistantName")}
          onCopy={async () => {}}
          streaming
          timestamp={t("chatSampleNow")}
        >
          <AppText>
            {t("chatAssistantSample")}
          </AppText>
        </AssistantMessage>
        <UserMessage
          name={session?.user.firstName ?? session?.user.name}
          onCopy={async () => {}}
          onEdit={async () => {}}
          status={t("chatUserSampleStatus")}
          timestamp={t("chatSampleNow")}
        >
          {t("chatUserSample")}
        </UserMessage>
        <AssistantMessage
          error
          name={t("chatAssistantName")}
          onRetry={async () => {}}
          timestamp={t("chatSampleNow")}
        >
          <AppText>
            {t("chatAssistantErrorSample")}
          </AppText>
        </AssistantMessage>
      </View>
    </AppShell>
  );
}
