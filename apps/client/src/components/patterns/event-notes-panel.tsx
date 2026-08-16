import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useTranslation } from "react-i18next";

export type EventNotesPanelProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  emptyText?: string;
  notes?: string | null;
  title?: React.ReactNode;
};

export function EventNotesPanel({
  accessibilityLabel,
  actions,
  compact = false,
  emptyText,
  notes,
  title,
}: EventNotesPanelProps) {
  const { t } = useTranslation("common");
  const hasNotes = Boolean(notes?.trim());

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("events.related.notes.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant={compact ? "muted" : "default"}
    >
      <CardHeader title={title ?? t("events.related.notes.label")} trailing={actions} />
      <CardContent topDivider>
        {hasNotes ? (
          <Text selectable variant="bodySmall">
            {notes?.trim()}
          </Text>
        ) : (
          <Text tone="muted" variant="bodySmall">
            {emptyText ?? t("events.related.notes.empty")}
          </Text>
        )}
      </CardContent>
    </BaseCard>
  );
}
