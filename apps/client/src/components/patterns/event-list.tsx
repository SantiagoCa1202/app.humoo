import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { EventListItem } from "@/components/patterns/event-list-item";
import type { EventDisplayRecord } from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  empty?: React.ReactNode;
  error?: React.ReactNode;
  events: EventDisplayRecord[];
  loading?: boolean;
  onEndReached?: () => void;
  onEventPress?: (event: EventDisplayRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedEventId?: string | null;
};

function EventListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`event-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <SkeletonText gap={theme.spacing[1]} lines={2} />
            {!compact ? (
              <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
                <Skeleton height={theme.spacing[6]} radius="full" width="30%" />
                <Skeleton height={theme.spacing[6]} radius="full" width="25%" />
              </View>
            ) : null}
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

export function EventList({
  accessibilityLabel,
  compact = false,
  empty,
  error,
  events,
  loading = false,
  onEndReached,
  onEventPress,
  onRefresh,
  refreshing = false,
  selectedEventId,
}: EventListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  if (loading && events.length === 0) {
    return <EventListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("events.error.title")}
      />
    );
  }

  if (events.length === 0) {
    return empty ? (
      <>{empty}</>
    ) : (
      <EmptyState
        description={t("events.empty.description")}
        title={t("events.empty.title")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("events.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={events}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <EventListItem
          event={item}
          onPress={onEventPress ? () => void onEventPress(item) : undefined}
          selected={selectedEventId === item.id}
          showStatus
        />
      )}
    />
  );
}
