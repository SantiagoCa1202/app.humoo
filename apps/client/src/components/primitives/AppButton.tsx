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
  variant?:
    | "primary"
    | "secondary"
    | "outline"
    | "ghost"
    | "destructiveSoft"
    | "destructiveSolid";
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
  const variantTokens =
    disabled || loading
      ? theme.components.button.disabled
      : theme.components.button[variant];

  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled || loading}
      onPress={onPress ? () => void onPress() : undefined}
      style={({ hovered, pressed }: PressableStateCallbackType) => {
        const pressedBackground =
          "pressedBackground" in variantTokens
            ? variantTokens.pressedBackground
            : variantTokens.background;
        const hoverBackground =
          "hoverBackground" in variantTokens
            ? variantTokens.hoverBackground
            : variantTokens.background;
        const backgroundColor = pressed
          ? pressedBackground
          : hovered
          ? hoverBackground
          : variantTokens.background;
        const shadowColor =
          "shadowColor" in variantTokens
            ? variantTokens.shadowColor
            : "transparent";

        return [
          {
            minHeight: theme.layout.controlHeight,
            paddingHorizontal: 22,
            paddingVertical: 14,
            borderRadius: theme.radius.pill,
            borderWidth: variant === "ghost" ? 0 : 1,
            borderColor: variantTokens.border,
            backgroundColor: backgroundColor as string,
            width: fullWidth ? "100%" : undefined,
            shadowColor: shadowColor as string,
            shadowOffset: { width: 0, height: 10 },
            shadowOpacity: shadowColor === "transparent" ? 0 : 1,
            shadowRadius: shadowColor === "transparent" ? 0 : 18,
          },
          containerStyle,
        ];
      }}
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
          <ActivityIndicator color={variantTokens.text} />
        ) : null}
        <AppText
          style={{
            color: variantTokens.text,
          }}
          variant="subtitle"
        >
          {label}
        </AppText>
      </View>
    </Pressable>
  );
}
