import { useTranslation } from "react-i18next";

import {
  ChatMessage,
  createChatActionIcon,
  type ChatMessageAction,
  type ChatMessageProps,
} from "@/components/patterns/chat-message";

export type AssistantMessageProps = Omit<
  ChatMessageProps,
  "actions" | "role" | "showActions"
> & {
  onCopy?: () => void | Promise<void>;
  onDislike?: () => void | Promise<void>;
  onLike?: () => void | Promise<void>;
  onRetry?: () => void | Promise<void>;
};

export function AssistantMessage({
  children,
  onCopy,
  onDislike,
  onLike,
  onRetry,
  showAvatar = true,
  ...props
}: AssistantMessageProps) {
  const { t } = useTranslation("common");
  const actions: ChatMessageAction[] = [];

  if (onCopy) {
    actions.push({
      accessibilityLabel: t("chat.actions.copy"),
      icon: createChatActionIcon("C"),
      key: "copy",
      onPress: onCopy,
      tooltip: t("chat.actions.copy"),
    });
  }

  if (onRetry) {
    actions.push({
      accessibilityLabel: t("chat.actions.retry"),
      icon: createChatActionIcon("R"),
      key: "retry",
      onPress: onRetry,
      tooltip: t("chat.actions.retry"),
    });
  }

  if (onLike) {
    actions.push({
      accessibilityLabel: t("chat.actions.like"),
      icon: createChatActionIcon("+"),
      key: "like",
      onPress: onLike,
      tooltip: t("chat.actions.like"),
    });
  }

  if (onDislike) {
    actions.push({
      accessibilityLabel: t("chat.actions.dislike"),
      icon: createChatActionIcon("-"),
      key: "dislike",
      onPress: onDislike,
      tooltip: t("chat.actions.dislike"),
    });
  }

  return (
    <ChatMessage
      {...props}
      actions={actions}
      role="assistant"
      showActions={actions.length > 0}
      showAvatar={showAvatar}
    >
      {children}
    </ChatMessage>
  );
}
