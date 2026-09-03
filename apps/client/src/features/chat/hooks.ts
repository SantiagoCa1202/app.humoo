import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useCallback, useEffect } from "react";

import { useAuth } from "@/auth/useAuth";
import {
  assistantResponseToMessage,
  createChatConversation,
  createChatClientMessageId,
  deleteChatConversation,
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
  active: (workspaceId: string) => [
    "workspace",
    workspaceId,
    "chat",
    "active",
  ] as const,
};

const CHAT_MESSAGE_PAGE_SIZE = 40;

function dedupeMessages(messages: ChatMessageRecord[]) {
  const messageMap = new Map<string, ChatMessageRecord>();

  messages.forEach((message) => {
    const key = message.clientMessageId
      ? `client:${message.clientMessageId}`
      : `id:${message.id}`;

    messageMap.set(key, message);
  });

  return Array.from(messageMap.values()).sort((left, right) =>
    compareMessages(left, right)
  );
}

function buildOptimisticUserMessage(
  input: SendChatMessageInput,
  conversationId: string,
): ChatMessageRecord {
  const clientMessageId = input.clientMessageId ?? createChatClientMessageId();

  return {
    blocks: [],
    clientMessageId,
    contentText: input.content.trim(),
    conversationId,
    createdAt: new Date().toISOString(),
    id: clientMessageId,
    senderType: "user",
    status: "pending",
    suggestions: [],
  };
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

function isNotFoundError(error: unknown) {
  return (
    typeof error === "object" &&
    error !== null &&
    "status" in error &&
    (error as { status?: number }).status === 404
  );
}

export function applyAssistantResponseToConversation(
  current: ChatConversationRecord | undefined,
  response: ChatAssistantResponseRecord,
  conversationId: string | null | undefined,
  lastMessageAt: string | null | undefined
): ChatConversationRecord {
  const assistantMessage = assistantResponseToMessage(response, lastMessageAt);
  const baseConversation = buildFallbackConversation(current, conversationId);
  const existingMessage = baseConversation.messages.find(
    (message) => message.id === assistantMessage.id,
  );

  return {
    ...baseConversation,
    id: conversationId ?? baseConversation.id,
    lastMessageAt: lastMessageAt ?? baseConversation.lastMessageAt ?? null,
    messages: dedupeMessages([
      ...baseConversation.messages,
      existingMessage
        ? preserveNewerExecutionPlanSnapshot(existingMessage, assistantMessage)
        : assistantMessage,
    ]),
  };
}

export function applyExecutionPlanSnapshot(
  current: ChatConversationRecord | undefined,
  plan: Record<string, unknown>,
  progressMessage?: { messageId: string; occurredAt?: string | null },
): ChatConversationRecord | undefined {
  if (!current || typeof plan.id !== "string") {
    return current;
  }

  let matchedPlan = false;
  const progressBlock = executionPlanProgressBlock(plan);
  const messages = current.messages.map((message) => ({
    ...message,
    blocks: message.blocks.map((block) => {
      if (block.type !== "component" || block.component !== "execution.plan") {
        return block;
      }

      const currentPlan = block.data?.execution_plan;
      if (!currentPlan || typeof currentPlan !== "object" || (currentPlan as { id?: unknown }).id !== plan.id) {
        return block;
      }

      matchedPlan = true;

      return progressBlock;
    }),
  }));

  if (matchedPlan || !progressMessage?.messageId) {
    return {
      ...current,
      messages,
    };
  }

  const createdAt = progressMessage.occurredAt ?? current.lastMessageAt ?? new Date().toISOString();
  const existingProgressMessage = messages.find(
    (message) => message.id === progressMessage.messageId,
  );

  return {
    ...current,
    lastMessageAt: progressMessage.occurredAt ?? current.lastMessageAt ?? null,
    messages: dedupeMessages([
      ...messages.filter((message) => message.id !== progressMessage.messageId),
      existingProgressMessage
        ? { ...existingProgressMessage, blocks: [...existingProgressMessage.blocks, progressBlock] }
        : {
            blocks: [progressBlock],
            conversationId: current.id,
            createdAt,
            id: progressMessage.messageId,
            senderType: "assistant",
            status: "completed",
            suggestions: [],
          },
    ]),
  };
}

function executionPlanProgressBlock(plan: Record<string, unknown>): ChatMessageRecord["blocks"][number] {
  const title = typeof plan.title === "string" && plan.title.trim()
    ? plan.title
    : "Execution workflow in progress";

  return {
    component: "execution.plan",
    data: {
      description: "The queue continues automatically. Completed steps are never repeated and blocked dependencies remain visible for review.",
      execution_plan: plan,
      status: typeof plan.status === "string" ? plan.status : "pending",
      title,
    },
    registryKey: "execution.plan@1",
    schemaVersion: 1,
    type: "component",
  };
}

function preserveNewerExecutionPlanSnapshot(
  current: ChatMessageRecord,
  incoming: ChatMessageRecord,
): ChatMessageRecord {
  const currentPlans = new Map<string, Record<string, unknown>>();

  current.blocks.forEach((block) => {
    const plan = executionPlanFromBlock(block);
    if (plan && typeof plan.id === "string") {
      currentPlans.set(plan.id, plan);
    }
  });

  return {
    ...incoming,
    blocks: incoming.blocks.map((block) => {
      const incomingPlan = executionPlanFromBlock(block);
      if (!incomingPlan || typeof incomingPlan.id !== "string") {
        return block;
      }

      const currentPlan = currentPlans.get(incomingPlan.id);
      if (!currentPlan || executionPlanStatusRank(currentPlan) <= executionPlanStatusRank(incomingPlan)) {
        return block;
      }

      return executionPlanProgressBlock(currentPlan);
    }),
  };
}

function executionPlanFromBlock(
  block: ChatMessageRecord["blocks"][number],
): Record<string, unknown> | null {
  if (block.type !== "component" || block.component !== "execution.plan") {
    return null;
  }

  const plan = block.data?.execution_plan;

  return plan && typeof plan === "object" ? plan as Record<string, unknown> : null;
}

function executionPlanStatusRank(plan: Record<string, unknown>): number {
  return matchExecutionPlanStatus(typeof plan.status === "string" ? plan.status : "");
}

function matchExecutionPlanStatus(status: string): number {
  switch (status) {
    case "completed":
    case "partial":
    case "failed":
    case "cancelled":
      return 3;
    case "running":
      return 2;
    case "queued":
    case "pending_confirmation":
      return 1;
    default:
      return 0;
  }
}

export function useChatSelection() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  const activeQuery = useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => (workspaceId ? readActiveConversationId(workspaceId) : null),
    queryKey: workspaceId
      ? chatKeys.active(workspaceId)
      : ["workspace", "no-workspace", "chat", "active"],
    staleTime: Infinity,
  });

  const selectConversation = useCallback(
    (conversationId: string) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(chatKeys.active(workspaceId), conversationId);
      void writeActiveConversationId(workspaceId, conversationId);
    },
    [queryClient, workspaceId],
  );

  return {
    activeConversationId: activeQuery.data ?? null,
    isReady: activeQuery.isSuccess,
    selectConversation,
  };
}

