import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { MenuItemRow } from "@/components/patterns/menu-item-row";
import { sortMenuItems, type MenuSectionRecord } from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuSectionProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  collapsible?: boolean;
  compact?: boolean;
  defaultExpanded?: boolean;
  onItemPress?: (item: MenuSectionRecord["items"][number]) => void;
  section: MenuSectionRecord;
};

export function MenuSection({
  accessibilityLabel,
  actions,
  collapsible = false,
  compact = false,
  defaultExpanded = true,
  onItemPress,
  section,
}: MenuSectionProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [expanded, setExpanded] = useState(defaultExpanded);
  const sortedItems = sortMenuItems(section.items);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("menus.section.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant={compact ? "muted" : "default"}
    >
      <CardHeader
        title={section.name?.trim() || t("menus.section.untitled")}
        trailing={
          <View style={{ alignItems: "center", flexDirection: "row", gap: theme.spacing[2] }}>
            <Badge
              label={t("menus.metrics.items", { count: sortedItems.length })}
              size="sm"
              variant="neutral"
            />
            {actions}
            {collapsible ? (
              <IconButton
                accessibilityHint={t(
                  expanded ? "menus.actions.collapseHint" : "menus.actions.expandHint"
                )}
                accessibilityLabel={t(
                  expanded ? "menus.actions.collapse" : "menus.actions.expand"
                )}
                icon={<Text variant="bodySmall">{expanded ? "-" : "+"}</Text>}
                onPress={() => setExpanded((value) => !value)}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        }
      />
      {!expanded && collapsible ? null : (
        <CardContent topDivider>
          {sortedItems.length === 0 ? (
            <Text tone="muted" variant="bodySmall">
              {t("menus.section.empty")}
            </Text>
          ) : (
            <View style={{ gap: theme.spacing[3] }}>
              {sortedItems.map((item, index) => (
                <View key={item.id ?? item.clientId ?? `menu-item-${index}`} style={{ gap: theme.spacing[3] }}>
                  <MenuItemRow
                    compact={compact}
                    index={index}
                    item={item}
                    onPress={onItemPress ? () => onItemPress(item) : undefined}
                  />
                  {index < sortedItems.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              ))}
            </View>
          )}
        </CardContent>
      )}
    </BaseCard>
  );
}
