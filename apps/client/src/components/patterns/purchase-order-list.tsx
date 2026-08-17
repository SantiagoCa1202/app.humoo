import { SectionList, View } from "react-native";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { PurchaseOrderCard } from "@/components/patterns/purchase-order-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  groupPurchaseOrdersByStatus,
  type PurchaseOrderRecord,
  type PurchaseOrderStatus,
} from "@/features/purchasing";
import { getStatusTranslationKey } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type PurchaseOrderSection = {
  data: PurchaseOrderRecord[];
  key: string;
  status?: PurchaseOrderStatus;
  title?: string;
};

function PurchaseOrderListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 4 : 5 }).map((_, index) => (
        <BaseCard key={`purchase-order-skeleton-${index}`} padding="lg" variant="muted">
          <View style={{ gap: theme.spacing[3] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="24%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="30%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

export type PurchaseOrderListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  groupByStatus?: boolean;
  loading?: boolean;
  onEndReached?: () => void;
  onPurchaseOrderPress?: (purchaseOrder: PurchaseOrderRecord) => void;
  onRefresh?: () => void;
  purchaseOrders: PurchaseOrderRecord[];
  refreshing?: boolean;
  selectedPurchaseOrderId?: string | null;
};

export function PurchaseOrderList({
  accessibilityLabel,
  compact = false,
  error,
  groupByStatus = false,
  loading = false,
  onEndReached,
  onPurchaseOrderPress,
  onRefresh,
  purchaseOrders,
  refreshing = false,
  selectedPurchaseOrderId,
}: PurchaseOrderListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const sections = useMemo<PurchaseOrderSection[]>(() => {
    if (!groupByStatus) {
      return [
        {
          data: purchaseOrders,
          key: "all",
        },
      ];
    }

    return groupPurchaseOrdersByStatus(purchaseOrders).map((group) => ({
      data: group.purchaseOrders,
      key: group.id,
      status: group.status,
      title: t(getStatusTranslationKey(group.status, "purchaseOrders")),
    }));
  }, [groupByStatus, purchaseOrders, t]);

  if (loading && purchaseOrders.length === 0) {
    return <PurchaseOrderListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("purchasing.purchaseOrders.list.errorTitle")}
      />
    );
  }

  if (purchaseOrders.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("purchasing.purchaseOrders.list.emptyDescription")}
        title={t("purchasing.purchaseOrders.list.emptyTitle")}
      />
    );
  }

  return (
    <SectionList
      accessibilityLabel={
        accessibilityLabel ?? t("purchasing.purchaseOrders.list.accessibilityLabel")
      }
      contentContainerStyle={{ gap: theme.spacing[3] }}
      keyExtractor={(item, index) => item.id ?? `purchase-order-${index}`}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <View style={{ gap: theme.spacing[3] }}>
          <PurchaseOrderCard
            compact={compact}
            onPress={
              onPurchaseOrderPress ? () => void onPurchaseOrderPress(item) : undefined
            }
            purchaseOrder={item}
            selected={selectedPurchaseOrderId === item.id}
          />
          <Divider spacing="none" />
        </View>
      )}
      renderSectionHeader={({ section }) =>
        groupByStatus && section.title ? (
          <View style={{ marginBottom: theme.spacing[2] }}>
            <Text tone="muted" variant="overline">
              {section.title}
            </Text>
          </View>
        ) : null
      }
      sections={sections}
      stickySectionHeadersEnabled={false}
    />
  );
}