export function useChatConversation() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;
  const {
    activeConversationId,
    isReady,
    selectConversation,
  } = useChatSelection();

  const conversationQuery = useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      isReady,
    queryFn: async () => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      try {
        return await getChatConversation(
          session.token,
          workspaceId,
          {
            conversationId: activeConversationId,
            limit: CHAT_MESSAGE_PAGE_SIZE,
          },
        );
      } catch (error) {
        if (!activeConversationId || !isNotFoundError(error)) {
          throw error;
        }

        queryClient.setQueryData(chatKeys.active(workspaceId), null);
        await writeActiveConversationId(workspaceId, null);

        return getChatConversation(session.token, workspaceId, {
          limit: CHAT_MESSAGE_PAGE_SIZE,
        });
      }
    },
    queryKey: workspaceId
      ? chatKeys.conversation(workspaceId, activeConversationId)
      : ["workspace", "no-workspace", "chat"],
    refetchOnWindowFocus: false,
    staleTime: 30_000,
  });

  useEffect(() => {
    const conversationId = conversationQuery.data?.id;

    if (
      !workspaceId ||
      !conversationId ||
      activeConversationId === conversationId
    ) {
      return;
    }

    selectConversation(conversationId);
  }, [
    activeConversationId,
    conversationQuery.data?.id,
    selectConversation,
    workspaceId,
  ]);

  return {
    ...conversationQuery,
    activeConversationId:
      activeConversationId ?? conversationQuery.data?.id ?? null,
    selectConversation,
  };
}

