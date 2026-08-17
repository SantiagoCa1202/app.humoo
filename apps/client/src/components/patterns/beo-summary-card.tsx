import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import { formatDocumentDateTime, getBeoSummaryMetrics, getBeoVersionLabel, type BeoRecord, type BeoSummaryViewModel, type BeoVersionRecord } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOSummaryCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  beo: BeoRecord;
  compact?: boolean;
  summary?: BeoSummaryViewModel | null;
  version?: BeoVersionRecord | null;
};

export function BEOSummaryCard({ accessibilityLabel, actions, beo, compact = false, summary, version }: BEOSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const metrics: SummaryMetric[] = getBeoSummaryMetrics(summary).slice(0, compact ? 4 : 6).map(([key, value]) => ({
    label: t(`documents.beoSummary.labels.${key}`),
    value: key === "guests" && typeof value === "number" ? new Intl.NumberFormat(i18n.language).format(value) : String(value),
  }));
  const startsAt = formatDocumentDateTime(summary?.startsAt, i18n.language, summary?.timezone);
  if (startsAt) metrics.push({ label: t("documents.beoSummary.labels.schedule"), value: startsAt });
  const versionLabel = version ? getBeoVersionLabel(version, t) : typeof beo.currentVersion === "number" && beo.currentVersion > 0 ? t("documents.beoVersion.versionLabel", { version: beo.currentVersion }) : null;
  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("documents.beoSummary.accessibilityLabel")}
      metrics={metrics}
      subtitle={versionLabel}
      title={summary?.eventName ?? beo.event?.name ?? t("documents.beoSummary.title")}
      trailing={actions ?? (version?.status ? <Badge label={t(`documents.beoVersion.status.${version.status}`)} size="sm" variant="neutral" /> : undefined)}
    >
      {!compact && summary?.notes?.trim() ? <View style={{ gap: theme.spacing[1] }}><Text tone="muted" variant="overline">{t("documents.beoSummary.labels.notes")}</Text><Text numberOfLines={4} variant="bodySmall">{summary.notes}</Text></View> : null}
    </SummaryCard>
  );
}
