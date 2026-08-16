import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  formatPrepDateTime,
  getPrepGenerationSourceLabel,
  getPrepVersionItemCount,
  getPrepVersionLabel,
  getPrepVersionProgress,
  type PrepDisplayRecord,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepListVersionCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  isCurrent?: boolean;
  onCompare?: () => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  prepList?: PrepDisplayRecord | null;
  progress?: PrepListProgressRecord | null;
  selected?: boolean;
  version: PrepListVersionRecord;
};

export function PrepListVersionCard({
  accessibilityLabel,
  actions,
  compact = false,
  isCurrent = false,
  onCompare,
  onPress,
  prepList,
  progress,
  selected = false,
  version,
}: PrepListVersionCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const createdAt = formatPrepDateTime(version.createdAt, i18n.language);
  const approvedAt = formatPrepDateTime(version.approvedAt, i18n.language);
  const resolvedProgress = progress ?? getPrepVersionProgress(version);
  const metadata: EntityCardMetadataItem[] = [
    createdAt
      ? {
          label: t("prep.version.fields.createdAt"),
          value: createdAt,
        }
      : null,
    approvedAt
      ? {
          label: t("prep.version.fields.approvedAt"),
          value: approvedAt,
        }
      : null,
    version.createdBy?.name?.trim()
      ? {
          label: t("prep.version.fields.createdBy"),
          value: version.createdBy.name.trim(),
        }
      : null,
    typeof version.guestCountSnapshot === "number"
      ? {
          label: t("prep.generation.labels.guestCount"),
          value: new Intl.NumberFormat(i18n.language).format(version.guestCountSnapshot),
        }
      : null,
    getPrepGenerationSourceLabel(version.source, t)
      ? {
          label: t("prep.version.fields.source"),
          value: getPrepGenerationSourceLabel(version.source, t),
        }
      : null,
    {
      label: t("prep.labels.items"),
      value: t("prep.metrics.items", { count: getPrepVersionItemCount(version) }),
    },
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("prep.version.cardAccessibilityLabel")}
      leading={
        version.createdBy?.name?.trim() ? (
          <Avatar
            name={version.createdBy.name}
            size={compact ? "sm" : "md"}
            source={version.createdBy.source}
          />
        ) : undefined
      }
      metadata={compact ? metadata.slice(0, 4) : metadata}
      onPress={onPress}
      selected={selected}
      status={version.status ?? undefined}
      statusNamespace="prepListVersions"
      subtitle={version.changeSummary?.trim() || prepList?.name?.trim() || undefined}
      title={getPrepVersionLabel(version, t) ?? t("prep.version.current")}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {actions}
          {isCurrent ? (
            <Badge label={t("prep.version.current")} size="sm" variant="primary" />
          ) : null}
          {selected ? (
            <Badge label={t("prep.version.selected")} size="sm" variant="neutral" />
          ) : null}
          {onCompare ? (
            <Button
              label={t("prep.version.compare")}
              onPress={onCompare}
              size="sm"
              variant="ghost"
            />
          ) : null}
        </View>
      }
      variant={compact ? "default" : "elevated"}
    />
  );
}
