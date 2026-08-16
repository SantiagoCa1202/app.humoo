import type { PressableProps, StyleProp, ViewStyle } from "react-native";

import { Button } from "@/components/primitives/button";

type AppButtonProps = Omit<PressableProps, "children" | "onPress" | "style"> & {
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
  return (
    <Button
      containerStyle={containerStyle}
      disabled={disabled}
      fullWidth={fullWidth}
      label={label}
      loading={loading}
      onPress={onPress}
      variant={
        variant === "destructiveSoft" || variant === "destructiveSolid"
          ? "destructive"
          : variant
      }
      {...props}
    />
  );
}
