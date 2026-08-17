import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { StockMovementCard } from "@/components/patterns/stock-movement-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  groupInventoryMovementsByDate,
  type InventoryMovementRecord,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

type MovementRow =
  | { id: string; movement: InventoryMovementRecord; type: "item" }
  | { id: string; title: string; type: "header" };

export type StockMovementListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByDate?: boolean;
  loading?: boolean;
  movements: InventoryMovementRecord[];
  onEndReached?: () => void;
  onMovementPress?: (movement: InventoryMovementRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
};

function StockMovementListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 4 : 5 }).map((_, index) => (
        <BaseCard key={`stock-movement-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="32%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="26%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

function createRows(
  movements: InventoryMovementRecord[],
  groupByDate: boolean,
  locale?: string
): MovementRow[] {
  if (!groupByDate) {
    return movements.map((movement, index) => ({
      id: movement.id ?? `inventory-movement-${index}`,
      movement,
      type: "item",
    }));
  }

  return groupInventoryMovementsByDate(movements, locale).flatMap((group) => [
    {
      id: `inventory-movement-header-${group.dateKey}`,
      title: group.label,
      type: "header" as const,
    },
    ...group.items.map((movement, index) => ({
      id: movement.id ?? `${group.dateKey}-${index}`,
      movement,
      type: "item" as const,
    })),
  ]);
}

export function StockMovementList({
  accessibilityLabel,
  compact = false,
  error,
  groupByDate = false,
  loading = false,
  movements,
  onEndReached,
  onMovementPress,
  onRefresh,
  refreshing = false,
}: StockMovementListProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const rows = createRows(movements, groupByDate, i18n.language);

  if (loading && movements.length === 0) {
    return <StockMovementListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("inventory.movements.errorTitle")}
      />
    );
  }

  if (movements.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("inventory.movements.emptyDescription")}
        title={t("inventory.movements.emptyTitle")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("inventory.movements.accessibilityLabel")}
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
            <StockMovementCard
              compact={compact}
              movement={item.movement}
              onPress={
                onMovementPress ? () => void onMovementPress(item.movement) : undefined
              }
            />
            <Divider spacing="none" />
          </View>
        )
      }
    />
  );
}
