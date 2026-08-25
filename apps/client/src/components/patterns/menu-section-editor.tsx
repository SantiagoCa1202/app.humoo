import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { TextField } from "@/components/primitives/text-field";
import { MenuItemEditor } from "@/components/patterns/menu-item-editor";
import { MenuItemRow } from "@/components/patterns/menu-item-row";
import {
  createMenuItemDraft,
  getMenuItemKey,
  moveItemInArray,
  normalizeMenuItemsOrder,
  sortMenuItems,
  type MenuRecipeOption,
  type MenuSectionRecord,
  type MenuSectionValidationErrors,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuSectionEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  errors?: MenuSectionValidationErrors;
  hasEvent?: boolean;
  onChange: (section: MenuSectionRecord) => void;
  onMoveDown?: () => void;
  onMoveUp?: () => void;
  onRemove?: () => void;
  recipeOptions?: MenuRecipeOption[];
  section: MenuSectionRecord;
};

export function MenuSectionEditor({
  accessibilityLabel,
  compact = false,
  disabled = false,
  errors,
  hasEvent = false,
  onChange,
  onMoveDown,
  onMoveUp,
  onRemove,
  recipeOptions,
  section,
}: MenuSectionEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [editingItemKey, setEditingItemKey] = useState<string | null>(null);
  const sortedItems = useMemo(() => sortMenuItems(section.items), [section.items]);

  const updateItems = (items: MenuSectionRecord["items"]) => {
    onChange({
      ...section,
      itemCount: items.length,
      items: normalizeMenuItemsOrder(items),
    });
  };

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("menus.sectionEditor.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="default"
    >
      <CardHeader
        title={section.name?.trim() || t("menus.sectionEditor.untitled")}
        trailing={
          <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
            {onMoveUp ? (
              <IconButton
                accessibilityLabel={t("menus.actions.moveUp")}
                disabled={disabled}
                icon={<Text variant="bodySmall">^</Text>}
                onPress={onMoveUp}
                size="sm"
                variant="ghost"
              />
            ) : null}
            {onMoveDown ? (
              <IconButton
                accessibilityLabel={t("menus.actions.moveDown")}
                disabled={disabled}
                icon={<Text variant="bodySmall">v</Text>}
                onPress={onMoveDown}
                size="sm"
                variant="ghost"
              />
            ) : null}
            {onRemove ? (
              <IconButton
                accessibilityLabel={t("menus.actions.removeSection")}
                disabled={disabled}
                icon={<Text tone="danger" variant="bodySmall">x</Text>}
                onPress={onRemove}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        }
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          <TextField
            editable={!disabled}
            error={errors?.name}
            label={t("menus.form.fields.sectionName.label")}
            onChangeText={(name) => onChange({ ...section, name })}
            placeholder={t("menus.form.fields.sectionName.placeholder")}
            required
            value={section.name ?? ""}
          />
          {sortedItems.length === 0 ? (
            <Text tone="muted" variant="bodySmall">
              {t("menus.section.empty")}
            </Text>
          ) : (
            <View style={{ gap: theme.spacing[3] }}>
              {sortedItems.map((item, index) => {
                const itemKey = getMenuItemKey(item);
                const itemErrors = errors?.items?.[itemKey];
                const isEditing = editingItemKey === itemKey;

                return (
                  <View key={itemKey} style={{ gap: theme.spacing[3] }}>
                    <MenuItemRow
                      compact={compact}
                      dragHandle={
                        <View style={{ flexDirection: "row", gap: theme.spacing[1] }}>
                          <IconButton
                            accessibilityLabel={t("menus.actions.moveUp")}
                            disabled={disabled || index === 0}
                            icon={<Text variant="bodySmall">^</Text>}
                            onPress={() =>
                              updateItems(moveItemInArray(sortedItems, index, index - 1))
                            }
                            size="sm"
                            variant="ghost"
                          />
                          <IconButton
                            accessibilityLabel={t("menus.actions.moveDown")}
                            disabled={disabled || index === sortedItems.length - 1}
                            icon={<Text variant="bodySmall">v</Text>}
                            onPress={() =>
                              updateItems(moveItemInArray(sortedItems, index, index + 1))
                            }
                            size="sm"
                            variant="ghost"
                          />
                        </View>
                      }
                      editable
                      index={index}
                      item={item}
                      onEdit={() => setEditingItemKey(isEditing ? null : itemKey)}
                      onRemove={() =>
                        updateItems(sortedItems.filter((currentItem) => getMenuItemKey(currentItem) !== itemKey))
                      }
                    />
                    {isEditing ? (
                      <MenuItemEditor
                        compact={compact}
                        disabled={disabled}
                        errors={itemErrors}
                        hasEvent={hasEvent}
                        onCancel={() => setEditingItemKey(null)}
                        onChange={(nextItem) =>
                          updateItems(
                            sortedItems.map((currentItem) =>
                              getMenuItemKey(currentItem) === itemKey ? nextItem : currentItem
                            )
                          )
                        }
                        onSubmit={() => setEditingItemKey(null)}
                        recipeOptions={recipeOptions}
                        value={item}
                      />
                    ) : null}
                    {index < sortedItems.length - 1 ? <Divider spacing="none" /> : null}
                  </View>
                );
              })}
            </View>
          )}
          <Button
            disabled={disabled}
            label={t("menus.actions.addItem")}
            onPress={() => {
              const nextItem = createMenuItemDraft({
                position: sortedItems.length + 1,
              });
              updateItems([...sortedItems, nextItem]);
              setEditingItemKey(getMenuItemKey(nextItem));
            }}
            size="sm"
            variant="secondary"
          />
        </View>
      </CardContent>
    </BaseCard>
  );
}
