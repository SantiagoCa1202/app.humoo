import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { Divider } from "@/components/primitives/divider";
import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import { MenuStatusBadge } from "@/components/patterns/menu-status-badge";
import {
  formatMenuEventSummary,
  formatMenuSectionPreview,
  getMenuStatus,
  getMenuSummary,
  getMenuTagLabel,
  type MenuDisplayRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuDetailHeaderProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  menu: MenuDisplayRecord;
};

export function MenuDetailHeader({
  accessibilityLabel,
  actions,
  compact = false,
  menu,
}: MenuDetailHeaderProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getMenuStatus(menu);
  const eventSummary = formatMenuEventSummary(menu.event, i18n.language);
  const summary = getMenuSummary(menu);
  const sectionPreview = menu.sections
    ?.slice(0, compact ? 2 : 4)
    .map((section) => formatMenuSectionPreview(section, t))
    .filter(Boolean) as string[] | undefined;

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.detailHeader.accessibilityLabel")}
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
            eyebrow={eventSummary ?? undefined}
            level={compact ? "h3" : "h2"}
            subtitle={summary ?? undefined}
            title={menu.name}
          />
          {status ? <MenuStatusBadge size={compact ? "sm" : "md"} status={status} /> : null}
        </View>
        {actions ? <View>{actions}</View> : null}
      </View>
      <Divider spacing="none" />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
        {typeof menu.sectionCount === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("menus.labels.sections")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("menus.metrics.sections", { count: menu.sectionCount })}
            </Text>
          </View>
        ) : null}
        {typeof menu.itemCount === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("menus.labels.items")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("menus.metrics.items", { count: menu.itemCount })}
            </Text>
          </View>
        ) : null}
        {typeof menu.recipeCount === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("menus.labels.recipes")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("menus.metrics.recipes", { count: menu.recipeCount })}
            </Text>
          </View>
        ) : null}
      </View>
      {sectionPreview?.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {sectionPreview.map((item) => (
            <Badge key={item} label={item} size="sm" variant="neutral" />
          ))}
        </View>
      ) : null}
      {menu.tags?.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {menu.tags.map((tag) => {
            const label = getMenuTagLabel(tag);
            return <Badge key={label} label={label} size="sm" variant="neutral" />;
          })}
        </View>
      ) : null}
    </View>
  );
}
