import { useTranslation } from "react-i18next";
import { View } from "react-native";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { MenuStatusBadge } from "@/components/patterns/menu-status-badge";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  formatMenuEventSummary,
  formatMenuMetricCount,
  formatMenuSectionPreview,
  getMenuStatus,
  getMenuSummary,
  getMenuTagLabel,
  type MenuDisplayRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  menu: MenuDisplayRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showEvent?: boolean;
  showStatus?: boolean;
  trailing?: React.ReactNode;
};

export function MenuCard({
  accessibilityLabel,
  compact = false,
  disabled = false,
  menu,
  onPress,
  selected = false,
  showEvent = true,
  showStatus = true,
  trailing,
}: MenuCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const metadata: EntityCardMetadataItem[] = [];
  const eventSummary = showEvent ? formatMenuEventSummary(menu.event, i18n.language) : null;
  const status = getMenuStatus(menu);
  const sectionPreview = menu.sections
    ?.slice(0, compact ? 1 : 3)
    .map((section) => formatMenuSectionPreview(section, t))
    .filter(Boolean) as string[] | undefined;

  if (typeof menu.sectionCount === "number") {
    metadata.push({
      label: t("menus.labels.sections"),
      value: formatMenuMetricCount("sections", menu.sectionCount, t),
    });
  }

  if (typeof menu.itemCount === "number") {
    metadata.push({
      label: t("menus.labels.items"),
      value: formatMenuMetricCount("items", menu.itemCount, t),
    });
  }

  if (!compact && typeof menu.recipeCount === "number") {
    metadata.push({
      label: t("menus.labels.recipes"),
      value: formatMenuMetricCount("recipes", menu.recipeCount, t),
    });
  }

  if (!compact && typeof menu.guestCount === "number") {
    metadata.push({
      label: t("menus.labels.guests"),
      value: formatMenuMetricCount("guests", menu.guestCount, t),
    });
  }

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("menus.card.accessibilityLabel")}
      disabled={disabled}
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={getMenuSummary(menu) ?? eventSummary ?? undefined}
      title={menu.name}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {trailing}
          {showStatus && status ? <MenuStatusBadge size={compact ? "sm" : "md"} status={status} /> : null}
        </View>
      }
      variant={compact ? "muted" : "elevated"}
    >
      {showEvent && eventSummary ? (
        <Text selectable tone="secondary" variant="bodySmall">
          {eventSummary}
        </Text>
      ) : null}
      {sectionPreview?.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {sectionPreview.map((item) => (
            <Badge key={item} label={item} size="sm" variant="neutral" />
          ))}
        </View>
      ) : null}
      {!compact && menu.tags?.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {menu.tags.slice(0, 3).map((tag) => {
            const label = getMenuTagLabel(tag);
            return <Badge key={label} label={label} size="sm" variant="neutral" />;
          })}
        </View>
      ) : null}
    </EntityCard>
  );
}
