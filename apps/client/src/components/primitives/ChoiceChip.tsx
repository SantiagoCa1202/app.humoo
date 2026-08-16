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
        borderCurve: "continuous",
        borderColor: tokens.border,
        borderRadius: theme.radius.full,
        borderWidth: 1,
        paddingHorizontal: theme.spacing[3],
        paddingVertical: theme.spacing[2],
      })}
      {...props}
    >
      <AppText
        style={{
          color: tokens.text,
        }}
        variant="label"
      >
        {label}
      </AppText>
    </Pressable>
  );
}
