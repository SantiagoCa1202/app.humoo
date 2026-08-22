import { Feather } from "@expo/vector-icons";
import { useMemo } from "react";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
} from "@/features/notifications/hooks";
import { navigateFromNotification } from "@/features/notifications/navigation";
import type { NotificationRecord } from "@/features/notifications/types";
import { useAppTheme } from "@/theme/ThemeProvider";

function resolveNotificationText(
  value: string | null,
  t: (key: string, options?: Record<string, unknown>) => string,
  payload: Record<string, unknown> | null,
): string {
  if (!value) {
    return "";
  }

  return t(value, {
    defaultValue: value,
    prepItemTitle: payload?.prep_item_title,
    taskTitle: payload?.task_title,
  });
}

function formatNotificationDate(value: string | null, language: string): string {
  if (!value) {
    return "";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(language, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function NotificationItem({
  item,
  onPress,
  onRead,
}: {
  item: NotificationRecord;
  onPress: () => void;
  onRead: () => void;
}) {
  const { i18n, t } = useTranslation("app");
  const { theme } = useAppTheme();
  const title = resolveNotificationText(item.title, t, item.payload);
  const body = resolveNotificationText(item.body, t, item.payload);

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: !item.readAt }}
      onPress={onPress}
      style={({ pressed }) => ({
        opacity: pressed ? 0.78 : 1,
      })}
    >
      <Card
        variant={item.readAt ? "default" : "selected"}
        style={{
          borderLeftColor: item.readAt
            ? theme.colors.border.default
            : theme.colors.brand.primary,
          borderLeftWidth: 3,
          gap: theme.spacing[2],
        }}
      >
        <View style={{ alignItems: "flex-start", flexDirection: "row", gap: theme.spacing[3] }}>
          <Feather
            color={item.readAt ? theme.colors.text.secondary : theme.colors.brand.primary}
            name={item.readAt ? "mail" : "bell"}
            size={18}
          />
          <View style={{ flex: 1, gap: theme.spacing[1] }}>
            <AppText variant="bodyMedium">{title}</AppText>
            {body ? <AppText muted>{body}</AppText> : null}
            <AppText muted variant="caption">
              {formatNotificationDate(item.createdAt, i18n.language)}
            </AppText>
          </View>
          {!item.readAt ? (
            <Pressable
              accessibilityRole="button"
              onPress={(event) => {
                event.stopPropagation();
                onRead();
              }}
              style={{ padding: 4 }}
            >
              <AppText variant="caption" style={{ color: theme.colors.brand.primary }}>
                {t("notificationsMarkRead")}
              </AppText>
            </Pressable>
          ) : null}
        </View>
      </Card>
    </Pressable>
  );
}

export default function NotificationCenterScreen() {
  const { t } = useTranslation("app");
  const notificationsQuery = useNotifications();
  const markReadMutation = useMarkNotificationRead();
  const markAllReadMutation = useMarkAllNotificationsRead();
  const notifications = useMemo(
    () => notificationsQuery.data?.pages.flatMap((page) => page.data) ?? [],
    [notificationsQuery.data],
  );

  return (
    <AppShell title={t("notificationsTitle")} subtitle={t("notificationsSubtitle")}>
      <View style={{ gap: 16 }}>
        <SectionCard
          action={
            <AppButton
              disabled={notifications.length === 0 || markAllReadMutation.isPending}
              label={t("notificationsMarkAllRead")}
              loading={markAllReadMutation.isPending}
              onPress={() => markAllReadMutation.mutate()}
              variant="outline"
            />
          }
          description={t("notificationsDescription")}
          title={t("notificationsCenterTitle")}
        >
          {notificationsQuery.isLoading ? (
            <StateBlock title={t("notificationsLoading")} tone="loading" />
          ) : null}
          {notificationsQuery.error ? (
            <StateBlock
              actionLabel={t("notificationsRetry")}
              description={
                notificationsQuery.error instanceof Error
                  ? notificationsQuery.error.message
                  : undefined
              }
              onAction={() => void notificationsQuery.refetch()}
              title={t("notificationsError")}
              tone="error"
            />
          ) : null}
          {!notificationsQuery.isLoading && !notificationsQuery.error && notifications.length === 0 ? (
            <StateBlock
              description={t("notificationsEmptyDescription")}
              title={t("notificationsEmpty")}
              tone="empty"
            />
          ) : null}
          {notifications.map((item) => (
            <NotificationItem
              item={item}
              key={item.id}
              onPress={() => {
                if (!item.readAt) {
                  markReadMutation.mutate(item.id);
                }
                navigateFromNotification(item);
              }}
              onRead={() => markReadMutation.mutate(item.id)}
            />
          ))}
          {notificationsQuery.hasNextPage ? (
            <AppButton
              label={t("notificationsLoadMore")}
              loading={notificationsQuery.isFetchingNextPage}
              onPress={() => void notificationsQuery.fetchNextPage()}
              variant="secondary"
            />
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}
