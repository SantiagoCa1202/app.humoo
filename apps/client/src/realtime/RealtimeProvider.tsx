import { AppState, type AppStateStatus } from "react-native";
import { createContext, useEffect, useMemo, useRef, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { useWorkspace } from "@/features/workspace";
import { RealtimeClient } from "@/realtime/RealtimeClient";
import type { RealtimeChange, RealtimeStatus } from "@/realtime/types";

type RealtimeContextValue = {
  status: RealtimeStatus;
};

export const RealtimeContext = createContext<RealtimeContextValue>({ status: "disabled" });

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const clientRef = useRef<RealtimeClient | null>(null);
  const [status, setStatus] = useState<RealtimeStatus>("disabled");
  const workspaceId = activeWorkspace?.id ?? null;

  if (!clientRef.current) {
    clientRef.current = new RealtimeClient();
  }

  useEffect(() => {
    const client = clientRef.current!;

    if (!session?.token || !workspaceId) {
      client.disconnect();
      setStatus("disabled");
      return;
    }

    client.subscribe(
      session.token,
      workspaceId,
      (change) => {
        if (change.workspaceId === workspaceId) {
          handleRealtimeChange(queryClient, change);
        }
      },
      setStatus,
    );

    return () => client.disconnect();
  }, [queryClient, session?.token, workspaceId]);

  useEffect(() => {
    const client = clientRef.current!;
    let previousState: AppStateStatus = AppState.currentState;

    const subscription = AppState.addEventListener("change", (nextState) => {
      if (nextState === "active" && previousState !== "active") {
        client.resume();
        if (workspaceId) {
          refetchAfterReconnect(queryClient, workspaceId);
        }
      }

      if (nextState !== "active" && previousState === "active") {
        client.pause();
      }

      previousState = nextState;
    });

    return () => subscription.remove();
  }, [queryClient, workspaceId]);

  const value = useMemo(() => ({ status }), [status]);

  return <RealtimeContext.Provider value={value}>{children}</RealtimeContext.Provider>;
}

function handleRealtimeChange(queryClient: ReturnType<typeof useQueryClient>, change: RealtimeChange): void {
  const workspaceKey = ["workspace", change.workspaceId] as const;

  if (change.type.startsWith("event.")) {
    invalidate(queryClient, [...workspaceKey, "events"]);
    invalidate(queryClient, [...workspaceKey, "command-center"]);
    return;
  }

  if (change.type.startsWith("prep.")) {
    invalidate(queryClient, [...workspaceKey, "prep"]);
    invalidate(queryClient, [...workspaceKey, "command-center"]);
    return;
  }

  if (change.type.startsWith("task.")) {
    invalidate(queryClient, [...workspaceKey, "tasks"]);
    invalidate(queryClient, [...workspaceKey, "my-tasks"]);
    invalidate(queryClient, [...workspaceKey, "command-center"]);
    return;
  }

  if (change.type.startsWith("beo.") || change.type.startsWith("document.")) {
    invalidate(queryClient, [...workspaceKey, "documents"]);
    invalidate(queryClient, [...workspaceKey, "command-center"]);
    return;
  }

  if (change.type === "notification.created") {
    invalidate(queryClient, [...workspaceKey, "notifications"]);
    return;
  }

  if (change.type.startsWith("team.")) {
    invalidate(queryClient, [...workspaceKey, "team-staff"]);
    invalidate(queryClient, [...workspaceKey, "command-center"]);
  }
}

function refetchAfterReconnect(queryClient: ReturnType<typeof useQueryClient>, workspaceId: string): void {
  for (const scope of ["events", "prep", "tasks", "documents", "notifications", "command-center", "team-staff"]) {
    invalidate(queryClient, ["workspace", workspaceId, scope]);
  }
}

function invalidate(queryClient: ReturnType<typeof useQueryClient>, queryKey: readonly string[]): void {
  void queryClient.invalidateQueries({ queryKey, refetchType: "active" });
}
