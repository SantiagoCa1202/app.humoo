export type NotificationPriority = "low" | "normal" | "high" | "critical";
export type NotificationType = "info" | "success" | "warning" | "error" | "action_required";

export type NotificationRecord = {
  actionKey: string | null;
  actionPayload: Record<string, unknown> | null;
  body: string | null;
  createdAt: string | null;
  entityId: string | null;
  entityType: string | null;
  eventKey: string;
  id: string;
  payload: Record<string, unknown> | null;
  priority: NotificationPriority;
  readAt: string | null;
  title: string;
  type: NotificationType;
  workspaceId: string;
};

export type NotificationsPage = {
  data: NotificationRecord[];
  nextCursor: string | null;
  nextPageUrl: string | null;
  path: string;
  perPage: number;
  prevCursor: string | null;
  prevPageUrl: string | null;
};

export type NotificationPreferenceRecord = {
  email: boolean;
  enabled: boolean;
  eventKey: string;
  inApp: boolean;
  minimumPriority: "all" | "important" | "critical";
  push: boolean;
  supportedChannels: string[];
};
