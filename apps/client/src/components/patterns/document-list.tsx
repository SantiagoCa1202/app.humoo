import { FlatList, View } from "react-native";
import { useTranslation } from "react-i18next";

import { DocumentCard } from "@/components/patterns/document-card";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { BaseCard } from "@/components/primitives/base-card";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import type { DocumentRecord } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type DocumentListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  documents: DocumentRecord[];
  error?: React.ReactNode;
  loading?: boolean;
  onDocumentPress?: (document: DocumentRecord) => void;
  onEndReached?: () => void;
  onRefresh?: () => void;
  refreshing?: boolean;
  selectedDocumentId?: string | null;
};

function DocumentListSkeleton({ compact }: { compact: boolean }) {
  const { theme } = useAppTheme();
  return <View style={{ gap: theme.spacing[3] }}>{Array.from({ length: compact ? 3 : 4 }).map((_, index) => <BaseCard key={`document-skeleton-${index}`} padding="md" variant="muted"><SkeletonText lines={compact ? 2 : 3} /></BaseCard>)}</View>;
}

export function DocumentList({ accessibilityLabel, compact = false, documents, error, loading = false, onDocumentPress, onEndReached, onRefresh, refreshing = false, selectedDocumentId }: DocumentListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  if (loading && documents.length === 0) return <DocumentListSkeleton compact={compact} />;
  if (error) return <ErrorState detail={typeof error === "boolean" ? undefined : error} title={t("documents.list.errorTitle")} />;
  if (!documents.length) return <EmptyState description={t("documents.list.emptyDescription")} title={t("documents.list.emptyTitle")} />;
  return (
    <FlatList
      accessibilityLabel={accessibilityLabel ?? t("documents.list.accessibilityLabel")}
      contentContainerStyle={{ gap: theme.spacing[3] }}
      data={documents}
      keyExtractor={(item) => item.id}
      onEndReached={onEndReached}
      onRefresh={onRefresh}
      refreshing={refreshing}
      renderItem={({ item }) => <DocumentCard compact={compact} document={item} onPress={onDocumentPress ? () => onDocumentPress(item) : undefined} selected={selectedDocumentId === item.id} />}
    />
  );
}
