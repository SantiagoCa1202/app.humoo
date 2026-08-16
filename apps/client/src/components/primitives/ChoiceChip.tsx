import { Pressable, type PressableProps } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type ChoiceChipProps = Omit<PressableProps, "style"> & {
  active?: boolean;
  label: string;
};

export function ChoiceChip({
  active = false,
  disabled,
  label,
  ...props
}: ChoiceChipProps) {
  const { theme } = useAppTheme();
  const tokens = disabled
    ? theme.components.chip.disabled
    : active
    ? theme.components.chip.selected
    : theme.components.chip.default;

  return (
    <Pressable
      disabled={disabled}
      style={({ hovered, pressed }) => ({
        backgroundColor: (disabled
          ? tokens.background
          : pressed
          ? "pressedBackground" in tokens
            ? tokens.pressedBackground
            : tokens.background
          : hovered
          ? "hoverBackground" in tokens
            ? tokens.hoverBackground
            : tokens.background
          : tokens.background) as string,
        borderColor: tokens.border,
        borderRadius: theme.radius.pill,
        borderWidth: 1,
        paddingHorizontal: 14,
        paddingVertical: 8,
      })}
      {...props}
    >
      <AppText
        style={{
          color: tokens.text,
        }}
        variant="caption"
      >
        {label}
      </AppText>
    </Pressable>
  );
}
