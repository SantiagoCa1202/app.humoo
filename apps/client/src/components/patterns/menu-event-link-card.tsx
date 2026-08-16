import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { EventStatusBadge } from "@/components/patterns/event-status-badge";
import { Button } from "@/components/primitives/button";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  formatMenuEventSummary,
  getMenuEventVenueName,
  type MenuDisplayRecord,
  type MenuEventReference,
} from "@/features/menus";

export type MenuEventLinkCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  event?: MenuEventReference | null;
  menu: Pick<MenuDisplayRecord, "event" | "name">;
  onEventPress?: () => void | Promise<void>;
  onLink?: () => void | Promise<void>;
  onUnlink?: () => void | Promise<void>;
};

export function MenuEventLinkCard({
  accessibilityLabel,
  compact = false,
  disabled = false,
  event,
  menu,
  onEventPress,
  onLink,
  onUnlink,
}: MenuEventLinkCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedEvent = event ?? menu.event ?? null;
  const venueName = getMenuEventVenueName(resolvedEvent);
  const metadata: EntityCardMetadataItem[] = venueName
    ? [
        {
          label: t("menus.eventLink.venue"),
          value: venueName,
        },
      ]
    : [];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("menus.eventLink.accessibilityLabel")}
      disabled={disabled}
      eyebrow={t("menus.eventLink.eyebrow")}
      metadata={compact ? [] : metadata}
      onPress={resolvedEvent && onEventPress ? onEventPress : undefined}
      radius="lg"
      subtitle={
        resolvedEvent
          ? formatMenuEventSummary(resolvedEvent, i18n.language) ??
            t("menus.eventLink.linkedDescription", { menuName: menu.name })
          : t("menus.eventLink.emptyDescription", { menuName: menu.name })
      }
      title={resolvedEvent?.name?.trim() || t("menus.eventLink.emptyTitle")}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {resolvedEvent?.status ? <EventStatusBadge size="sm" status={resolvedEvent.status} /> : null}
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
            {onLink ? (
              <Button
                disabled={disabled}
                label={t(
                  resolvedEvent ? "menus.eventLink.actions.change" : "menus.eventLink.actions.link"
                )}
                onPress={onLink}
                size="sm"
                variant={resolvedEvent ? "secondary" : "primary"}
              />
            ) : null}
            {resolvedEvent && onUnlink ? (
              <Button
                disabled={disabled}
                label={t("menus.eventLink.actions.unlink")}
                onPress={onUnlink}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </View>
      }
      variant={resolvedEvent ? "default" : "muted"}
    />
  );
}
