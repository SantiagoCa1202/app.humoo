import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { StatusBadge } from "@/components/primitives/status-badge";
import {
  getPrepStatusNamespace,
  type PrepRenderableStatus,
  type PrepStatusNamespace,
} from "@/features/prep";
import { getStatusTranslationKey } from "@/theme/status-config";

export type PrepStatusBadgeProps = {
  accessibilityLabel?: string;
  namespace?: PrepStatusNamespace;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
  status: PrepRenderableStatus;
  uppercase?: boolean;
};

export function PrepStatusBadge({
  accessibilityLabel,
  namespace,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: PrepStatusBadgeProps) {
  const { t } = useTranslation("common");
  const resolvedNamespace = getPrepStatusNamespace(status, namespace);
  const label = t(getStatusTranslationKey(status, resolvedNamespace));

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? label}
      accessible
    >
      <StatusBadge
        namespace={resolvedNamespace}
        showDot={showDot}
        size={size}
        status={status}
        uppercase={uppercase}
      />
    </View>
  );
}
