import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { InventoryListItem } from "@/components/patterns/inventory-list-item";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  getInventoryLocationName,
  getInventoryStatus,
  groupInventoryItemsByLocation,
  groupInventoryItemsByStatus,
  type InventoryItemRecord,
} from "@/features/inventory";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type InventoryRow =
  | { id: string; item: InventoryItemRecord; type: "item" }
  | { id: string; title: string; type: "header" };

export type InventoryListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByLocation?: boolean;
  groupByStatus?: boolean;
  items: InventoryItemRecord[];
  loading?: boolean;
  onEndReached?: () => void;
  onItemPress?: (item: InventoryItemRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedItemId?: string | null;
};

function InventoryListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 4 : 5 }).map((_, index) => (
        <BaseCard key={`inventory-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="30%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="24%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

function createRows(
  items: InventoryItemRecord[],
  groupByStatus: boolean,
  groupByLocation: boolean,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  if (groupByStatus) {
    return groupInventoryItemsByStatus(items).flatMap((group) => [
      {
        id: group.id,
        title: t(getStatusTranslationKey(group.status, "inventory")),
        type: "header" as const,
      },
      ...group.items.map((item, index) => ({
        id: item.id ?? `${group.status}-${index}`,
        item,
        type: "item" as const,
      })),
    ]);
  }

  if (groupByLocation) {
    return groupInventoryItemsByLocation(items).flatMap((group, groupIndex) => [
      {
        id: group.id,
        title:
          getInventoryLocationName(group.location) ?? t("inventory.groups.unknownLocation"),
        type: "header" as const,
      },
      ...group.items.map((item, index) => ({
        id: item.id ?? `inventory-location-${groupIndex}-${index}`,
        item,
        type: "item" as const,
      })),
    ]);
  }

  return items.map((item, index) => ({
    id: item.id ?? `inventory-item-${index}`,
    item,
    type: "item" as const,
  }));
}

export function InventoryList({
  accessibilityLabel,
  compact = false,
  error,
  groupByLocation = false,
  groupByStatus = false,
  items,
  loading = false,
  onEndReached,
  onItemPress,
  onRefresh,
  refreshing = false,
  selectedItemId,
}: InventoryListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const rows = createRows(items, groupByStatus, groupByLocation, t);

  if (loading && items.length === 0) {
    return <InventoryListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("inventory.list.errorTitle")}
      />
    );
  }

  if (items.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("inventory.list.emptyDescription")}
        title={t("inventory.list.emptyTitle")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("inventory.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={rows}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) =>
        item.type === "header" ? (
          <Text tone="muted" variant="overline">
            {item.title}
          </Text>
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            <InventoryListItem
              item={item.item}
              onPress={onItemPress ? () => void onItemPress(item.item) : undefined}
              selected={selectedItemId === item.item.id}
              showLocation={!compact}
              showStatus
              status={getInventoryStatus(item.item, item.item.stock)}
            />
            <Divider spacing="none" />
          </View>
        )
      }
    />
  );
}
