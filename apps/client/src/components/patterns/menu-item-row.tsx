import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import type { MenuItemRecord } from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuItemRowProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  dragHandle?: React.ReactNode;
  editable?: boolean;
  index?: number;
  item: MenuItemRecord;
  onEdit?: () => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  onRemove?: () => void | Promise<void>;
  selected?: boolean;
};

export function MenuItemRow({
  accessibilityLabel,
  compact = false,
  disabled = false,
  dragHandle,
  editable = false,
  index,
  item,
  onEdit,
  onPress,
  onRemove,
  selected = false,
}: MenuItemRowProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const hasActions = editable && (onEdit || onRemove);
  const recipeName = item.recipe?.name?.trim();
  const content = (
    <View style={{ flex: 1, gap: theme.spacing[1] }}>
      <Text
        numberOfLines={compact ? 1 : 2}
        selectable
        tone={selected ? "primary" : "default"}
        variant={compact ? "bodySmall" : "body"}
      >
        {item.name || t("menus.itemRow.untitled")}
      </Text>
      {item.description?.trim() ? (
        <Text numberOfLines={compact ? 1 : 2} selectable tone="secondary" variant="caption">
          {item.description.trim()}
        </Text>
      ) : null}
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        {recipeName ? (
          <Badge label={`${t("menus.labels.recipe")}: ${recipeName}`} size="sm" variant="neutral" />
        ) : null}
        {item.quantityLabel?.trim() ? (
          <Badge label={item.quantityLabel.trim()} size="sm" variant="neutral" />
        ) : null}
      </View>
    </View>
  );

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.itemRow.accessibilityLabel")}
      style={{
        alignItems: "flex-start",
        flexDirection: "row",
        gap: theme.spacing[3],
        opacity: disabled ? 0.72 : 1,
      }}
    >
      {dragHandle ? <View>{dragHandle}</View> : null}
      {typeof index === "number" ? (
        <Text tone="muted" variant="caption">
          {index + 1}
        </Text>
      ) : null}
      {onPress ? (
        <Pressable
          accessibilityRole="button"
          disabled={disabled}
          onPress={() => void onPress()}
          style={{ flex: 1 }}
        >
          {content}
        </Pressable>
      ) : (
        content
      )}
      {hasActions ? (
        <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
          {onEdit ? (
            <Tooltip content={t("menus.actions.editItem")}>
              <IconButton
                accessibilityLabel={t("menus.actions.editItem")}
                disabled={disabled}
                icon={<Text variant="bodySmall">e</Text>}
                onPress={onEdit}
                size="sm"
                variant="ghost"
              />
            </Tooltip>
          ) : null}
          {onRemove ? (
            <Tooltip content={t("menus.actions.removeItem")}>
              <IconButton
                accessibilityLabel={t("menus.actions.removeItem")}
                disabled={disabled}
                icon={<Text tone="danger" variant="bodySmall">x</Text>}
                onPress={onRemove}
                size="sm"
                variant="ghost"
              />
            </Tooltip>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}
