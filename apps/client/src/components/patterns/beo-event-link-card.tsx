import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { Text } from "@/components/primitives/text";
import {
  createEventEntityOptions,
  type BeoRecord,
  type DocumentRecord,
} from "@/features/documents";
import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventClientName,
  getEventVenueName,
  type EventDisplayRecord,
} from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

function resolveEventOptions(
  eventOptions: EntityPickerOption<string>[] | undefined,
  events: EventDisplayRecord[] | undefined,
  locale: string
) {
  if (eventOptions?.length) {
    return eventOptions;
  }

  if (events?.length) {
    return createEventEntityOptions(events, locale);
  }

  return [];
}

export type BEOEventLinkCardProps = {
  accessibilityLabel?: string;
  beo?: BeoRecord | null;
  compact?: boolean;
  disabled?: boolean;
  document?: DocumentRecord | null;
  editable?: boolean;
  event?: EventDisplayRecord | null;
  eventOptions?: EntityPickerOption<string>[];
  events?: EventDisplayRecord[];
  onCreateEvent?: () => void | Promise<void>;
  onLink?: (eventId: string) => void | Promise<void>;
  onUnlink?: () => void | Promise<void>;
};

export function BEOEventLinkCard({
  accessibilityLabel,
  beo,
  compact = false,
  disabled = false,
  document,
  editable = false,
  event,
  eventOptions,
  events,
  onCreateEvent,
  onLink,
  onUnlink,
}: BEOEventLinkCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const options = resolveEventOptions(eventOptions, events, i18n.language);
  const eventDate = event ? formatEventDateRange(event, i18n.language) : null;
  const eventVenue = event ? getEventVenueName(event.venue) : null;
  const eventClient = event ? getEventClientName(event.client) : null;
  const guestCount = event ? formatEventGuestCount(event.guestCountExpected, i18n.language) : null;

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("documents.eventLink.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="outlined"
    >
      <CardHeader
        subtitle={
          event
            ? t("documents.eventLink.linkedDescription")
            : t("documents.eventLink.unlinkedDescription")
        }
        title={t("documents.eventLink.title")}
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {event ? (
            <View style={{ gap: theme.spacing[2] }}>
              <Text variant="title">{event.name}</Text>
              {[eventDate, eventClient, eventVenue, guestCount]
                .filter(Boolean)
                .map((value, index) => (
                  <Text key={`beo-event-link-${index}`} tone="secondary" variant="bodySmall">
                    {value}
                  </Text>
                ))}
            </View>
          ) : (
            <Text tone="secondary" variant="bodySmall">
              {t("documents.eventLink.unlinked")}
            </Text>
          )}
          {editable && onLink && options.length ? (
            <EntityPicker
              accessibilityLabel={t("documents.eventLink.fields.event.accessibilityLabel")}
              disabled={disabled}
              entities={options}
              label={t("documents.eventLink.fields.event.label")}
              onChange={(eventId) => {
                void onLink(eventId);
              }}
              placeholder={t("documents.eventLink.fields.event.placeholder")}
              value={event?.id ?? beo?.eventId ?? undefined}
            />
          ) : null}
          {document?.name ? (
            <Text tone="muted" variant="caption">
              {t("documents.eventLink.documentReference", { name: document.name })}
            </Text>
          ) : null}
        </View>
      </CardContent>
      {(event && onUnlink) || onCreateEvent ? (
        <CardFooter align="right" divider>
          {event && onUnlink ? (
            <Button
              disabled={disabled}
              label={t("documents.actions.unlinkEvent")}
              onPress={onUnlink}
              size="sm"
              variant="ghost"
            />
          ) : null}
          {onCreateEvent ? (
            <Button
              disabled={disabled}
              label={t("documents.actions.createEvent")}
              onPress={onCreateEvent}
              size="sm"
              variant="secondary"
            />
          ) : null}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
