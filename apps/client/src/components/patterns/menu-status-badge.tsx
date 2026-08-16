import { useTranslation } from "react-i18next";

import { StatusBadge } from "@/components/primitives/status-badge";
import type { MenuStatus } from "@/features/menus";

export type MenuStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
  status?: MenuStatus | null;
  uppercase?: boolean;
};

export function MenuStatusBadge({
  accessibilityLabel,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: MenuStatusBadgeProps) {
  const { t } = useTranslation("common");

  if (!status) {
    return null;
  }

  return (
    <StatusBadge
      namespace="menus"
      showDot={showDot}
      size={size}
      status={status}
      uppercase={uppercase}
    />
  );
}
