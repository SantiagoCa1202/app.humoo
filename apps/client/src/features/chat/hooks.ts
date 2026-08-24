import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";

import { useAuth } from "@/auth/useAuth";
import {
  assistantResponseToMessage,
  createChatClientMessageId,
  getChatConversation,
  getChatHistory,
  sendChatMessage,
} from "@/features/chat/api";
import {
  readActiveConversationId,
  writeActiveConversationId,
} from "@/features/chat/storage";
import type {
  ChatAssistantResponseRecord,
  ChatConversationRecord,
  ChatMessageRecord,
  SendChatMessageInput,
} from "@/features/chat/types";
import { useWorkspace } from "@/features/workspace";

export const chatKeys = {
  workspace: (workspaceId: string) => ["workspace", workspaceId, "chat"] as const,
  history: (workspaceId: string) => [
    "workspace",
    workspaceId,
    "chat",
    "history",
  ] as const,
  conversation: (workspaceId: string, conversationId?: string | null) => [
    "workspace",
    workspaceId,
    "chat",
    "conversation",
    conversationId ?? "latest",
  ] as const,
};

function dedupeMessages(messages: ChatMessageRecord[]) {
  const messageMap = new Map<string, ChatMessageRecord>();

  messages.forEach((message) => {
    messageMap.set(message.id, message);
  });

  return Array.from(messageMap.values()).sort((left, right) =>
    compareMessages(left, right)
  );
}

function compareMessages(left: ChatMessageRecord, right: ChatMessageRecord) {
  if (left.parentMessageId === right.id) {
    return 1;
  }

  if (right.parentMessageId === left.id) {
    return -1;
  }

  const timestampComparison = (left.createdAt ?? "").localeCompare(
    right.createdAt ?? "",
  );

  if (timestampComparison !== 0) {
    return timestampComparison;
  }

  if (left.senderType === "user" && right.senderType === "assistant") {
    return -1;
  }

  if (left.senderType === "assistant" && right.senderType === "user") {
    return 1;
  }

  return left.id.localeCompare(right.id);
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
  const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
  const [storageReady, setStorageReady] = useState(false);

  useEffect(() => {
    let cancelled = false;

    if (!workspaceId) {
      setActiveConversationId(null);
      setStorageReady(false);
      return () => {
        cancelled = true;
      };
    }

    setStorageReady(false);
    void readActiveConversationId(workspaceId).then((conversationId) => {
      if (cancelled) {
        return;
      }

      setActiveConversationId(conversationId);
      setStorageReady(true);
    });

    return () => {
      cancelled = true;
    };
  }, [workspaceId]);

  const conversationQuery = useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      storageReady,
    queryFn: async () => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return getChatConversation(
        session.token,
        workspaceId,
        activeConversationId,
      );
    },
    queryKey: workspaceId
      ? chatKeys.conversation(workspaceId, activeConversationId)
      : ["workspace", "no-workspace", "chat"],
  });

  useEffect(() => {
    const conversationId = conversationQuery.data?.id;

    if (!workspaceId || !conversationId || activeConversationId === conversationId) {
      return;
    }

    setActiveConversationId(conversationId);
    void writeActiveConversationId(workspaceId, conversationId);
  }, [activeConversationId, conversationQuery.data?.id, workspaceId]);

  const selectConversation = useCallback(
    (conversationId: string) => {
      if (!workspaceId || conversationId === activeConversationId) {
        return;
      }

      setActiveConversationId(conversationId);
      void writeActiveConversationId(workspaceId, conversationId);
    },
    [activeConversationId, workspaceId],
  );

  return {
    ...conversationQuery,
    activeConversationId: activeConversationId ?? conversationQuery.data?.id ?? null,
    selectConversation,
  };
}

export function useChatHistory() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return getChatHistory(session.token, workspaceId);
    },
    queryKey: workspaceId
      ? chatKeys.history(workspaceId)
      : ["workspace", "no-workspace", "chat", "history"],
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

      queryClient.setQueriesData<ChatConversationRecord>(
        { queryKey: chatKeys.workspace(workspaceId) },
        (current) => {
          if (!current || current.id !== result.conversationId) {
            return current;
          }

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
        },
      );
      void queryClient.invalidateQueries({ queryKey: chatKeys.history(workspaceId) });
    },
  });
}