export function useLoadOlderChatMessages() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (conversationId: string) => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      const current = queryClient.getQueryData<ChatConversationRecord>(
        chatKeys.conversation(workspaceId, conversationId),
      );
      const beforeMessageId = current?.messagePagination?.nextBeforeMessageId;

      if (!beforeMessageId) {
        return null;
      }

      return getChatConversation(session.token, workspaceId, {
        beforeMessageId,
        conversationId,
        limit: CHAT_MESSAGE_PAGE_SIZE,
      });
    },
    onSuccess: (page, conversationId) => {
      if (!workspaceId || !page) {
        return;
      }

      queryClient.setQueryData<ChatConversationRecord>(
        chatKeys.conversation(workspaceId, conversationId),
        (current) => {
          if (!current || current.id !== conversationId) {
            return current;
          }

          return {
            ...current,
            messagePagination: page.messagePagination,
            messages: dedupeMessages([...page.messages, ...current.messages]),
          };
        },
      );
    },
  });
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

export function useCreateChatConversation() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return createChatConversation(session.token, workspaceId);
    },
    onSuccess: async (conversation) => {
      if (!workspaceId) {
        return;
      }

      queryClient.setQueryData(
        chatKeys.conversation(workspaceId, conversation.id),
        conversation,
      );
      queryClient.setQueryData(chatKeys.active(workspaceId), conversation.id);
      await writeActiveConversationId(workspaceId, conversation.id);
      await queryClient.invalidateQueries({
        queryKey: chatKeys.history(workspaceId),
      });
    },
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
    onMutate: async (input) => {
      if (!workspaceId || !input.conversationId) {
        return null;
      }

      const conversationId = input.conversationId;
      const optimisticMessage = buildOptimisticUserMessage(input, conversationId);

      await queryClient.cancelQueries({
        queryKey: chatKeys.conversation(workspaceId, conversationId),
      });

      queryClient.setQueryData<ChatConversationRecord>(
        chatKeys.conversation(workspaceId, conversationId),
        (current) => {
          if (!current || current.id !== conversationId) {
            return current;
          }

          return {
            ...current,
            lastMessageAt: optimisticMessage.createdAt ?? current.lastMessageAt ?? null,
            messages: dedupeMessages([...current.messages, optimisticMessage]),
          };
        },
      );
    },
    onError: (_error, input) => {
      if (!workspaceId || !input.conversationId) {
        return;
      }

      queryClient.setQueryData<ChatConversationRecord>(
        chatKeys.conversation(workspaceId, input.conversationId),
        (current) => {
          if (!current || current.id !== input.conversationId) {
            return current;
          }

          return {
            ...current,
            messages: current.messages.map((message) =>
              message.clientMessageId === input.clientMessageId
                ? { ...message, status: "failed" }
                : message,
            ),
          };
        },
      );

      void queryClient.invalidateQueries({
        queryKey: chatKeys.conversation(workspaceId, input.conversationId),
      });
    },
    onSuccess: async (result, input) => {
      if (!workspaceId) {
        return;
      }

      const conversationId = result.conversationId ?? input.conversationId;

      queryClient.setQueryData<ChatConversationRecord>(
        chatKeys.conversation(workspaceId, conversationId),
        (current) => {
          if (!current || current.id !== conversationId) {
            return current;
          }

          const baseConversation = buildFallbackConversation(
            current,
            result.conversationId
          );

          return {
            ...baseConversation,
            id: conversationId ?? baseConversation.id,
            lastMessageAt:
              result.conversationLastMessageAt ?? baseConversation.lastMessageAt ?? null,
            messages: dedupeMessages([
              ...baseConversation.messages,
              result.userMessage,
              ...(result.assistantResponse
                ? [assistantResponseToMessage(
                    result.assistantResponse,
                    result.conversationLastMessageAt ?? result.userMessage.createdAt,
                  )]
                : []),
            ]),
          };
        },
      );
      await queryClient.invalidateQueries({
        queryKey: chatKeys.history(workspaceId),
      });
    },
  });
}

export function useDeleteChatConversation() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (conversationId: string) => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      await deleteChatConversation(session.token, workspaceId, conversationId);
    },
    onSuccess: async (_result, conversationId) => {
      if (!workspaceId) {
        return;
      }

      await queryClient.cancelQueries({
        exact: true,
        queryKey: chatKeys.conversation(workspaceId, conversationId),
      });
      queryClient.removeQueries({
        exact: true,
        queryKey: chatKeys.conversation(workspaceId, conversationId),
      });
      queryClient.setQueryData(chatKeys.active(workspaceId), null);
      await writeActiveConversationId(workspaceId, null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: chatKeys.history(workspaceId) }),
        queryClient.invalidateQueries({
          queryKey: chatKeys.conversation(workspaceId, null),
        }),
      ]);
    },
  });
}
