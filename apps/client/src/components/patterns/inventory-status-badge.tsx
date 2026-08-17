import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { StatusBadge } from "@/components/primitives/status-badge";
import type { InventoryStatus } from "@/features/inventory";
import { getStatusTranslationKey } from "@/theme/status-config";

export type InventoryStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
  status?: InventoryStatus | null;
  uppercase?: boolean;
};

export function InventoryStatusBadge({
  accessibilityLabel,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: InventoryStatusBadgeProps) {
  const { t } = useTranslation("common");

  if (!status) {
    return null;
  }

  return (
    <View
      accessibilityLabel={
        accessibilityLabel ??
        t("inventory.statusBadge.accessibilityLabel", {
          status: t(getStatusTranslationKey(status, "inventory")),
        })
      }
      accessible
    >
      <StatusBadge
        namespace="inventory"
        showDot={showDot}
        size={size}
        status={status}
        uppercase={uppercase}
      />
    </View>
  );
}
