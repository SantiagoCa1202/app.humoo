import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import {
  assistantResponseToMessage,
  createChatClientMessageId,
  getChatConversation,
  sendChatMessage,
} from "@/features/chat/api";
import type {
  ChatAssistantResponseRecord,
  ChatConversationRecord,
  ChatMessageRecord,
  SendChatMessageInput,
} from "@/features/chat/types";
import { useWorkspace } from "@/features/workspace";

export const chatKeys = {
  workspace: (workspaceId: string) => ["workspace", workspaceId, "chat"] as const,
};

function dedupeMessages(messages: ChatMessageRecord[]) {
  const messageMap = new Map<string, ChatMessageRecord>();

  messages.forEach((message) => {
    messageMap.set(message.id, message);
  });

  return Array.from(messageMap.values()).sort((left, right) =>
    `${left.createdAt ?? ""}-${left.id}`.localeCompare(
      `${right.createdAt ?? ""}-${right.id}`
    )
  );
}

function buildFallbackConversation(
  current: ChatConversationRecord | undefined,
  nextConversationId: string | null | undefined
): ChatConversationRecord {
  return (
    current ?? {
      id: nextConversationId ?? "chat",
      messages: [],
      title: "Humoo AI",
    }
  );
}

export function applyAssistantResponseToConversation(
  current: ChatConversationRecord | undefined,
  response: ChatAssistantResponseRecord,
  conversationId: string | null | undefined,
  lastMessageAt: string | null | undefined
): ChatConversationRecord {
  const assistantMessage = assistantResponseToMessage(response);
  const baseConversation = buildFallbackConversation(current, conversationId);

  return {
    ...baseConversation,
    id: conversationId ?? baseConversation.id,
    lastMessageAt: lastMessageAt ?? baseConversation.lastMessageAt ?? null,
    messages: dedupeMessages([
      ...baseConversation.messages,
      assistantMessage,
    ]),
  };
}

export function useChatConversation() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return getChatConversation(session.token, workspaceId);
    },
    queryKey:
      workspaceId ? chatKeys.workspace(workspaceId) : ["workspace", "no-workspace", "chat"],
    retry: 1,
  });
}

export function useSendChatMessage() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: SendChatMessageInput) => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return sendChatMessage(session.token, workspaceId, {
        ...input,
        clientMessageId: input.clientMessageId ?? createChatClientMessageId(),
      });
    },
    onSuccess: (result) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData<ChatConversationRecord | undefined>(
        chatKeys.workspace(workspaceId),
        (current) => {
          const baseConversation = buildFallbackConversation(
            current,
            result.conversationId
          );

          return {
            ...baseConversation,
            id: result.conversationId ?? baseConversation.id,
            lastMessageAt:
              result.conversationLastMessageAt ?? baseConversation.lastMessageAt ?? null,
            messages: dedupeMessages([
              ...baseConversation.messages,
              result.userMessage,
              assistantResponseToMessage(result.assistantResponse),
            ]),
          };
        }
      );
    },
  });
}
