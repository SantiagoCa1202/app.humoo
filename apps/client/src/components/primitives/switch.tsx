import { Pressable, Switch as ReactNativeSwitch, View } from "react-native";
import { useTranslation } from "react-i18next";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SwitchProps = {
  accessibilityLabel?: string;
  description?: string;
  disabled?: boolean;
  label?: string;
  onChange: (value: boolean) => void;
  value: boolean;
};

export function Switch({
  accessibilityLabel,
  description,
  disabled = false,
  label,
  onChange,
  value,
}: SwitchProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <Pressable
      accessibilityLabel={accessibilityLabel ?? label ?? t("toggle")}
      accessibilityRole="switch"
      accessibilityState={{ checked: value, disabled }}
      disabled={disabled}
      onPress={() => onChange(!value)}
      style={({ hovered, pressed }) => ({
        alignItems: "center",
        backgroundColor:
          !disabled && (pressed || hovered)
            ? theme.colors.background.subtle
            : "transparent",
        borderCurve: "continuous",
        borderRadius: theme.radius.md,
        flexDirection: "row",
        gap: theme.spacing[3],
        justifyContent: "space-between",
        opacity: disabled ? 0.7 : 1,
        padding: theme.spacing[2],
      })}
    >
      <View style={{ flex: 1, gap: theme.spacing[1] }}>
        {label ? (
          <Text tone={disabled ? "muted" : "default"} variant="body">
            {label}
          </Text>
        ) : null}
        {description ? (
          <Text tone="muted" variant="caption">
            {description}
          </Text>
        ) : null}
      </View>
      <ReactNativeSwitch
        accessibilityElementsHidden
        disabled={disabled}
        onValueChange={onChange}
        thumbColor={
          disabled
            ? theme.colors.interaction.disabledText
            : theme.colors.background.surface
        }
        trackColor={{
          false: theme.colors.border.strong,
          true: theme.colors.brand.primary,
        }}
        value={value}
      />
    </Pressable>
  );
}
