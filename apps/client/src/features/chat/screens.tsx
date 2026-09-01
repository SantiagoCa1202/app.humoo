import { Feather } from "@expo/vector-icons";
import { router, type Href } from "expo-router";
import { memo, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AssistantMessage } from "@/components/patterns/assistant-message";
import { AssistantTextBlock } from "@/components/patterns/assistant-text-block";
import { AlertCard } from "@/components/patterns/alert-card";
import { AppShell } from "@/components/patterns/AppShell";
import { ComponentBlock } from "@/components/patterns/component-block";
import { ChatComposer } from "@/components/patterns/chat-composer";
import { StateBlock } from "@/components/patterns/StateBlock";
import { StreamingStatus } from "@/components/patterns/streaming-status";
import { SuggestionChips } from "@/components/patterns/suggestion-chips";
import { UserMessage } from "@/components/patterns/user-message";
import { Button } from "@/components/primitives/button";
import { ChatRemoteComponent } from "@/features/chat/remote-components";
import { useChatLiveStream } from "@/features/chat/use-chat-live-stream";
import { useExecutionPlanUpdates } from "@/features/chat/use-execution-plan-updates";
import { createChatClientMessageId } from "@/features/chat/api";
import {
  useChatConversation,
  useDeleteChatConversation,
  useLoadOlderChatMessages,
  useSendChatMessage,
} from "@/features/chat/hooks";
import type {
  ChatComponentBlockRecord,
  ChatMessageBlockRecord,
  ChatMessageRecord,
  ChatEntityReference,
} from "@/features/chat/types";
import { useAppTheme } from "@/theme/ThemeProvider";
import { formatDisplayDateTime } from "@/utils/date-time";
import { routes } from "@/navigation/routes";

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
  return formatDisplayDateTime(value, locale) ?? undefined;
}

