export type RealtimeStatus =
  | "disabled"
  | "connecting"
  | "connected"
  | "disconnected"
  | "error"
  | "reconnecting";

export type RealtimeChange = {
  entityId: string;
  entityType: string;
  occurredAt: string | null;
  type: string;
  version: number | null;
  workspaceId: string;
};

export type RealtimeListener = (change: RealtimeChange) => void;

export type ChatStreamEvent = {
  aiRun?: unknown;
  conversationId: string;
  delta?: string;
  label?: string;
  messageId: string;
  occurredAt: string | null;
  executionPlan?: Record<string, unknown>;
  stage?: string;
  sequence?: number | null;
  type: "activity" | "ai_run.updated" | "completed" | "execution_plan.updated" | "failed" | "text.delta";
};

export type ChatStreamListener = (event: ChatStreamEvent) => void;
