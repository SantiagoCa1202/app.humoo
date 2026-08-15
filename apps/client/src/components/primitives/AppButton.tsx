import {
  ActivityIndicator,
  Pressable,
  type PressableStateCallbackType,
  type PressableProps,
  type StyleProp,
  type ViewStyle,
  View,
} from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppButtonProps = Omit<PressableProps, "onPress" | "style"> & {
  label: string;
  variant?: "primary" | "secondary" | "ghost";
  loading?: boolean;
  fullWidth?: boolean;
  containerStyle?: StyleProp<ViewStyle>;
  onPress?: () => void | Promise<void>;
};

export function AppButton({
  label,
  variant = "primary",
  loading,
  disabled,
  fullWidth,
  containerStyle,
  onPress,
  ...props
}: AppButtonProps) {
  const { theme } = useAppTheme();

  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled || loading}
      onPress={onPress ? () => void onPress() : undefined}
      style={({ pressed }: PressableStateCallbackType) => [
        {
          minHeight: theme.layout.controlHeight,
          paddingHorizontal: 22,
          paddingVertical: 14,
          borderRadius: theme.radius.pill,
          borderWidth: variant === "ghost" ? 0 : 1,
          borderColor:
            variant === "primary"
              ? theme.colors.primary
              : variant === "secondary"
              ? theme.colors.borderStrong
              : "transparent",
          backgroundColor:
            variant === "primary"
              ? theme.colors.primary
              : variant === "secondary"
              ? theme.colors.surface
              : "transparent",
          opacity: disabled ? 0.45 : pressed ? 0.85 : 1,
          width: fullWidth ? "100%" : undefined,
          shadowColor:
            variant === "primary" ? theme.colors.shadow : "transparent",
          shadowOffset: { width: 0, height: 10 },
          shadowOpacity: variant === "primary" ? 1 : 0,
          shadowRadius: 18,
        },
        containerStyle,
      ]}
      {...props}
    >
      <View
        style={{
          alignItems: "center",
          flexDirection: "row",
          gap: 10,
          justifyContent: "center",
        }}
      >
        {loading ? (
          <ActivityIndicator
            color={
              variant === "primary"
                ? theme.colors.primaryContrast
                : theme.colors.text
            }
          />
        ) : null}
        <AppText
          style={{
            color:
              variant === "primary"
                ? theme.colors.primaryContrast
                : variant === "secondary"
                ? theme.colors.accent
                : theme.colors.text,
          }}
          variant="subtitle"
        >
          {label}
        </AppText>
      </View>
    </Pressable>
  );
}
