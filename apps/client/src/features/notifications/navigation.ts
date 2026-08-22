import { router } from "expo-router";

import type { NotificationRecord } from "@/features/notifications/types";

export function navigateFromNotification(notification: NotificationRecord): boolean {
  const entityId = notification.entityId;

  if (!entityId) {
    return false;
  }

  switch (notification.entityType) {
    case "task":
      router.push({ pathname: "/(app)/tasks/[taskId]", params: { taskId: entityId } });
      return true;
    case "event":
      router.push({ pathname: "/(app)/events/[eventId]", params: { eventId: entityId } });
      return true;
    case "document":
      router.push({ pathname: "/(app)/documents/[documentId]", params: { documentId: entityId } });
      return true;
    case "prep_list":
      router.push({ pathname: "/(app)/prep/[prepListId]", params: { prepListId: entityId } });
      return true;
    default:
      return false;
  }
}
