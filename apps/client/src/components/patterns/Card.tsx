import type { ViewProps } from "react-native";

import { BaseCard } from "@/components/primitives/base-card";

type CardProps = ViewProps & {
  variant?: "default" | "outlined" | "muted" | "elevated" | "selected";
};

export function Card({
  style,
  variant = "elevated",
  ...props
}: CardProps) {
  return (
    <BaseCard
      {...props}
      padding="lg"
      radius="lg"
      style={[
        style,
      ]}
      variant={variant === "selected" ? "default" : variant}
      selected={variant === "selected"}
    />
  );
}
