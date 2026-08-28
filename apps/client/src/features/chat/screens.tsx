import { Feather } from "@expo/vector-icons";
import { useEffect, useMemo, useRef, useState } from "react";
import { ScrollView, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AssistantMessage } from "@/components/patterns/assistant-message";
import { AssistantTextBlock } from "@/components/patterns/assistant-text-block";
import { AlertCard } from "@/components/patterns/alert-card";
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
import { createChatClientMessageId } from "@/features/chat/api";
import {
  useChatConversation,
  useDeleteChatConversation,
  useSendChatMessage,
} from "@/features/chat/hooks";
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

function formatMessageTimestamp(
  value: string | null | undefined,
  locale: string,
) {
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
  return (
    [...messages]
      .reverse()
      .find((message) => message.senderType === "assistant")?.suggestions ?? []
  );
}

function isBootstrapMessage(message: ChatMessageRecord) {
  return (
    message.senderType === "assistant" &&
    !message.parentMessageId &&
    message.blocks.some(
      (block) =>
        block.type === "component" &&
        block.component === "clarification.options",
    )
  );
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
        text={
          block.text ??
          (block.type === "error" ? "No pude completar este bloque." : "")
        }
        tone={block.type === "status" ? "muted" : "default"}
      />
    </ComponentBlock>
  );
}

