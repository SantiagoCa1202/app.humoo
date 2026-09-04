import { useEffect, useMemo } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";

import { coerceAiRunRecord, getChatAiRuns } from "@/features/chat/api";
import { chatKeys, mergeChatMessages } from "@/features/chat/hooks";
import type { AiRunCollectionRecord, AiRunRecord, ChatConversationRecord } from "@/features/chat/types";
import { useAuth } from "@/auth/useAuth";
import { useRealtime } from "@/realtime";
import { useWorkspace } from "@/features/workspace";

const ACTIVE_RUN_STATUSES = new Set(["queued", "running"]);
const TERMINAL_RUN_STATUSES = new Set(["completed", "failed", "cancelled"]);

export function useAiRunReconciliation(conversationId: string | null | undefined) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const { status: realtimeStatus, subscribeConversation } = useRealtime();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  const runsQuery = useQuery({
    enabled: Boolean(session?.token && workspaceId && conversationId),
    queryFn: () => {
      if (!session?.token || !workspaceId || !conversationId) {
        throw new Error("No active workspace chat session.");
      }

      return getChatAiRuns(session.token, workspaceId, conversationId);
    },
    queryKey: workspaceId && conversationId
      ? chatKeys.aiRuns(workspaceId, conversationId)
      : ["workspace", "no-workspace", "chat", "ai-runs"],
    refetchInterval: (query) =>
      query.state.data?.runs.some((run) => ACTIVE_RUN_STATUSES.has(run.status))
        ? 3_000
        : false,
    refetchOnReconnect: true,
    refetchOnWindowFocus: true,
    staleTime: 1_000,
  });

  useEffect(() => {
    if (!workspaceId || !conversationId || !runsQuery.data?.messages.length) {
      return;
    }

    queryClient.setQueryData<ChatConversationRecord>(
      chatKeys.conversation(workspaceId, conversationId),
      (current) => mergeChatMessages(current, runsQuery.data!.messages),
    );
  }, [conversationId, queryClient, runsQuery.data, workspaceId]);

  useEffect(() => {
    if (!conversationId || !workspaceId) {
      return;
    }

    return subscribeConversation(conversationId, (event) => {
      if (event.type !== "ai_run.updated" || !event.aiRun) {
        return;
      }
      const incomingRun = coerceAiRunRecord(event.aiRun);
      if (!incomingRun) {
        return;
      }

      queryClient.setQueryData<AiRunCollectionRecord>(
        chatKeys.aiRuns(workspaceId, conversationId),
        (current) => mergeRunSnapshot(current, incomingRun),
      );

      if (TERMINAL_RUN_STATUSES.has(incomingRun.status) || incomingRun.status.startsWith("waiting_")) {
        void runsQuery.refetch();
      }
    });
  }, [conversationId, queryClient, runsQuery.refetch, subscribeConversation, workspaceId]);

  useEffect(() => {
    if (realtimeStatus === "connected" && runsQuery.dataUpdatedAt > 0) {
      void runsQuery.refetch();
    }
  }, [realtimeStatus, runsQuery.dataUpdatedAt, runsQuery.refetch]);

  const activeRun = useMemo(
    () => runsQuery.data?.runs.find((run) => ACTIVE_RUN_STATUSES.has(run.status)) ?? null,
    [runsQuery.data?.runs],
  );

  return { activeRun, runsQuery };
}

function mergeRunSnapshot(
  current: AiRunCollectionRecord | undefined,
  incoming: AiRunRecord,
): AiRunCollectionRecord {
  const base = current ?? { messages: [], runs: [] };
  const existing = base.runs.find((run) => run.id === incoming.id);
  if (existing && existing.sequence >= incoming.sequence) {
    return base;
  }

  return {
    ...base,
    runs: [incoming, ...base.runs.filter((run) => run.id !== incoming.id)],
  };
}
