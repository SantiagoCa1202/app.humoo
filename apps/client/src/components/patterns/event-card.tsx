import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AvatarGroup } from "@/components/primitives/avatar-group";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { EventStatusBadge } from "@/components/patterns/event-status-badge";
import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventNamedValue,
  getEventStaff,
  type EventDisplayRecord,
} from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  event: EventDisplayRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showStaff?: boolean;
  showStatus?: boolean;
  trailing?: React.ReactNode;
};

export function EventCard({
  accessibilityLabel,
  compact = false,
  disabled = false,
  event,
  onPress,
  selected = false,
  showStaff = true,
  showStatus = true,
  trailing,
}: EventCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const schedule = formatEventDateRange(event, i18n.language);
  const guests = formatEventGuestCount(event.guestCountExpected, i18n.language);
  const venue = getEventNamedValue(event.venue);
  const staff = getEventStaff(event);
  const showFooter = showStaff && staff.length > 0;
  const subtitleParts = compact
    ? [venue, guests ? `${guests} ${t("events.labels.guests")}` : null].filter(Boolean)
    : [schedule];

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("events.card.accessibilityLabel")}
      disabled={disabled}
      onPress={onPress}
      padding={compact ? "md" : "lg"}
      radius={compact ? "md" : "lg"}
      selected={selected}
      variant={compact ? "muted" : "elevated"}
    >
      <CardHeader
        subtitle={subtitleParts.join(" • ")}
        title={event.name}
        trailing={
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            {trailing}
            {showStatus ? (
              <EventStatusBadge size={compact ? "sm" : "md"} status={event.status} />
            ) : null}
          </View>
        }
      />
      <CardContent padding="none" topDivider>
        <View style={{ gap: theme.spacing[2] }}>
          {!compact ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {schedule}
            </Text>
          ) : null}
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
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
            {!compact && event.serviceType ? (
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
        </View>
      </CardContent>
      {showFooter ? (
        <CardFooter align="between" divider padding="none">
          <Text tone="secondary" variant="caption">
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
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