export default function ChatScreen() {
  const { t, i18n } = useTranslation(["app", "common"]);
  const { theme } = useAppTheme();
  const conversationQuery = useChatConversation();
  const deleteConversation = useDeleteChatConversation();
  const sendMessage = useSendChatMessage();
  const [draft, setDraft] = useState("");
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);
  const messageScrollRef = useRef<ScrollView | null>(null);
  const conversation = conversationQuery.data;

  const suggestions = useMemo(() => {
    const messages = conversation?.messages ?? [];
    const hasUserMessage = messages.some(
      (message) => message.senderType === "user",
    );

    if (!hasUserMessage || sendMessage.isPending) {
      return [];
    }

    return findLatestSuggestions(messages);
  }, [conversation?.messages, sendMessage.isPending]);

  useEffect(() => {
    messageScrollRef.current?.scrollToEnd({ animated: false });
  }, [conversation?.messages.length, sendMessage.isPending]);

  const handleSend = (content: string) => {
    const normalized = content.trim();

    if (!normalized || !conversation?.id || sendMessage.isPending) {
      return;
    }

    setDraft("");
    sendMessage.mutate({
      clientMessageId: createChatClientMessageId(),
      content: normalized,
      conversationId: conversation.id,
      locale: i18n.language,
    });
  };

  const handleDelete = () => {
    if (!conversation?.id || deleteConversation.isPending) {
      return;
    }

    deleteConversation.mutate(conversation.id, {
      onSuccess: () => setShowDeleteConfirmation(false),
    });
  };

  if (conversationQuery.isPending && !conversation) {
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
          tone={
            isForbiddenError(conversationQuery.error) ? "forbidden" : "error"
          }
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
    <AppShell fillContent title={t("chatTitle")} subtitle={t("chatSubtitle")}>
      <View style={{ flex: 1, gap: theme.spacing[4], minHeight: 0 }}>
        {showDeleteConfirmation ? (
          <View style={{ gap: theme.spacing[2] }}>
            <AlertCard
              description={t("app:chatDeleteConfirmDescription")}
              title={t("app:chatDeleteConfirmTitle")}
              tone="warning"
            />
            <View
              style={{
                flexDirection: "row",
                flexWrap: "wrap",
                gap: theme.spacing[2],
                justifyContent: "flex-end",
              }}
            >
              <Button
                label={t("app:chatDeleteCancel")}
                onPress={() => setShowDeleteConfirmation(false)}
                size="sm"
                variant="ghost"
              />
              <Button
                label={t("app:chatDeleteConfirm")}
                loading={deleteConversation.isPending}
                onPress={handleDelete}
                size="sm"
                variant="destructive"
              />
            </View>
          </View>
        ) : deleteConversation.isError ? (
          <AlertCard
            description={t("app:chatDeleteError")}
            title={t("app:chatDeleteErrorTitle")}
            tone="error"
          />
        ) : null}

        {!showDeleteConfirmation ? (
          <View style={{ alignItems: "flex-end" }}>
            <Button
              accessibilityLabel={t("app:chatDeleteButton")}
              disabled={sendMessage.isPending || deleteConversation.isPending}
              label={
                deleteConversation.isError
                  ? t("app:chatDeleteRetry")
                  : t("app:chatDeleteButton")
              }
              leftIcon={<Feather name="trash-2" size={theme.iconSizes.sm} />}
              loading={deleteConversation.isPending}
              onPress={() => {
                if (deleteConversation.isError) {
                  handleDelete();
                  return;
                }

                setShowDeleteConfirmation(true);
              }}
              size="sm"
              variant="destructive"
            />
          </View>
        ) : null}

        <ScrollView
          contentContainerStyle={{
            flexGrow: 1,
            gap: theme.spacing[4],
            paddingBottom: theme.spacing[2],
          }}
          contentInsetAdjustmentBehavior="automatic"
          keyboardShouldPersistTaps="handled"
          nestedScrollEnabled
          ref={messageScrollRef}
          showsVerticalScrollIndicator={false}
          style={{ flex: 1, minHeight: 0 }}
        >
          {conversation.messages
            .filter((message) => !isBootstrapMessage(message))
            .map((message) =>
              message.senderType === "user" ? (
                <UserMessage
                  key={message.id}
                  name={t("app:chatParticipantUser")}
                  timestamp={formatMessageTimestamp(
                    message.createdAt,
                    i18n.language,
                  )}
                >
                  {message.contentText ?? ""}
                </UserMessage>
              ) : (
                <AssistantMessage
                  key={message.id}
                  name={t("app:chatParticipantAssistant")}
                  timestamp={formatMessageTimestamp(
                    message.createdAt,
                    i18n.language,
                  )}
                >
                  <View style={{ gap: theme.spacing[3] }}>
                    {message.blocks.length ? (
                      message.blocks.map((block, index) => (
                        <RenderedBlock
                          block={block}
                          disabled={sendMessage.isPending}
                          key={block.id ?? `${message.id}-${index}`}
                          onSendSuggestion={handleSend}
                        />
                      ))
                    ) : (
                      <AssistantTextBlock text={message.contentText ?? ""} />
                    )}
                  </View>
                </AssistantMessage>
              ),
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
        </ScrollView>

        <BaseCard padding="md" radius="lg" variant="elevated">
          <View style={{ gap: theme.spacing[3] }}>
            <TextArea
              editable={!sendMessage.isPending}
              minHeight={theme.spacing[16]}
              onChangeText={setDraft}
              placeholder={t("app:chatComposerPlaceholder")}
              scrollEnabled
              style={{
                height: theme.spacing[16] + theme.spacing[8],
              }}
              textAlignVertical="top"
              value={draft}
            />

            <View
              style={{
                alignItems: "flex-end",
                flexDirection: "row",
                gap: theme.spacing[3],
                justifyContent: "flex-end",
              }}
            >
              <Button
                accessibilityLabel={t("app:chatComposerSend")}
                containerStyle={{
                  borderRadius: theme.radius.full,
                  height: theme.spacing[10],
                  minHeight: theme.spacing[10],
                  paddingHorizontal: 0,
                  paddingVertical: 0,
                  width: theme.spacing[10],
                }}
                disabled={!draft.trim()}
                loading={sendMessage.isPending}
                onPress={() => handleSend(draft)}
                rightIcon={
                  <Feather
                    color={theme.colors.text.inverse}
                    name="arrow-right"
                    size={theme.iconSizes.md}
                  />
                }
              />
            </View>
          </View>
        </BaseCard>
      </View>
    </AppShell>
  );
}
