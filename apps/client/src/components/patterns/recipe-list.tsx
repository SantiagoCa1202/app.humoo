import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Skeleton } from "@/components/primitives/skeleton";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { RecipeListItem } from "@/components/patterns/recipe-list-item";
import type { RecipeDisplayRecord, RecipeVersionRecord } from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeListEntry = {
  currentVersion?: RecipeVersionRecord | null;
  recipe: RecipeDisplayRecord;
};

export type RecipeListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  empty?: React.ReactNode;
  error?: React.ReactNode;
  loading?: boolean;
  onEndReached?: () => void;
  onRecipePress?: (recipe: RecipeDisplayRecord, currentVersion?: RecipeVersionRecord | null) => void;
  onRefresh?: () => void;
  recipes: RecipeListEntry[] | RecipeDisplayRecord[];
  refreshing?: boolean;
  selectedRecipeId?: string | null;
};

function RecipeListSkeleton({ compact = false }: { compact?: boolean }) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[3] }}>
      {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
        <BaseCard key={`recipe-skeleton-${index}`} padding="md" radius="md" variant="muted">
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

function normalizeEntries(recipes: RecipeListProps["recipes"]): RecipeListEntry[] {
  return recipes.map((item) => ("recipe" in item ? item : { recipe: item }));
}

export function RecipeList({
  accessibilityLabel,
  compact = false,
  empty,
  error,
  loading = false,
  onEndReached,
  onRecipePress,
  onRefresh,
  recipes,
  refreshing = false,
  selectedRecipeId,
}: RecipeListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const entries = normalizeEntries(recipes);

  if (loading && entries.length === 0) {
    return <RecipeListSkeleton compact={compact} />;
  }

  if (error) {
    return (
      <ErrorState
        detail={typeof error === "boolean" ? undefined : error}
        title={t("recipes.error.title")}
      />
    );
  }

  if (entries.length === 0) {
    return empty ? (
      <>{empty}</>
    ) : (
      <EmptyState
        description={t("recipes.empty.description")}
        title={t("recipes.empty.title")}
      />
    );
  }

  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("recipes.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={entries}
      keyExtractor={(item) => item.recipe.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => (
        <RecipeListItem
          currentVersion={item.currentVersion}
          onPress={
            onRecipePress ? () => void onRecipePress(item.recipe, item.currentVersion) : undefined
          }
          recipe={item.recipe}
          selected={selectedRecipeId === item.recipe.id}
          showStatus
        />
      )}
    />
  );
}
