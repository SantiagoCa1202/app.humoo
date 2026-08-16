import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { Avatar } from "@/components/primitives/avatar";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  getEventContactName,
  getEventContactOrganization,
  getEventContactRole,
  type EventContactValue,
} from "@/features/events";

export type ContactCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  contact?: EventContactValue | null;
  onCall?: (contact: EventContactValue) => void | Promise<void>;
  onEmail?: (contact: EventContactValue) => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  trailing?: React.ReactNode;
};

export function ContactCard({
  accessibilityLabel,
  compact = false,
  contact,
  onCall,
  onEmail,
  onPress,
  trailing,
}: ContactCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const title = getEventContactName(contact);
  const role = getEventContactRole(contact);
  const organization = getEventContactOrganization(contact);
  const metadata: EntityCardMetadataItem[] = [];

  if (contact?.email?.trim()) {
    metadata.push({
      label: t("events.related.contact.email"),
      value: contact.email.trim(),
    });
  }

  if (contact?.phone?.trim()) {
    metadata.push({
      label: t("events.related.contact.phone"),
      value: contact.phone.trim(),
    });
  }

  if (!compact && organization) {
    metadata.push({
      label: t("events.related.contact.organization"),
      value: organization,
    });
  }

  const actionRow =
    contact && (onEmail || onCall) ? (
      <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
        {onEmail && contact.email?.trim() ? (
          <Tooltip content={t("events.related.contact.actions.emailTooltip")}>
            <IconButton
              accessibilityHint={t("events.related.contact.actions.emailHint")}
              accessibilityLabel={t("events.related.contact.actions.email")}
              icon={<Text variant="bodySmall">@</Text>}
              onPress={() => onEmail(contact)}
              size="sm"
              variant="ghost"
            />
          </Tooltip>
        ) : null}
        {onCall && contact.phone?.trim() ? (
          <Tooltip content={t("events.related.contact.actions.callTooltip")}>
            <IconButton
              accessibilityHint={t("events.related.contact.actions.callHint")}
              accessibilityLabel={t("events.related.contact.actions.call")}
              icon={<Text variant="bodySmall">#</Text>}
              onPress={() => onCall(contact)}
              size="sm"
              variant="ghost"
            />
          </Tooltip>
        ) : null}
        {trailing}
      </View>
    ) : (
      trailing
    );

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("events.related.contact.accessibilityLabel")}
      leading={<Avatar name={title} size={compact ? "sm" : "md"} source={contact?.source} />}
      metadata={metadata}
      onPress={onPress}
      subtitle={role ?? organization ?? undefined}
      title={title ?? t("events.related.contact.empty")}
      trailing={actionRow}
      variant={compact ? "muted" : "default"}
    />
  );
}
