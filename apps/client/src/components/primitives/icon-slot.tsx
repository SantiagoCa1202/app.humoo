import React from "react";
import { View, type ViewStyle } from "react-native";

type IconLikeProps = {
  color?: string;
  size?: number;
  style?: ViewStyle;
};

export type IconSlotProps = {
  icon?: React.ReactNode;
  color: string;
  size: number;
};

export function IconSlot({ icon, color, size }: IconSlotProps) {
  if (!icon) {
    return null;
  }

  if (React.isValidElement<IconLikeProps>(icon)) {
    return React.cloneElement(icon, {
      color,
      size,
      style: [
        {
          color,
        },
        icon.props.style,
      ] as never,
    });
  }

  return (
    <View
      style={{
        alignItems: "center",
        height: size,
        justifyContent: "center",
        width: size,
      }}
    >
      {icon}
    </View>
  );
}
