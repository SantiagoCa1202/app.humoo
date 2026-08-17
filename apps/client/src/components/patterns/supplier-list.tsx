import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { SupplierCard } from "@/components/patterns/supplier-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { type SupplierRecord } from "@/features/purchasing";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SupplierListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  loading?: boolean;
  onEndReached?: () => void;
  onRefresh?: () => void;
  onSupplierPress?: (supplier: SupplierRecord) => void;
  refreshing?: boolean;
  selectedSupplierId?: string | null;
  suppliers: SupplierRecord[];
};

function SupplierListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 4 : 5 }).map((_, index) => (
        <BaseCard key={`supplier-skeleton-${index}`} padding="lg" variant="muted">
          <View style={{ gap: theme.spacing[3] }}>
            <SkeletonText lines={2} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="26%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="34%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

export function SupplierList({
  accessibilityLabel,
  compact = false,
  error,
  loading = false,
  onEndReached,
  onRefresh,
  onSupplierPress,
  refreshing = false,
  selectedSupplierId,
  suppliers,
}: SupplierListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  if (loading && suppliers.length === 0) {
    return <SupplierListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("purchasing.suppliers.list.errorTitle")}
      />
    );
  }

  if (suppliers.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("purchasing.suppliers.list.emptyDescription")}
        title={t("purchasing.suppliers.list.emptyTitle")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("purchasing.suppliers.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={suppliers}
      keyExtractor={(item, index) => item.id ?? `supplier-${index}`}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <SupplierCard
          compact={compact}
          onPress={onSupplierPress ? () => void onSupplierPress(item) : undefined}
          selected={selectedSupplierId === item.id}
          supplier={item}
        />
      )}
    />
  );
}
