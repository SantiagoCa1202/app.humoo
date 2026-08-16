import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { MenuListItem } from "@/components/patterns/menu-list-item";
import type { MenuDisplayRecord } from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  empty?: React.ReactNode;
  error?: React.ReactNode;
  loading?: boolean;
  menus: MenuDisplayRecord[];
  onEndReached?: () => void;
  onMenuPress?: (menu: MenuDisplayRecord) => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedMenuId?: string | null;
};

function MenuListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`menu-skeleton-${index}`} padding="md" radius="md" variant="muted">
          <View style={{ gap: theme.spacing[2] }}>
            <SkeletonText lines={2} />
            <SkeletonText gap={theme.spacing[1]} lines={1} />
            <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
              <Skeleton height={theme.spacing[6]} radius="full" width="30%" />
              <Skeleton height={theme.spacing[6]} radius="full" width="25%" />
            </View>
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

export function MenuList({
  accessibilityLabel,
  compact = false,
  empty,
  error,
  loading = false,
  menus,
  onEndReached,
  onMenuPress,
  onRefresh,
  refreshing = false,
  selectedMenuId,
}: MenuListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  if (loading && menus.length === 0) {
    return <MenuListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("menus.error.title")}
      />
    );
  }

  if (menus.length === 0) {
    return empty ? (
      <>{empty}</>
    ) : (
      <EmptyState
        description={t("menus.empty.description")}
        title={t("menus.empty.title")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("menus.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={menus}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <MenuListItem
          menu={item}
          onPress={onMenuPress ? () => void onMenuPress(item) : undefined}
          selected={selectedMenuId === item.id}
          showStatus
        />
      )}
    />
  );
}
