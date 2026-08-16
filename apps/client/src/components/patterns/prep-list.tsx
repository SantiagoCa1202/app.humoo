import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { PrepListRow } from "@/components/patterns/prep-list-row";
import type {
  PrepDisplayRecord,
  PrepListProgressRecord,
  PrepListVersionRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepListEntry = {
  currentVersion?: PrepListVersionRecord | null;
  prepList: PrepDisplayRecord;
  progress?: PrepListProgressRecord | null;
};

export type PrepListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  empty?: React.ReactNode;
  error?: React.ReactNode;
  loading?: boolean;
  onEndReached?: () => void;
  onPrepListPress?: (
    prepList: PrepDisplayRecord,
    currentVersion?: PrepListVersionRecord | null,
    progress?: PrepListProgressRecord | null
  ) => void;
  onRefresh?: () => void;
  prepLists: PrepListEntry[] | PrepDisplayRecord[];
  refreshing?: boolean;
  selectedPrepListId?: string | null;
};

function PrepListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`prep-list-skeleton-${index}`} padding="md" radius="md" variant="muted">
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

function normalizeEntries(prepLists: PrepListProps["prepLists"]): PrepListEntry[] {
  return prepLists.map((item) => ("prepList" in item ? item : { prepList: item }));
}

export function PrepList({
  accessibilityLabel,
  compact = false,
  empty,
  error,
  loading = false,
  onEndReached,
  onPrepListPress,
  onRefresh,
  prepLists,
  refreshing = false,
  selectedPrepListId,
}: PrepListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const entries = normalizeEntries(prepLists);

  if (loading && entries.length === 0) {
    return <PrepListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("prep.error.title")}
      />
    );
  }

  if (entries.length === 0) {
    return empty ? (
      <>{empty}</>
    ) : (
      <EmptyState
        description={t("prep.empty.description")}
        title={t("prep.empty.title")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("prep.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={entries}
      keyExtractor={(item) => item.prepList.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <PrepListRow
          currentVersion={item.currentVersion}
          onPress={
            onPrepListPress
              ? () => void onPrepListPress(item.prepList, item.currentVersion, item.progress)
              : undefined
          }
          prepList={item.prepList}
          progress={item.progress}
          selected={selectedPrepListId === item.prepList.id}
          showStatus
        />
      )}
    />
  );
}
