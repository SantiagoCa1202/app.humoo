import { FlatList, View, useWindowDimensions } from "react-native";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { InventorySummaryCard } from "@/components/patterns/inventory-summary-card";
import { StockLevelCard } from "@/components/patterns/stock-level-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Divider } from "@/components/primitives/divider";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import {
  buildInventoryLocationSummary,
  getInventoryLocationName,
  type InventoryItemRecord,
  type InventoryLocationReference,
} from "@/features/inventory";
import { useAppTheme } from "@/theme/ThemeProvider";

type InventoryByLocationSection = {
  id: string;
  items: InventoryItemRecord[];
  location: InventoryLocationReference | null;
};

function InventoryByLocationSkeleton() {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[4] }}>
      {Array.from({ length: 3 }).map((_, index) => (
        <BaseCard key={`inventory-location-skeleton-${index}`} padding="lg" variant="muted">
          <View style={{ gap: theme.spacing[3] }}>
            <SkeletonText lines={2} />
            <Skeleton height={theme.spacing[16]} width="100%" />
            <SkeletonText lines={3} />
          </View>
        </BaseCard>
      ))}
    </View>
  );
}

export type InventoryByLocationProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  error?: React.ReactNode;
  inventory: InventoryItemRecord[];
  loading?: boolean;
  locations?: InventoryLocationReference[];
  onItemPress?: (item: InventoryItemRecord) => void;
  onLocationPress?: (location: InventoryLocationReference | null) => void;
  selectedLocationId?: string | null;
};

export function InventoryByLocation({
  accessibilityLabel,
  compact = false,
  error,
  inventory,
  loading = false,
  locations = [],
  onItemPress,
  onLocationPress,
  selectedLocationId,
}: InventoryByLocationProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const cardBasis = width >= 1100 ? "48%" : "100%";
  const sections = useMemo(() => {
    const itemsByLocation = new Map<string, InventoryByLocationSection>();

    locations.forEach((location) => {
      const key = location.id ?? location.key ?? location.name ?? `location-${itemsByLocation.size}`;

      itemsByLocation.set(key, {
        id: key,
        items: [],
        location,
      });
    });

    inventory.forEach((item, index) => {
      const location = item.stock?.location ?? item.location ?? null;
      const key =
        location?.id ??
        location?.key ??
        location?.name ??
        `unknown-location`;
      const existing = itemsByLocation.get(key);

      if (existing) {
        existing.items.push(item);
        return;
      }

      itemsByLocation.set(key, {
        id: `${key}-${index}`,
        items: [item],
        location,
      });
    });

    return [...itemsByLocation.values()].filter((section) => section.items.length > 0);
  }, [inventory, locations]);

  if (loading && inventory.length === 0) {
    return <InventoryByLocationSkeleton />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("inventory.byLocation.errorTitle")}
      />
    );
  }

  if (sections.length === 0) {
    return (
      <EmptyState
        compact={compact}
        description={t("inventory.byLocation.emptyDescription")}
        title={t("inventory.byLocation.emptyTitle")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("inventory.byLocation.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[4] }}
      data={sections}
      keyExtractor={(item) => item.id}
      renderItem={({ item: section }) => {
        const sectionSummary = buildInventoryLocationSummary(section.items, section.location);
        const locationLabel =
          getInventoryLocationName(section.location) ?? t("inventory.groups.unknownLocation");

        return (
          <BaseCard
            onPress={
              onLocationPress ? () => void onLocationPress(section.location) : undefined
            }
            padding="lg"
            selected={
              Boolean(section.location?.id) && selectedLocationId === section.location?.id
            }
            variant="default"
          >
            <View style={{ gap: theme.spacing[4] }}>
              <View style={{ gap: theme.spacing[1] }}>
                <Text selectable variant="h4">
                  {locationLabel}
                </Text>
                <Text selectable tone="muted" variant="caption">
                  {t("inventory.byLocation.itemsCount", {
                    count: sectionSummary.itemCount,
                  })}
                </Text>
              </View>
              <InventorySummaryCard compact summary={sectionSummary.summary} />
              <Divider spacing="none" />
              <View
                style={{
                  flexDirection: "row",
                  flexWrap: "wrap",
                  gap: theme.spacing[3],
                }}
              >
                {section.items.map((inventoryItem) => (
                  <View
                    key={inventoryItem.id ?? `${section.id}-${inventoryItem.name}`}
                    style={{ flexBasis: cardBasis, flexGrow: 1, minWidth: 280 }}
                  >
                    <StockLevelCard
                      compact={compact}
                      item={inventoryItem}
                      location={section.location}
                      onPress={
                        onItemPress
                          ? () => void onItemPress(inventoryItem)
                          : undefined
                      }
                      selected={false}
                      stock={inventoryItem.stock}
                    />
                  </View>
                ))}
              </View>
            </View>
          </BaseCard>
        );
      }}
    />
  );
}
