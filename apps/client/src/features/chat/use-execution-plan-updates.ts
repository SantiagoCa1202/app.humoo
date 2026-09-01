import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { applyExecutionPlanSnapshot, chatKeys } from "@/features/chat/hooks";
import type { ChatConversationRecord } from "@/features/chat/types";
import { useRealtime } from "@/realtime";
import { useWorkspace } from "@/features/workspace";

export function useExecutionPlanUpdates(conversationId: string | null | undefined) {
  const { subscribeConversation } = useRealtime();
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
        (current) => applyExecutionPlanSnapshot(current, event.executionPlan!),
      );
    });
  }, [conversationId, queryClient, subscribeConversation, workspaceId]);
}
