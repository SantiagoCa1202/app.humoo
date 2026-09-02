import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { applyExecutionPlanSnapshot, chatKeys } from "@/features/chat/hooks";
import { getChatConversation } from "@/features/chat/api";
import type { ChatConversationRecord } from "@/features/chat/types";
import { useAuth } from "@/auth/useAuth";
import { useRealtime } from "@/realtime";
import { useWorkspace } from "@/features/workspace";

export function useExecutionPlanUpdates(conversationId: string | null | undefined) {
  const { subscribeConversation } = useRealtime();
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  useEffect(() => {
    if (!conversationId || !workspaceId) {
      return;
    }

    return subscribeConversation(conversationId, (event) => {
      if (event.type !== "execution_plan.updated" || !event.executionPlan) {
        return;
      }

      queryClient.setQueriesData<ChatConversationRecord>(
        { queryKey: chatKeys.workspace(workspaceId) },
        (current) => applyExecutionPlanSnapshot(current, event.executionPlan!, {
          messageId: event.messageId,
          occurredAt: event.occurredAt,
        }),
      );

      if (
        !["completed", "partial", "failed", "cancelled"].includes(
          typeof event.executionPlan.status === "string" ? event.executionPlan.status : "",
        ) ||
        !session?.token
      ) {
        return;
      }

      void getChatConversation(session.token, workspaceId, { conversationId })
        .then((freshConversation) => {
          queryClient.setQueryData(
            chatKeys.conversation(workspaceId, conversationId),
            freshConversation,
          );
        });
    });
  }, [conversationId, queryClient, session?.token, subscribeConversation, workspaceId]);
}
