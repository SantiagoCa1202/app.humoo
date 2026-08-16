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

        return [
          {
            minHeight: theme.layout.controlHeight,
            paddingHorizontal: theme.spacing[5],
            paddingVertical: theme.spacing[3],
            borderCurve: "continuous",
            borderRadius: theme.radius.md,
            borderWidth: variant === "ghost" ? 0 : 1,
            borderColor: variantTokens.border,
            backgroundColor: backgroundColor as string,
            width: fullWidth ? "100%" : undefined,
          },
          theme.shadows[variantTokens.shadow],
          containerStyle,
        ];
      }}
      {...props}
    >
      <View
        style={{
          alignItems: "center",
          flexDirection: "row",
          gap: theme.spacing[2],
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
          variant="label"
        >
          {label}
        </AppText>
      </View>
    </Pressable>
  );
}
