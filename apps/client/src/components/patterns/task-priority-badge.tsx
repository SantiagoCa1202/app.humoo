import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import {
  getTaskPriorityMetadata,
  type TaskPriority,
} from "@/features/tasks";

export type TaskPriorityBadgeProps = {
  accessibilityLabel?: string;
  priority: TaskPriority;
  size?: "sm" | "md" | "lg";
};

export function TaskPriorityBadge({
  accessibilityLabel,
  priority,
  size = "md",
}: TaskPriorityBadgeProps) {
  const { t } = useTranslation("common");
  const metadata = getTaskPriorityMetadata(priority);

  if (!metadata) {
    return null;
  }

  const label = t(metadata.translationKey);

  return (
    <View accessibilityLabel={accessibilityLabel ?? label} accessible>
      <Badge label={label} size={size} variant={metadata.tone} />
    </View>
  );
}
