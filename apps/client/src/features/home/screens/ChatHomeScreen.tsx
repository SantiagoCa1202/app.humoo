import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { ActionResultCard } from "@/components/patterns/action-result-card";
import { AssistantMessage } from "@/components/patterns/assistant-message";
import { AssistantTextBlock } from "@/components/patterns/assistant-text-block";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { ClarificationCard } from "@/components/patterns/clarification-card";
import { ComponentBlock } from "@/components/patterns/component-block";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { ErrorRecoveryCard } from "@/components/patterns/error-recovery-card";
import { StreamingStatus } from "@/components/patterns/streaming-status";
import { SuggestionChips } from "@/components/patterns/suggestion-chips";
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
          timestamp={t("chatSampleNow")}
        >
          <AssistantTextBlock>
            {t("chatAssistantSample")}
          </AssistantTextBlock>
          <SuggestionChips
            accessibilityLabel={t("chatSuggestionsAccessibilityLabel")}
            onSelect={() => {}}
            suggestions={[
              {
                id: "prep",
                label: t("chatSuggestionPrep"),
              },
              {
                id: "events",
                label: t("chatSuggestionEvents"),
              },
              {
                id: "inventory",
                label: t("chatSuggestionInventory"),
              },
            ]}
          />
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
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <StreamingStatus
            description={t("chatStreamingSampleDescription")}
            steps={[
              {
                id: "context",
                label: t("chatStreamingStepContext"),
                status: "done",
              },
              {
                id: "prep",
                label: t("chatStreamingStepPrep"),
                status: "active",
              },
              {
                id: "result",
                label: t("chatStreamingStepResult"),
                status: "pending",
              },
            ]}
            title={t("chatStreamingSampleTitle")}
          />
        </AssistantMessage>
        <AssistantMessage
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <ComponentBlock label={t("chatOperationalPreviewLabel")}>
            <ActionPreviewCard
              action={t("chatOperationalPreviewAction")}
              changes={[
                {
                  after: t("chatOperationalPreviewAfter"),
                  before: t("chatOperationalPreviewBefore"),
                  id: "guest-count",
                  label: t("chatOperationalPreviewChangeLabel"),
                },
              ]}
              impact={t("chatOperationalPreviewImpact")}
              metadata={[
                {
                  id: "event",
                  label: t("chatOperationalPreviewMetadataEvent"),
                  value: t("chatOperationalPreviewMetadataEventValue"),
                },
              ]}
              title={t("chatOperationalPreviewTitle")}
            />
          </ComponentBlock>
        </AssistantMessage>
        <AssistantMessage
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <ComponentBlock label={t("chatOperationalConfirmationLabel")}>
            <ConfirmationCard
              description={t("chatOperationalConfirmationDescription")}
              details={[
                {
                  id: "field",
                  label: t("chatOperationalConfirmationDetailLabel"),
                  value: t("chatOperationalConfirmationDetailValue"),
                },
              ]}
              onCancel={async () => {}}
              onConfirm={async () => {}}
              title={t("chatOperationalConfirmationTitle")}
            />
          </ComponentBlock>
        </AssistantMessage>
        <AssistantMessage
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <ComponentBlock label={t("chatOperationalResultLabel")}>
            <ActionResultCard
              actionLabel={t("chatOperationalResultAction")}
              description={t("chatOperationalResultDescription")}
              details={[
                {
                  id: "timestamp",
                  label: t("chatOperationalResultDetailLabel"),
                  value: t("chatOperationalResultDetailValue"),
                },
              ]}
              onAction={async () => {}}
              status="success"
              title={t("chatOperationalResultTitle")}
            />
          </ComponentBlock>
        </AssistantMessage>
        <AssistantMessage
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <ComponentBlock label={t("chatOperationalRecoveryLabel")}>
            <ErrorRecoveryCard
              alternativeLabel={t("chatOperationalRecoveryAlternative")}
              description={t("chatOperationalRecoveryDescription")}
              errorCode="EVENT_CONFLICT"
              onAlternative={async () => {}}
              onRetry={async () => {}}
              safeDetail={t("chatOperationalRecoverySafeDetail")}
              title={t("chatOperationalRecoveryTitle")}
            />
          </ComponentBlock>
        </AssistantMessage>
        <AssistantMessage
          name={t("chatAssistantName")}
          timestamp={t("chatSampleNow")}
        >
          <ComponentBlock label={t("chatClarificationLabel")}>
            <ClarificationCard
              description={t("chatClarificationDescription")}
              onCancel={async () => {}}
              onSelect={async () => {}}
              onSubmit={async () => {}}
              options={[
                {
                  id: "today",
                  label: t("chatClarificationOptionToday"),
                },
                {
                  description: t("chatClarificationOptionTomorrowDescription"),
                  id: "tomorrow",
                  label: t("chatClarificationOptionTomorrow"),
                },
              ]}
              selected="today"
              title={t("chatClarificationTitle")}
            />
          </ComponentBlock>
        </AssistantMessage>
      </View>
    </AppShell>
  );
}
