import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { StatusBadge } from "@/components/primitives/status-badge";
import type { TaskStatus } from "@/features/tasks";
import { getStatusTranslationKey } from "@/theme/status-config";

export type TaskStatusBadgeProps = {
  accessibilityLabel?: string;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
  status: TaskStatus;
  uppercase?: boolean;
};

export function TaskStatusBadge({
  accessibilityLabel,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: TaskStatusBadgeProps) {
  const { t } = useTranslation("common");
  const label = t(getStatusTranslationKey(status, "tasks"));

  return (
    <View accessibilityLabel={accessibilityLabel ?? label} accessible>
      <StatusBadge
        namespace="tasks"
        showDot={showDot}
        size={size}
        status={status}
        uppercase={uppercase}
      />
    </View>
  );
}
