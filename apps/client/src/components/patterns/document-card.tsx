import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { DocumentSourceChip } from "@/components/patterns/document-source-chip";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { Badge } from "@/components/primitives/badge";
import {
  formatDocumentDate,
  getDocumentEventLink,
  getDocumentProcessingStatus,
  getDocumentSource,
  getDocumentTitle,
  getDocumentTypeLabel,
  getFileTypeLabel,
  type BeoVersionRecord,
  type DocumentRecord,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type DocumentCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  disabled?: boolean;
  document: DocumentRecord;
  latestVersion?: BeoVersionRecord | null;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
};

export function DocumentCard({ accessibilityLabel, actions, compact = false, disabled = false, document, latestVersion, onPress, selected = false }: DocumentCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const title = getDocumentTitle(document) ?? t("documents.card.untitled");
  const source = getDocumentSource(document);
  const processingStatus = getDocumentProcessingStatus(document.processingStatus);
  const documentType = getDocumentTypeLabel(document.type, t);
  const fileType = getFileTypeLabel(document, t);
  const eventLink = getDocumentEventLink(document);
  const date = formatDocumentDate(document.createdAt, i18n.language);
  const uploader = document.uploadedBy?.name?.trim();
  const metadata: EntityCardMetadataItem[] = [];
  if (date) metadata.push({ label: t("documents.labels.created"), value: date });
  if (!compact && uploader) metadata.push({ label: t("documents.labels.uploadedBy"), value: uploader });
  if (!compact && eventLink?.entityId) metadata.push({ label: t("documents.labels.event"), value: eventLink.entityId });
  if (!compact && typeof latestVersion?.version === "number") metadata.push({ label: t("documents.labels.version"), value: latestVersion.version });

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("documents.card.accessibilityLabel", { name: title })}
      disabled={disabled}
      eyebrow={
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          <DocumentSourceChip source={source} />
          {documentType ? <Badge label={documentType} size="sm" variant="neutral" /> : null}
          {fileType ? <Badge label={fileType} outline size="sm" variant="neutral" /> : null}
        </View>
      }
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      status={processingStatus ?? undefined}
      statusNamespace={processingStatus ? "documents" : undefined}
      title={title}
      trailing={actions}
    />
  );
}
