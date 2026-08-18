import * as Linking from "expo-linking";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { DocumentCard } from "@/components/patterns/document-card";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { Text } from "@/components/primitives/text";
import { buildBeoStructuredSections, type BeoStructuredSectionViewModel, type BeoVersionRecord, type DocumentRecord } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOViewerMode = "original" | "structured";
export type BEOViewerProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  document?: DocumentRecord | null;
  error?: React.ReactNode;
  loading?: boolean;
  mode?: BEOViewerMode;
  onFieldPress?: (fieldId: string) => void;
  onModeChange?: (mode: BEOViewerMode) => void;
  onOpenOriginal?: () => void | Promise<void>;
  originalContent?: React.ReactNode;
  selectedFieldId?: string | null;
  sourceUri?: string | null;
  structuredData?: Record<string, unknown> | null;
  structuredSections?: BeoStructuredSectionViewModel[];
  version?: BeoVersionRecord | null;
};

export function BEOViewer({
  accessibilityLabel,
  compact = false,
  document,
  error,
  loading = false,
  mode = "structured",
  onFieldPress,
  onModeChange,
  onOpenOriginal,
  originalContent,
  selectedFieldId,
  sourceUri,
  structuredData,
  structuredSections,
  version,
}: BEOViewerProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const handleOpenOriginal = async () => {
    if (onOpenOriginal) {
      await onOpenOriginal();
      return;
    }

    if (sourceUri) {
      await Linking.openURL(sourceUri);
    }
  };

  if (loading) return <BaseCard accessibilityLabel={t("documents.beoViewer.loading")} padding="lg"><SkeletonText lines={6} /></BaseCard>;
  if (error) return <ErrorState detail={typeof error === "boolean" ? undefined : error} title={t("documents.beoViewer.errorTitle")} />;
  const sections = structuredSections ?? buildBeoStructuredSections(structuredData ?? version?.snapshotJson);
  const canOpenOriginal = Boolean(onOpenOriginal || sourceUri);
  const canShowOriginal = Boolean(originalContent || document || canOpenOriginal);
  const canShowStructured = sections.length > 0;
  if (!canShowOriginal && !canShowStructured) return <EmptyState description={t("documents.beoViewer.emptyDescription")} title={t("documents.beoViewer.emptyTitle")} />;
  const resolvedMode = mode === "original" && canShowOriginal ? "original" : canShowStructured ? "structured" : "original";
  return (
    <View accessibilityLabel={accessibilityLabel ?? t("documents.beoViewer.accessibilityLabel")} style={{ gap: theme.spacing[3] }}>
      {canShowOriginal && canShowStructured ? <View style={{ flexDirection: "row", gap: theme.spacing[2] }}><Button label={t("documents.beoViewer.modes.original")} onPress={() => onModeChange?.("original")} size="sm" variant={resolvedMode === "original" ? "primary" : "ghost"} /><Button label={t("documents.beoViewer.modes.structured")} onPress={() => onModeChange?.("structured")} size="sm" variant={resolvedMode === "structured" ? "primary" : "ghost"} /></View> : null}
      {resolvedMode === "original" ? (
        originalContent ?? (
          document ? (
            <DocumentCard
              actions={
                canOpenOriginal ? (
                  <Button
                    label={t("documents.actions.openOriginal")}
                    onPress={handleOpenOriginal}
                    size="sm"
                    variant="secondary"
                  />
                ) : undefined
              }
              compact
              document={document}
              latestVersion={version}
              onPress={canOpenOriginal ? handleOpenOriginal : undefined}
            />
          ) : canOpenOriginal ? (
            <BaseCard padding={compact ? "md" : "lg"} variant="outlined">
              <CardHeader title={t("documents.beoViewer.originalTitle")} />
              <CardContent topDivider>
                <View style={{ gap: theme.spacing[3] }}>
                  <Text tone="secondary" variant="bodySmall">
                    {t("documents.beoViewer.originalDescription")}
                  </Text>
                  <Button
                    label={t("documents.actions.openOriginal")}
                    onPress={handleOpenOriginal}
                    size="sm"
                    variant="secondary"
                  />
                </View>
              </CardContent>
            </BaseCard>
          ) : null
        )
      ) : (
        <View style={{ gap: theme.spacing[compact ? 2 : 3] }}>
          {sections.map((section) => <BaseCard key={section.id} padding={compact ? "md" : "lg"} variant="outlined"><CardHeader title={section.title} /><CardContent topDivider><View style={{ gap: theme.spacing[3] }}>{section.fields.map((field) => <Pressable accessibilityRole={onFieldPress ? "button" : undefined} accessibilityState={{ selected: selectedFieldId === field.id }} disabled={!onFieldPress} key={field.id} onPress={onFieldPress ? () => onFieldPress(field.id) : undefined}><View style={{ gap: theme.spacing[1] }}><Text tone="muted" variant="caption">{field.label}</Text>{typeof field.value === "string" || typeof field.value === "number" ? <Text variant="bodySmall">{field.value}</Text> : field.value}</View></Pressable>)}</View></CardContent></BaseCard>)}
        </View>
      )}
    </View>
  );
}
