import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import { EventStatusBadge } from "@/components/patterns/event-status-badge";
import {
  formatEventDateRange,
  formatEventGuestCount,
  getEventNamedValue,
  type EventDisplayRecord,
} from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EventListItemProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  event: EventDisplayRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showStatus?: boolean;
};

export function EventListItem({
  accessibilityLabel,
  disabled = false,
  event,
  onPress,
  selected = false,
  showStatus = true,
}: EventListItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const schedule = formatEventDateRange(event, i18n.language);
  const guests = formatEventGuestCount(event.guestCountExpected, i18n.language);
  const venue = getEventNamedValue(event.venue);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("events.listItem.accessibilityLabel")}
      disabled={disabled}
      onPress={onPress}
      padding="md"
      radius="md"
      selected={selected}
      variant="muted"
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          <Text numberOfLines={2} selectable variant="title">
            {event.name}
          </Text>
          <Text selectable tone="secondary" variant="bodySmall">
            {schedule}
          </Text>
          {venue || guests ? (
            <Text selectable tone="muted" variant="caption">
              {[venue, guests ? `${guests} ${t("events.labels.guests")}` : null]
                .filter(Boolean)
                .join(" • ")}
            </Text>
          ) : null}
        </View>
        {showStatus ? <EventStatusBadge size="sm" status={event.status} /> : null}
      </View>
    </BaseCard>
  );
}
