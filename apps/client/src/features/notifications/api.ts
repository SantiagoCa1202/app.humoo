import { apiRequest } from "@/api/client";
import type {
  NotificationPreferenceRecord,
  NotificationRecord,
  NotificationsPage,
} from "@/features/notifications/types";

type ApiNotification = {
  action_key?: string | null;
  action_payload?: Record<string, unknown> | null;
  body?: string | null;
  created_at?: string | null;
  entity_id?: string | null;
  entity_type?: string | null;
  event_key: string;
  id: string;
  payload?: Record<string, unknown> | null;
  priority: NotificationRecord["priority"];
  read_at?: string | null;
  title: string;
  type: NotificationRecord["type"];
  workspace_id: string;
};

type ApiNotificationsPage = {
  data: ApiNotification[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

type ApiPreference = {
  email?: boolean;
  enabled?: boolean;
  event_key: string;
  in_app?: boolean;
  minimum_priority?: NotificationPreferenceRecord["minimumPriority"];
  push?: boolean;
  supported_channels?: string[];
};

function mapNotification(value: ApiNotification): NotificationRecord {
  return {
    actionKey: value.action_key ?? null,
    actionPayload: value.action_payload ?? null,
    body: value.body ?? null,
    createdAt: value.created_at ?? null,
    entityId: value.entity_id ?? null,
    entityType: value.entity_type ?? null,
    eventKey: value.event_key,
    id: value.id,
    payload: value.payload ?? null,
    priority: value.priority,
    readAt: value.read_at ?? null,
    title: value.title,
    type: value.type,
    workspaceId: value.workspace_id,
  };
}

function mapPreference(value: ApiPreference): NotificationPreferenceRecord {
  return {
    email: value.email ?? false,
    enabled: value.enabled ?? true,
    eventKey: value.event_key,
    inApp: value.in_app ?? true,
    minimumPriority: value.minimum_priority ?? "all",
    push: value.push ?? false,
    supportedChannels: value.supported_channels ?? ["in_app"],
  };
}

export async function listNotifications(
  authToken: string,
  workspaceId: string,
  cursor?: string | null,
): Promise<NotificationsPage> {
  const response = await apiRequest<ApiNotificationsPage>("/notifications", {
    authToken,
    query: {
      cursor: cursor ?? undefined,
      per_page: 25,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapNotification),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getUnreadCount(
  authToken: string,
  workspaceId: string,
): Promise<number> {
  const response = await apiRequest<{ data: { count: number } }>(
    "/notifications/unread-count",
    { authToken, workspaceId },
  );

  return response.data.count;
}

export async function markNotificationRead(
  authToken: string,
  workspaceId: string,
  notificationId: string,
): Promise<NotificationRecord> {
  const response = await apiRequest<{ data: ApiNotification }>(
    `/notifications/${notificationId}/read`,
    { authToken, method: "PATCH", workspaceId },
  );

  return mapNotification(response.data);
}

export async function markAllNotificationsRead(
  authToken: string,
  workspaceId: string,
): Promise<void> {
  await apiRequest("/notifications/read-all", {
    authToken,
    method: "POST",
    workspaceId,
  });
}

export async function getNotificationPreferences(
  authToken: string,
  workspaceId: string,
): Promise<NotificationPreferenceRecord[]> {
  const response = await apiRequest<{ data: ApiPreference[] }>(
    "/notification-preferences",
    { authToken, workspaceId },
  );

  return response.data.map(mapPreference);
}

export async function updateNotificationPreference(
  authToken: string,
  workspaceId: string,
  eventKey: string,
  values: Pick<NotificationPreferenceRecord, "enabled" | "inApp" | "minimumPriority">,
): Promise<NotificationPreferenceRecord> {
  const response = await apiRequest<{ data: ApiPreference }>(
    `/notification-preferences/${eventKey}`,
    {
      authToken,
      body: JSON.stringify({
        enabled: values.enabled,
        in_app: values.inApp,
        minimum_priority: values.minimumPriority,
      }),
      method: "PATCH",
      workspaceId,
    },
  );

  return mapPreference(response.data);
}
