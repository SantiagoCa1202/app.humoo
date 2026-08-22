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
