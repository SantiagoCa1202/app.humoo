import { Modal, Pressable, ScrollView, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Chip } from "@/components/primitives/chip";
import { FieldLabel } from "@/components/primitives/field-label";
import { FieldMessage } from "@/components/primitives/field-message";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SelectOption<T extends string> = {
  disabled?: boolean;
  label: string;
  value: T;
};

type SelectBaseProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  onDismiss: () => void;
  open: boolean;
  optional?: boolean;
  placeholder?: string;
  renderOptions: () => React.ReactNode;
  required?: boolean;
  triggerContent: React.ReactNode;
  triggerValueEmpty?: boolean;
  onOpen: () => void;
};

export function SelectBase({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  onDismiss,
  onOpen,
  open,
  optional = false,
  placeholder,
  renderOptions,
  required = false,
  triggerContent,
  triggerValueEmpty = false,
}: SelectBaseProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const inputTokens = theme.components.input;
  const borderColor = error
    ? inputTokens.errorBorder
    : open
    ? theme.colors.brand.primary
    : inputTokens.border;

  return (
    <View style={{ gap: theme.spacing[2] }}>
      {label ? (
        <FieldLabel label={label} optional={optional} required={required} />
      ) : null}
      <Pressable
        accessibilityLabel={accessibilityLabel ?? label ?? placeholder}
        accessibilityRole="button"
        accessibilityState={{ disabled, expanded: open }}
        disabled={disabled}
        onPress={onOpen}
        style={({ hovered, pressed }) => ({
          alignItems: "center",
          backgroundColor: disabled
            ? inputTokens.disabledBackground
            : pressed || hovered
            ? theme.colors.background.subtle
            : inputTokens.background,
          borderColor,
          borderCurve: "continuous",
          borderRadius: theme.radius.md,
          borderWidth: 1,
          flexDirection: "row",
          gap: theme.spacing[2],
          justifyContent: "space-between",
          minHeight: theme.layout.controlHeight,
          opacity: disabled ? 0.7 : 1,
          paddingHorizontal: theme.spacing[4],
          paddingVertical: theme.spacing[3],
        })}
      >
        <View style={{ flex: 1 }}>
          {triggerValueEmpty ? (
            <Text tone="muted" variant="body">
              {placeholder ?? t("selectOption")}
            </Text>
          ) : (
            triggerContent
          )}
        </View>
        <Text tone={disabled ? "muted" : "secondary"} variant="bodySmall">
          {open ? "^" : "v"}
        </Text>
      </Pressable>
      <FieldMessage error={error} helperText={helperText} />
      <Modal
        animationType="fade"
        onRequestClose={onDismiss}
        transparent
        visible={open}
      >
        <Pressable
          onPress={onDismiss}
          style={{
            backgroundColor: theme.colors.overlay,
            flex: 1,
            justifyContent: "center",
            padding: theme.spacing[4],
          }}
        >
          <Pressable
            onPress={(event) => {
              event.stopPropagation();
            }}
            style={{
              backgroundColor: theme.colors.background.surface,
              borderColor: theme.colors.border.default,
              borderCurve: "continuous",
              borderRadius: theme.radius.lg,
              borderWidth: 1,
              maxHeight: "70%",
              padding: theme.spacing[3],
              ...theme.shadows.sm,
            }}
          >
            <ScrollView>{renderOptions()}</ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

type SelectChipsProps = {
  disabled?: boolean;
  labels: string[];
  onRemove?: (label: string) => void;
};

export function SelectChips({
  disabled = false,
  labels,
  onRemove,
}: SelectChipsProps) {
  const { theme } = useAppTheme();

  return (
    <View
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        gap: theme.spacing[2],
      }}
    >
      {labels.map((optionLabel) => (
        <Chip
          key={optionLabel}
          disabled={disabled}
          label={optionLabel}
          onRemove={onRemove ? () => onRemove(optionLabel) : undefined}
          removable={Boolean(onRemove)}
          selected
          size="sm"
          variant="primary"
        />
      ))}
    </View>
  );
}
