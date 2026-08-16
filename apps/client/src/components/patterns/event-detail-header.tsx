import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Badge } from "@/components/primitives/badge";
import { Divider } from "@/components/primitives/divider";
import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import { EventStatusBadge } from "@/components/patterns/event-status-badge";
import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventNamedValue,
  getEventStaff,
  getEventTagLabel,
  type EventDisplayRecord,
} from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventDetailHeaderProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  event: EventDisplayRecord;
};

export function EventDetailHeader({
  accessibilityLabel,
  actions,
  compact = false,
  event,
}: EventDetailHeaderProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const schedule = formatEventDateRange(event, i18n.language);
  const guests = formatEventGuestCount(event.guestCountExpected, i18n.language);
  const venue = getEventNamedValue(event.venue);
  const staff = getEventStaff(event);
  const tags = event.tags?.map(getEventTagLabel).filter(Boolean) ?? [];

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("events.detailHeader.accessibilityLabel")}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4], width: "100%" }}
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[2] }}>
          <Heading
            eyebrow={event.eventGroup ?? undefined}
            level={compact ? "h3" : "h2"}
            subtitle={schedule}
            title={event.name}
          />
          <EventStatusBadge size={compact ? "sm" : "md"} status={event.status} />
        </View>
        {actions ? <View>{actions}</View> : null}
      </View>
      <Divider spacing="none" />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
        {venue ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("events.labels.venue")}
            </Text>
            <Text selectable variant="bodySmall">
              {venue}
            </Text>
          </View>
        ) : null}
        {guests ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("events.labels.guests")}
            </Text>
            <Text selectable variant="bodySmall">
              {guests}
            </Text>
          </View>
        ) : null}
        {event.serviceType ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("events.labels.serviceType")}
            </Text>
            <Text selectable variant="bodySmall">
              {event.serviceType}
            </Text>
          </View>
        ) : null}
      </View>
      {staff.length ? (
        <View style={{ gap: theme.spacing[2] }}>
          <Text tone="muted" variant="caption">
            {t("events.labels.staff")}
          </Text>
          <AvatarGroup
            size="sm"
            users={staff.map((member) => ({
              name: member.name,
              status: member.presence,
              source: member.source,
              variant: member.variant ?? "neutral",
            }))}
          />
        </View>
      ) : null}
      {tags.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {tags.map((tag) => (
            <Badge key={tag} label={tag} size="sm" variant="neutral" />
          ))}
        </View>
      ) : null}
    </View>
  );
}
