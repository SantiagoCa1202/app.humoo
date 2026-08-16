import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { PrepItem } from "@/components/patterns/prep-item";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import { sortPrepItems, type PrepItemRecord, type PrepTaskStatus } from "@/features/prep";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type PrepItemRow =
  | { id: string; type: "header"; status: PrepTaskStatus }
  | { id: string; item: PrepItemRecord; type: "item" };

export type PrepItemListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByStatus?: boolean;
  items: PrepItemRecord[];
  loading?: boolean;
  onEndReached?: () => void;
  onItemPress?: (item: PrepItemRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedItemId?: string | null;
};

const PREP_STATUS_ORDER: PrepTaskStatus[] = [
  "todo",
  "in_progress",
  "blocked",
  "done",
  "skipped",
];

function PrepItemListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`prep-item-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="28%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="24%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

function createRows(items: PrepItemRecord[], groupByStatus: boolean): PrepItemRow[] {
  const orderedItems = sortPrepItems(items);

  if (!groupByStatus) {
    return orderedItems.map((item) => ({
      id: item.id ?? item.clientId ?? item.title,
      item,
      type: "item",
    }));
  }

  return PREP_STATUS_ORDER.flatMap((status) => {
    const matchingItems = orderedItems.filter((item) => (item.status ?? "todo") === status);

    if (!matchingItems.length) {
      return [];
    }

    return [
      {
        id: `prep-status-${status}`,
        status,
        type: "header" as const,
      },
      ...matchingItems.map((item) => ({
        id: item.id ?? item.clientId ?? item.title,
        item,
        type: "item" as const,
      })),
    ];
  });
}

export function PrepItemList({
  accessibilityLabel,
  compact = false,
  error,
  groupByStatus = false,
  items,
  loading = false,
  onEndReached,
  onItemPress,
  onRefresh,
  refreshing = false,
  selectedItemId,
}: PrepItemListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const rows = createRows(items, groupByStatus);

  if (loading && items.length === 0) {
    return <PrepItemListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("prep.items.errorTitle")}
      />
    );
  }

  if (items.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("prep.items.emptyDescription")}
        title={t("prep.items.emptyTitle")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("prep.items.listAccessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={rows}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) =>
        item.type === "header" ? (
          <Text tone="muted" variant="overline">
            {t(getStatusTranslationKey(item.status, "prepTasks"))}
          </Text>
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            <PrepItem
              compact={compact}
              item={item.item}
              onPress={onItemPress ? () => void onItemPress(item.item) : undefined}
              selected={selectedItemId === item.item.id}
              showActions={false}
            />
            <Divider spacing="none" />
          </View>
        )
      }
    />
  );
}
