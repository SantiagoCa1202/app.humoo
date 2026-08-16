import { useMemo, useState } from "react";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { SearchInput } from "@/components/primitives/search-input";
import { SelectBase } from "@/components/primitives/select-base";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EntityPickerOption<T extends string> = {
  disabled?: boolean;
  icon?: React.ReactNode;
  label?: string;
  metadata?: string;
  name?: string;
  type?: string;
  value: T;
};

export type EntityPickerProps<T extends string> = {
  accessibilityLabel?: string;
  disabled?: boolean;
  entities: EntityPickerOption<T>[];
  error?: string;
  helperText?: string;
  label?: string;
  onChange: (value: T) => void;
  placeholder?: string;
  searchable?: boolean;
  value?: T;
};

export function EntityPicker<T extends string>({
  accessibilityLabel,
  disabled = false,
  entities,
  error,
  helperText,
  label,
  onChange,
  placeholder,
  searchable = true,
  value,
}: EntityPickerProps<T>) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const normalizedEntities = useMemo(
    () =>
      entities.map((entity) => ({
        ...entity,
        displayLabel: entity.label ?? entity.name ?? entity.value,
      })),
    [entities]
  );

  const selectedEntity = normalizedEntities.find((entity) => entity.value === value);
  const filteredEntities = normalizedEntities.filter((entity) => {
    if (!query.trim()) {
      return true;
    }

    const haystack = `${entity.displayLabel} ${entity.metadata ?? ""} ${entity.type ?? ""}`.toLowerCase();
    return haystack.includes(query.trim().toLowerCase());
  });

  return (
    <SelectBase
      accessibilityLabel={accessibilityLabel ?? label ?? placeholder}
      disabled={disabled}
      error={error}
      helperText={helperText}
      label={label}
      onDismiss={() => {
        setOpen(false);
        setQuery("");
      }}
      onOpen={() => setOpen(true)}
      open={open}
      placeholder={placeholder ?? t("forms.entityPicker.placeholder")}
      renderOptions={() => (
        <View style={{ gap: theme.spacing[2] }}>
          {searchable ? (
            <SearchInput
              onChangeText={setQuery}
              placeholder={t("forms.entityPicker.searchPlaceholder")}
              value={query}
            />
          ) : null}
          {filteredEntities.length === 0 ? (
            <Text tone="muted" variant="bodySmall">
              {t("forms.entityPicker.empty")}
            </Text>
          ) : (
            filteredEntities.map((entity) => {
              const isSelected = entity.value === value;

              return (
                <Pressable
                  key={entity.value}
                  disabled={entity.disabled}
                  onPress={() => {
                    onChange(entity.value);
                    setOpen(false);
                    setQuery("");
                  }}
                  style={({ hovered, pressed }) => ({
                    backgroundColor: isSelected
                      ? theme.colors.brand.soft
                      : pressed || hovered
                      ? theme.colors.background.subtle
                      : "transparent",
                    borderColor: isSelected
                      ? theme.colors.brand.primary
                      : "transparent",
                    borderCurve: "continuous",
                    borderRadius: theme.radius.md,
                    borderWidth: 1,
                    opacity: entity.disabled ? 0.6 : 1,
                    paddingHorizontal: theme.spacing[3],
                    paddingVertical: theme.spacing[3],
                  })}
                >
                  <View style={{ flexDirection: "row", gap: theme.spacing[2] }}>
                    {entity.icon ? <View>{entity.icon}</View> : null}
                    <View style={{ flex: 1, gap: theme.spacing[1] }}>
                      <Text
                        tone={isSelected ? "primary" : entity.disabled ? "muted" : "default"}
                        variant="body"
                      >
                        {entity.displayLabel}
                      </Text>
                      {entity.metadata ? (
                        <Text tone="muted" variant="caption">
                          {entity.metadata}
                        </Text>
                      ) : null}
                    </View>
                  </View>
                </Pressable>
              );
            })
          )}
        </View>
      )}
      triggerContent={
        <Text variant="body">{selectedEntity?.displayLabel}</Text>
      }
      triggerValueEmpty={!selectedEntity}
    />
  );
}