function findLatestSuggestions(messages: ChatMessageRecord[]) {
  for (let index = messages.length - 1; index >= 0; index -= 1) {
    const message = messages[index];

    if (message.senderType === "assistant") {
      return message.suggestions;
    }
  }

  return [];
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

const RenderedBlock = memo(function RenderedBlock({
  block,
  disabled = false,
  onOpenEntity,
  onSendSuggestion,
}: {
  block: ChatMessageBlockRecord;
  disabled?: boolean;
  onOpenEntity: (reference: ChatEntityReference) => void;
  onSendSuggestion: (value: string) => void;
}) {
  if (block.type === "component") {
    return (
      <ComponentBlock>
        <ChatRemoteComponent
          block={block as ChatComponentBlockRecord}
          disabled={disabled}
          onOpenEntity={onOpenEntity}
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
});

const ChatMessageItem = memo(function ChatMessageItem({
  assistantName,
  disabled,
  message,
  messageGap,
  onOpenEntity,
  onSendSuggestion,
  timestamp,
  userName,
}: {
  assistantName: string;
  disabled: boolean;
  message: ChatMessageRecord;
  messageGap: number;
  onOpenEntity: (reference: ChatEntityReference) => void;
  onSendSuggestion: (value: string) => void;
  timestamp?: string;
  userName: string;
}) {
  if (message.senderType === "user") {
    return (
      <UserMessage name={userName} timestamp={timestamp}>
        {message.contentText ?? ""}
      </UserMessage>
    );
  }

  return (
    <AssistantMessage name={assistantName} timestamp={timestamp}>
      <View style={{ gap: messageGap }}>
        {message.blocks.length ? (
          message.blocks.map((block, index) => (
            <RenderedBlock
              block={block}
              disabled={disabled}
              key={block.id ?? `${message.id}-${index}`}
              onOpenEntity={onOpenEntity}
              onSendSuggestion={onSendSuggestion}
            />
          ))
        ) : (
          <AssistantTextBlock text={message.contentText ?? ""} />
        )}
      </View>
    </AssistantMessage>
  );
});

export default function ChatScreen() {
  const { t, i18n } = useTranslation(["app", "common"]);
  const { theme } = useAppTheme();
  const conversationQuery = useChatConversation();
  const deleteConversation = useDeleteChatConversation();
  const loadOlderMessages = useLoadOlderChatMessages();
  const sendMessage = useSendChatMessage();
  const [draft, setDraft] = useState("");
  const [composerHeight, setComposerHeight] = useState(
    theme.layout.controlHeight + theme.spacing[6],
  );
  const [showDeleteConfirmation, setShowDeleteConfirmation] = useState(false);
  const messageListRef = useRef<FlatList<ChatMessageRecord> | null>(null);
  const lastScrolledMessageId = useRef<string | null>(null);
  const conversation = conversationQuery.data;
  const chatLiveStream = useChatLiveStream(conversation?.id);
  useExecutionPlanUpdates(conversation?.id);
  const visibleMessages = useMemo(
    () =>
      (conversation?.messages ?? []).filter(
        (message) => !isBootstrapMessage(message),
      ),
    [conversation?.messages],
  );

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
    const latestMessageId = visibleMessages.at(-1)?.id;

    if (!latestMessageId || latestMessageId === lastScrolledMessageId.current) {
      return;
    }

    messageListRef.current?.scrollToEnd({
      animated: lastScrolledMessageId.current !== null,
    });
    lastScrolledMessageId.current = latestMessageId;
  }, [visibleMessages]);

  useEffect(() => {
    if (!chatLiveStream.stream?.text) {
      return;
    }

    const frame = requestAnimationFrame(() => {
      messageListRef.current?.scrollToEnd({ animated: false });
    });

    return () => cancelAnimationFrame(frame);
  }, [chatLiveStream.stream?.text]);

  const handleSend = useCallback((content: string) => {
    const normalized = content.trim();

    if (!normalized || !conversation?.id || sendMessage.isPending) {
      return;
    }

    setDraft("");
    chatLiveStream.reset();
    sendMessage.mutate({
      clientMessageId: createChatClientMessageId(),
      content: normalized,
      conversationId: conversation.id,
      locale: i18n.language,
    });
  }, [chatLiveStream, conversation?.id, i18n.language, sendMessage]);

  const handleLoadOlderMessages = useCallback(() => {
    if (
      !conversation?.id ||
      !conversation.messagePagination?.hasMore ||
      loadOlderMessages.isPending
    ) {
      return;
    }

    loadOlderMessages.mutate(conversation.id);
  }, [conversation, loadOlderMessages]);

  const handleOpenEntity = useCallback(
    ({ id: entityId, type: entityType }: ChatEntityReference) => {
      const routesByEntity: Record<string, Href | ((id: string) => Href)> = {
        event: (id) =>
          ({
            pathname: routes.app.eventDetail,
            params: { eventId: id },
          }) as Href,
        menu: (id) =>
          ({ pathname: routes.app.menuDetail, params: { menuId: id } }) as Href,
        recipe: (id) =>
          ({
            pathname: routes.app.recipeDetail,
            params: { recipeId: id },
          }) as Href,
        prep_list: (id) =>
          ({
            pathname: routes.app.prepDetail,
            params: { prepListId: id },
          }) as Href,
        prep: (id) =>
          ({
            pathname: routes.app.prepDetail,
            params: { prepListId: id },
          }) as Href,
        task: (id) =>
          ({ pathname: routes.app.taskDetail, params: { taskId: id } }) as Href,
        tasks: routes.app.tasks,
        team: (id) =>
          ({ pathname: routes.app.teamDetail, params: { teamId: id } }) as Href,
        teams: routes.app.teamRoster,
        client: (id) =>
          ({
            pathname: routes.app.directoryClientDetail,
            params: { id },
          }) as Href,
        contact: (id) =>
          ({
            pathname: routes.app.directoryContactDetail,
            params: { id },
          }) as Href,
        venue: (id) =>
          ({
            pathname: routes.app.directoryVenueDetail,
            params: { id },
          }) as Href,
        membership: routes.app.teamRoster,
        station: routes.app.stations,
        shift: routes.app.shifts,
        availability: routes.app.availability,
        clients: routes.app.directoryClients,
        contacts: routes.app.directoryContacts,
        venues: routes.app.directoryVenues,
        prep_lists: routes.app.prep,
        recipes: routes.app.recipes,
        menus: routes.app.menus,
      };
      const route = routesByEntity[entityType];
      if (!route) return;
      router.push(typeof route === "function" ? route(entityId) : route);
    },
    [],
  );

  const renderMessage = useCallback(
    ({ item }: { item: ChatMessageRecord }) => (
      <ChatMessageItem
        assistantName={t("app:chatParticipantAssistant")}
        disabled={sendMessage.isPending}
        message={item}
        messageGap={theme.spacing[3]}
        onOpenEntity={handleOpenEntity}
        onSendSuggestion={handleSend}
        timestamp={formatMessageTimestamp(item.createdAt, i18n.language)}
        userName={t("app:chatParticipantUser")}
      />
    ),
    [
      handleOpenEntity,
      handleSend,
      i18n.language,
      sendMessage.isPending,
      t,
      theme.spacing,
    ],
  );

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
    <AppShell
      fillContent
      headerActions={
        !showDeleteConfirmation ? (
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
        ) : null
      }
      subtitle={t("chatSubtitle")}
      title={t("chatTitle")}
    >
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
          <View
            style={{
              paddingTop: theme.layout.controlHeight + theme.spacing[2],
            }}
          >
            <AlertCard
              description={t("app:chatDeleteError")}
              title={t("app:chatDeleteErrorTitle")}
              tone="error"
            />
          </View>
        ) : null}

        <FlatList
          data={visibleMessages}
          contentContainerStyle={{
            flexGrow: 1,
            gap: theme.spacing[4],
            paddingBottom: composerHeight + theme.spacing[8],
          }}
          contentInsetAdjustmentBehavior="automatic"
          initialNumToRender={12}
          keyboardShouldPersistTaps="handled"
          keyExtractor={(message) => message.id}
          ListFooterComponent={
            sendMessage.isPending ? (
              <AssistantMessage
                name={t("app:chatParticipantAssistant")}
                showAvatar
                streaming={!chatLiveStream.stream?.text}
              >
                {chatLiveStream.stream?.text ? (
                  <AssistantTextBlock text={chatLiveStream.stream.text} />
                ) : null}
                {!chatLiveStream.stream?.text ? (
                  <StreamingStatus
                    compact
                    description={chatLiveStream.stream?.activity ?? t("app:chatStreamingDescription")}
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
                    title={chatLiveStream.stream?.activity ?? t("app:chatStreamingTitle")}
                  />
                ) : null}
              </AssistantMessage>
            ) : null
          }
          ListHeaderComponent={
            conversation.messagePagination?.hasMore ? (
              <View style={{ alignItems: "center", gap: theme.spacing[2] }}>
                <Button
                  disabled={loadOlderMessages.isPending}
                  label={
                    loadOlderMessages.isError
                      ? t("app:chatHistoryLoadRetry")
                      : t("app:chatHistoryLoadPrevious")
                  }
                  loading={loadOlderMessages.isPending}
                  onPress={handleLoadOlderMessages}
                  size="sm"
                  variant="ghost"
                />
                {loadOlderMessages.isError ? (
                  <AlertCard
                    description={t("app:chatHistoryLoadError")}
                    title={t("app:chatHistoryLoadErrorTitle")}
                    tone="error"
                  />
                ) : null}
              </View>
            ) : null
          }
          maintainVisibleContentPosition={{ minIndexForVisible: 0 }}
          maxToRenderPerBatch={10}
          nestedScrollEnabled
          ref={messageListRef}
          removeClippedSubviews
          renderItem={renderMessage}
          showsVerticalScrollIndicator={false}
          style={{ flex: 1, minHeight: 0 }}
          windowSize={7}
        />

        <View
          onLayout={(event) => {
            const nextHeight = event.nativeEvent.layout.height;

            setComposerHeight((currentHeight) =>
              currentHeight === nextHeight ? currentHeight : nextHeight,
            );
          }}
          style={{
            bottom: 0,
            left: 0,
            position: "absolute",
            right: 0,
            zIndex: 1,
          }}
        >
          <ChatComposer
            disabled={deleteConversation.isPending}
            onChangeText={setDraft}
            onSend={() => handleSend(draft)}
            sending={sendMessage.isPending}
            value={draft}
          />
        </View>
      </View>
    </AppShell>
  );
}
