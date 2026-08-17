import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { StatusBadge, type StatusBadgeProps } from "@/components/primitives/status-badge";
import type { PurchaseOrderStatus } from "@/features/purchasing";

export type PurchaseOrderStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: StatusBadgeProps["size"];
  status: PurchaseOrderStatus;
  uppercase?: boolean;
};

export function PurchaseOrderStatusBadge({
  accessibilityLabel,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: PurchaseOrderStatusBadgeProps) {
  const { t } = useTranslation("common");
  const label = t(`purchasing.purchaseOrders.status.${status}`);

  return (
    <View accessibilityLabel={accessibilityLabel ?? label} accessible>
      <StatusBadge
        namespace="purchaseOrders"
        showDot={showDot}
        size={size}
        status={status}
        uppercase={uppercase}
      />
    </View>
  );
}
