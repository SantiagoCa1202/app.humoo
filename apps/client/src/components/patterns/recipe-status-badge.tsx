import { StatusBadge } from "@/components/primitives/status-badge";
import type { RecipeStatus } from "@/features/recipes";

export type RecipeStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
  status?: RecipeStatus | null;
  uppercase?: boolean;
};

export function RecipeStatusBadge({
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: RecipeStatusBadgeProps) {
  if (!status) {
    return null;
  }

  return (
    <StatusBadge
      namespace="recipes"
      showDot={showDot}
      size={size}
      status={status}
      uppercase={uppercase}
    />
  );
}
