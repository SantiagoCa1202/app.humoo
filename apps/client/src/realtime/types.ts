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
  conversationId: string;
  delta?: string;
  label?: string;
  messageId: string;
  occurredAt: string | null;
  stage?: string;
  type: "activity" | "completed" | "failed" | "text.delta";
};

export type ChatStreamListener = (event: ChatStreamEvent) => void;
