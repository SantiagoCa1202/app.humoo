import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  getEventClientName,
  getEventClientOrganization,
  getEventContactName,
  getEventContactOrganization,
  type EventClientValue,
  type EventNamedValue,
} from "@/features/events";

function isRichClientValue(
  client?: EventNamedValue | EventClientValue | null
): client is EventClientValue {
  return Boolean(
    client &&
      typeof client !== "string" &&
      ("company" in client ||
        "organization" in client ||
        "contact" in client ||
        "email" in client ||
        "phone" in client ||
        "metadata" in client ||
        "source" in client)
  );
}

export type ClientCardProps = {
  accessibilityLabel?: string;
  client?: EventNamedValue | EventClientValue | null;
  compact?: boolean;
  onPress?: () => void | Promise<void>;
  trailing?: React.ReactNode;
};

export function ClientCard({
  accessibilityLabel,
  client,
  compact = false,
  onPress,
  trailing,
}: ClientCardProps) {
  const { t } = useTranslation("common");
  const title = getEventClientName(client);
  const organization = getEventClientOrganization(client);
  const metadata: EntityCardMetadataItem[] = [];

  if (isRichClientValue(client)) {
    const primaryContact = getEventContactName(client.contact);
    const contactOrganization = getEventContactOrganization(client.contact);

    if (organization) {
      metadata.push({
        label: t("events.related.client.organization"),
        value: organization,
      });
    }

    if (primaryContact) {
      metadata.push({
        label: t("events.related.client.primaryContact"),
        value: contactOrganization ? `${primaryContact} • ${contactOrganization}` : primaryContact,
      });
    }

    if (client.email?.trim()) {
      metadata.push({
        label: t("events.related.contact.email"),
        value: client.email.trim(),
      });
    }

    if (!compact && client.phone?.trim()) {
      metadata.push({
        label: t("events.related.contact.phone"),
        value: client.phone.trim(),
      });
    }

    if (!compact && client.metadata?.trim()) {
      metadata.push({
        label: t("events.related.client.metadata"),
        value: client.metadata.trim(),
      });
    }
  }

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("events.related.client.accessibilityLabel")}
      leading={
        isRichClientValue(client) ? (
          <Avatar name={title} size={compact ? "sm" : "md"} source={client.source} />
        ) : undefined
      }
      metadata={metadata}
      onPress={onPress}
      subtitle={organization ?? undefined}
      title={title ?? t("events.related.client.empty")}
      trailing={trailing}
      variant={compact ? "muted" : "default"}
    />
  );
}
