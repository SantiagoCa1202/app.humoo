import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AssistantMessage } from "@/components/patterns/assistant-message";
import { AssistantTextBlock } from "@/components/patterns/assistant-text-block";
import { AppShell } from "@/components/patterns/AppShell";
import { ComponentBlock } from "@/components/patterns/component-block";
import { StateBlock } from "@/components/patterns/StateBlock";
import { StreamingStatus } from "@/components/patterns/streaming-status";
import { SuggestionChips } from "@/components/patterns/suggestion-chips";
import { UserMessage } from "@/components/patterns/user-message";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { TextArea } from "@/components/primitives/text-area";
import { Text } from "@/components/primitives/text";
import { ChatRemoteComponent } from "@/features/chat/remote-components";
import { useChatConversation, useSendChatMessage } from "@/features/chat/hooks";
import type {
  ChatComponentBlockRecord,
  ChatMessageBlockRecord,
  ChatMessageRecord,
} from "@/features/chat/types";
import { useAppTheme } from "@/theme/ThemeProvider";

function isForbiddenError(error: unknown) {
  return (
    typeof error === "object" &&
    error !== null &&
    "status" in error &&
    (error as { status?: number }).status === 403
  );
}

function formatMessageTimestamp(value: string | null | undefined, locale: string) {
  if (!value) {
    return undefined;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return undefined;
  }

  return new Intl.DateTimeFormat(locale, {
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    month: "short",
  }).format(date);
}

function findLatestSuggestions(messages: ChatMessageRecord[]) {
  return [...messages]
    .reverse()
    .find((message) => message.senderType === "assistant" && message.suggestions.length > 0)
    ?.suggestions ?? [];
}

function RenderedBlock({
  block,
  disabled = false,
  onSendSuggestion,
}: {
  block: ChatMessageBlockRecord;
  disabled?: boolean;
  onSendSuggestion: (value: string) => void;
}) {
  if (block.type === "component") {
    return (
      <ComponentBlock>
        <ChatRemoteComponent
          block={block as ChatComponentBlockRecord}
          disabled={disabled}
          onSendSuggestion={onSendSuggestion}
        />
      </ComponentBlock>
    );
  }

  return (
    <ComponentBlock>
      <AssistantTextBlock
        text={block.text ?? (block.type === "error" ? "No pude completar este bloque." : "")}
        tone={block.type === "status" ? "muted" : "default"}
      />
    </ComponentBlock>
  );
}

export default function ChatScreen() {
  const { t, i18n } = useTranslation(["app", "common"]);
  const { theme } = useAppTheme();
  const conversationQuery = useChatConversation();
  const sendMessage = useSendChatMessage();
  const [draft, setDraft] = useState("");
  const conversation = conversationQuery.data;

  const suggestions = useMemo(
    () => findLatestSuggestions(conversation?.messages ?? []),
    [conversation?.messages]
  );

  const handleSend = (content: string) => {
    const normalized = content.trim();

    if (!normalized || !conversation?.id || sendMessage.isPending) {
      return;
    }

    setDraft("");
    sendMessage.mutate({
      content: normalized,
      conversationId: conversation.id,
      locale: i18n.language,
    });
  };

  if (conversationQuery.isLoading && !conversation) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={t("app:chatLoadingDescription")}
          title={t("app:chatLoadingTitle")}
          tone="loading"
        />
      </AppShell>
    );
  }

  if (conversationQuery.isError && !conversation) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={
            isForbiddenError(conversationQuery.error)
              ? t("app:chatForbiddenDescription")
              : conversationQuery.error.message
          }
          onAction={() => {
            void conversationQuery.refetch();
          }}
          title={
            isForbiddenError(conversationQuery.error)
              ? t("app:chatForbiddenTitle")
              : t("app:chatErrorTitle")
          }
          tone={isForbiddenError(conversationQuery.error) ? "forbidden" : "error"}
        />
      </AppShell>
    );
  }

  if (!conversation) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={t("app:chatEmptyDescription")}
          title={t("app:chatEmptyTitle")}
          tone="empty"
        />
      </AppShell>
    );
  }

  return (
    <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
      <View style={{ gap: theme.spacing[4] }}>
        <View style={{ gap: theme.spacing[4] }}>
          {conversation.messages.map((message) =>
            message.senderType === "user" ? (
              <UserMessage
                key={message.id}
                name={t("app:chatParticipantUser")}
                timestamp={formatMessageTimestamp(message.createdAt, i18n.language)}
              >
                {message.contentText ?? ""}
              </UserMessage>
            ) : (
              <AssistantMessage
                key={message.id}
                name={t("app:chatParticipantAssistant")}
                timestamp={formatMessageTimestamp(message.createdAt, i18n.language)}
              >
                <View style={{ gap: theme.spacing[3] }}>
                  {message.blocks.length
                    ? message.blocks.map((block, index) => (
                        <RenderedBlock
                          block={block}
                          disabled={sendMessage.isPending}
                          key={block.id ?? `${message.id}-${index}`}
                          onSendSuggestion={handleSend}
                        />
                      ))
                    : (
                        <AssistantTextBlock text={message.contentText ?? ""} />
                      )}
                </View>
              </AssistantMessage>
            )
          )}

          {sendMessage.isPending ? (
            <AssistantMessage
              name={t("app:chatParticipantAssistant")}
              showAvatar
              streaming
            >
              <StreamingStatus
                compact
                description={t("app:chatStreamingDescription")}
                steps={[
                  {
                    id: "chat-context",
                    label: t("app:chatStreamingStepContext"),
                    status: "done",
                  },
                  {
                    id: "chat-response",
                    label: t("app:chatStreamingStepResponse"),
                    status: "active",
                  },
                ]}
                title={t("app:chatStreamingTitle")}
              />
            </AssistantMessage>
          ) : null}
        </View>

        {suggestions.length ? (
          <BaseCard padding="md" radius="lg" variant="muted">
            <View style={{ gap: theme.spacing[3] }}>
              <Text tone="secondary" variant="overline">
                {t("app:chatSuggestionsTitle")}
              </Text>
              <SuggestionChips
                accessibilityLabel={t("app:chatSuggestionsAccessibilityLabel")}
                disabled={sendMessage.isPending}
                onSelect={(suggestion) => handleSend(suggestion.value ?? suggestion.label)}
                suggestions={suggestions.map((suggestion, index) => ({
                  id: `chat-suggestion-${index}`,
                  label: suggestion,
                  value: suggestion,
                }))}
              />
            </View>
          </BaseCard>
        ) : null}

        <BaseCard padding="md" radius="lg" variant="elevated">
          <View style={{ gap: theme.spacing[3] }}>
            <Text variant="title">{t("app:chatComposerTitle")}</Text>
            <TextArea
              autoGrow
              editable={!sendMessage.isPending}
              minHeight={theme.spacing[16]}
              onChangeText={setDraft}
              placeholder={t("app:chatComposerPlaceholder")}
              value={draft}
            />
            <View
              style={{
                alignItems: "center",
                flexDirection: "row",
                justifyContent: "space-between",
              }}
            >
              <Text tone="secondary" variant="caption">
                {t("app:chatComposerHelper")}
              </Text>
              <Button
                disabled={!draft.trim()}
                label={t("app:chatComposerSend")}
                loading={sendMessage.isPending}
                onPress={() => handleSend(draft)}
              />
            </View>
          </View>
        </BaseCard>
      </View>
    </AppShell>
  );
}
