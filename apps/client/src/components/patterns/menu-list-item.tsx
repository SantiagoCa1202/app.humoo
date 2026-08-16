import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import { MenuStatusBadge } from "@/components/patterns/menu-status-badge";
import {
  formatMenuEventSummary,
  formatMenuMetricCount,
  getMenuStatus,
  type MenuDisplayRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuListItemProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  menu: MenuDisplayRecord;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  showStatus?: boolean;
};

export function MenuListItem({
  accessibilityLabel,
  disabled = false,
  menu,
  onPress,
  selected = false,
  showStatus = true,
}: MenuListItemProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getMenuStatus(menu);
  const detailParts = [
    typeof menu.sectionCount === "number"
      ? formatMenuMetricCount("sections", menu.sectionCount, t)
      : null,
    typeof menu.itemCount === "number"
      ? formatMenuMetricCount("items", menu.itemCount, t)
      : null,
    formatMenuEventSummary(menu.event, i18n.language),
  ].filter(Boolean);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("menus.listItem.accessibilityLabel")}
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
            {menu.name}
          </Text>
          {detailParts.length ? (
            <Text selectable tone="muted" variant="caption">
              {detailParts.join(" • ")}
            </Text>
          ) : null}
        </View>
        {showStatus && status ? <MenuStatusBadge size="sm" status={status} /> : null}
      </View>
    </BaseCard>
  );
}
