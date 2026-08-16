import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { StatusBadge, type StatusBadgeProps } from "@/components/primitives/status-badge";
import type { EventStatus } from "@/features/events";

export type EventStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: StatusBadgeProps["size"];
  status: EventStatus;
  uppercase?: boolean;
};

export function EventStatusBadge({
  accessibilityLabel,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: EventStatusBadgeProps) {
  const { t } = useTranslation("common");

  return (
    <View accessibilityLabel={accessibilityLabel ?? t(`events.status.${status}`)}>
      <StatusBadge
        namespace="events"
        showDot={showDot}
        size={size}
        status={status}
        uppercase={uppercase}
      />
    </View>
  );
}
