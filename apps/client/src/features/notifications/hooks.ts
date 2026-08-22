import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import {
  getNotificationPreferences,
  getUnreadCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  updateNotificationPreference,
} from "@/features/notifications/api";
import type { NotificationsPage } from "@/features/notifications/types";
import { useWorkspace } from "@/features/workspace";

export const notificationKeys = {
  list: (workspaceId: string) => ["workspace", workspaceId, "notifications"] as const,
  unreadCount: (workspaceId: string) => ["workspace", workspaceId, "notifications", "unread-count"] as const,
  preferences: (workspaceId: string) => ["workspace", workspaceId, "notification-preferences"] as const,
};

function getApiContext(token: string | null | undefined, workspaceId: string | null) {
  if (!token || !workspaceId) {
    throw new Error("Notification API context is unavailable.");
  }

  return { token, workspaceId };
}

export function useNotifications() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useInfiniteQuery<NotificationsPage>({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    getNextPageParam: (page) => page.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: ({ pageParam }) => {
      const context = getApiContext(session?.token, workspaceId);
      return listNotifications(context.token, context.workspaceId, pageParam as string | null);
    },
    queryKey: workspaceId
      ? notificationKeys.list(workspaceId)
      : ["workspace", "no-workspace", "notifications"],
  });
}

export function useNotificationUnreadCount() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    queryFn: () => {
      const context = getApiContext(session?.token, workspaceId);
      return getUnreadCount(context.token, context.workspaceId);
    },
    queryKey: workspaceId
      ? notificationKeys.unreadCount(workspaceId)
      : ["workspace", "no-workspace", "notifications", "unread-count"],
    refetchInterval: 60_000,
  });
}

export function useMarkNotificationRead() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: (notificationId: string) => {
      const context = getApiContext(session?.token, workspaceId);
      return markNotificationRead(context.token, context.workspaceId, notificationId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: notificationKeys.list(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: notificationKeys.unreadCount(workspaceId) }),
      ]);
    },
  });
}

export function useMarkAllNotificationsRead() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: () => {
      const context = getApiContext(session?.token, workspaceId);
      return markAllNotificationsRead(context.token, context.workspaceId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: notificationKeys.list(workspaceId) }),
        queryClient.invalidateQueries({ queryKey: notificationKeys.unreadCount(workspaceId) }),
      ]);
    },
  });
}

export function useNotificationPreferences() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    queryFn: () => {
      const context = getApiContext(session?.token, workspaceId);
      return getNotificationPreferences(context.token, context.workspaceId);
    },
    queryKey: workspaceId
      ? notificationKeys.preferences(workspaceId)
      : ["workspace", "no-workspace", "notification-preferences"],
  });
}

export function useUpdateNotificationPreference() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: (input: {
      eventKey: string;
      enabled: boolean;
      inApp: boolean;
      minimumPriority: "all" | "important" | "critical";
    }) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateNotificationPreference(context.token, context.workspaceId, input.eventKey, input);
    },
    onSuccess: async () => {
      if (workspaceId) {
        await queryClient.invalidateQueries({ queryKey: notificationKeys.preferences(workspaceId) });
      }
    },
  });
}
