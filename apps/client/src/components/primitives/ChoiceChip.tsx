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

  return (
    <Pressable
      disabled={disabled}
      style={({ pressed }) => ({
        backgroundColor: active
          ? theme.colors.primary
          : theme.colors.surfaceMuted,
        borderColor: active
          ? theme.colors.primary
          : theme.colors.border,
        borderRadius: theme.radius.pill,
        borderWidth: 1,
        opacity: disabled ? 0.45 : pressed ? 0.85 : 1,
        paddingHorizontal: 14,
        paddingVertical: 8,
      })}
      {...props}
    >
      <AppText
        style={{
          color: active ? theme.colors.primaryContrast : theme.colors.text,
        }}
        variant="caption"
      >
        {label}
      </AppText>
    </Pressable>
  );
}
