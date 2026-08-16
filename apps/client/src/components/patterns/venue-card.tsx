import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  getEventContactName,
  getEventContactRole,
  getEventVenueAddress,
  getEventVenueContact,
  getEventVenueName,
  getEventVenueRoom,
  getEventVenueSummary,
  type EventNamedValue,
  type EventVenueValue,
} from "@/features/events";

export type VenueCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  onPress?: () => void | Promise<void>;
  trailing?: React.ReactNode;
  venue?: EventNamedValue | EventVenueValue | null;
};

export function VenueCard({
  accessibilityLabel,
  compact = false,
  onPress,
  trailing,
  venue,
}: VenueCardProps) {
  const { t } = useTranslation("common");
  const title = getEventVenueName(venue);
  const addressLines = getEventVenueAddress(venue);
  const contact = getEventVenueContact(venue);
  const metadata: EntityCardMetadataItem[] = [];
  const room = getEventVenueRoom(venue);
  const contactName = getEventContactName(contact);
  const contactRole = getEventContactRole(contact);
  const summary = getEventVenueSummary(venue);

  if (room) {
    metadata.push({
      label: t("events.related.venue.room"),
      value: room,
    });
  }

  if (!compact && addressLines[1]) {
    metadata.push({
      label: t("events.related.venue.location"),
      value: addressLines.slice(1).join(" • "),
    });
  }

  if (contactName) {
    metadata.push({
      label: t("events.related.contact.label"),
      value: contactRole ? `${contactName} • ${contactRole}` : contactName,
    });
  }

  if (!compact && summary) {
    metadata.push({
      label: t("events.related.notes.label"),
      value: summary,
    });
  }

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("events.related.venue.accessibilityLabel")}
      metadata={metadata}
      onPress={onPress}
      subtitle={addressLines[0] ?? undefined}
      title={title ?? t("events.related.venue.empty")}
      trailing={trailing}
      variant={compact ? "muted" : "default"}
    />
  );
}
