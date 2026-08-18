import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { DocumentSourceChip } from "@/components/patterns/document-source-chip";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { formatDocumentDate, getBeoVersionLabel, type BeoVersionRecord } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOVersionCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  isCurrent?: boolean;
  onCompare?: (version: BeoVersionRecord) => void;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  version: BeoVersionRecord;
};

export function BEOVersionCard({ accessibilityLabel, actions, compact = false, isCurrent = false, onCompare, onPress, selected = false, version }: BEOVersionCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const title = getBeoVersionLabel(version, t);
  const metadata: EntityCardMetadataItem[] = [];
  const createdAt = formatDocumentDate(version.createdAt, i18n.language);
  const author = version.createdBy?.name?.trim();
  if (createdAt) metadata.push({ label: t("documents.labels.created"), value: createdAt });
  if (!compact && author) metadata.push({ label: t("documents.labels.createdBy"), value: author });
  if (!compact && version.changeSummary?.trim()) metadata.push({ label: t("documents.beoVersion.changeSummary"), value: version.changeSummary });
  const trailing = (
    <View
      style={{
        alignItems: "flex-end",
        gap: theme.spacing[2],
      }}
    >
      {isCurrent ? (
        <Badge label={t("documents.beoVersion.current")} size="sm" variant="primary" />
      ) : null}
      {actions}
      {onCompare ? (
        <Button
          label={t("documents.actions.compareVersion")}
          onPress={() => onCompare(version)}
          size="sm"
          variant="ghost"
        />
      ) : null}
    </View>
  );

  return (
    <EntityCard
      accessibilityLabel={
        accessibilityLabel ?? t("documents.beoVersion.accessibilityLabel", { version: title })
      }
      eyebrow={<DocumentSourceChip source={version.source} />}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      status={version.status as never}
      statusNamespace={version.status ? "beoVersions" : undefined}
      title={title}
      trailing={trailing}
    />
  );
}
