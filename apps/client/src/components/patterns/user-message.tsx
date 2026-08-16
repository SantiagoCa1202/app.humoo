import { useTranslation } from "react-i18next";

import {
  ChatMessage,
  createChatActionIcon,
  type ChatMessageAction,
  type ChatMessageProps,
} from "@/components/patterns/chat-message";
import { Text } from "@/components/primitives/text";

export type UserMessageProps = Omit<
  ChatMessageProps,
  "actions" | "role" | "showActions" | "showAvatar"
> & {
  onCopy?: () => void | Promise<void>;
  onEdit?: () => void | Promise<void>;
  showAvatar?: boolean;
  status?: React.ReactNode;
};

export function UserMessage({
  children,
  error,
  footer,
  onCopy,
  onEdit,
  showAvatar = false,
  status,
  ...props
}: UserMessageProps) {
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

  if (onEdit) {
    actions.push({
      accessibilityLabel: t("chat.actions.edit"),
      icon: createChatActionIcon("E"),
      key: "edit",
      onPress: onEdit,
      tooltip: t("chat.actions.edit"),
    });
  }

  const statusNode =
    status !== undefined && status !== null && status !== ""
      ? typeof status === "string" || typeof status === "number"
        ? (
            <Text
              selectable
              tone="inverse"
              variant="caption"
            >
              {status}
            </Text>
          )
        : (
            status
          )
      : null;

  return (
    <ChatMessage
      {...props}
      actions={actions}
      error={error}
      footer={footer ?? statusNode}
      role="user"
      showActions={actions.length > 0}
      showAvatar={showAvatar}
    >
      {children}
    </ChatMessage>
  );
}
