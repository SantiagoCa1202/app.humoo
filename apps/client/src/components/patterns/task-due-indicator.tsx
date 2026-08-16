import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  formatTaskDateTime,
  getTaskDueState,
  type TaskStatus,
} from "@/features/tasks";

export type TaskDueIndicatorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  dueAt?: string | null;
  showIcon?: boolean;
  status?: TaskStatus | null;
  timeZone?: string | null;
};

function getVariantForDueState(state: ReturnType<typeof getTaskDueState>) {
  if (state === "completed") {
    return "success" as const;
  }

  if (state === "overdue") {
    return "danger" as const;
  }

  if (state === "today" || state === "tomorrow") {
    return "warning" as const;
  }

  return "neutral" as const;
}

export function TaskDueIndicator({
  accessibilityLabel,
  compact = false,
  dueAt,
  showIcon = false,
  status,
  timeZone,
}: TaskDueIndicatorProps) {
  const { t, i18n } = useTranslation("common");
  const dueState = getTaskDueState(dueAt, status, timeZone);
  const formatted = formatTaskDateTime(dueAt, i18n.language);

  if (!dueAt || !formatted) {
    return (
      <Text
        selectable
        tone="muted"
        variant={compact ? "caption" : "bodySmall"}
        accessibilityLabel={accessibilityLabel ?? t("tasks.due.none")}
      >
        {t("tasks.due.none")}
      </Text>
    );
  }

  const label =
    dueState === "completed"
      ? t("tasks.due.completed", { value: formatted })
      : dueState === "overdue"
      ? t("tasks.due.overdue", { value: formatted })
      : dueState === "today"
      ? t("tasks.due.today", { value: formatted })
      : dueState === "tomorrow"
      ? t("tasks.due.tomorrow", { value: formatted })
      : t("tasks.due.upcoming", { value: formatted });

  if (compact) {
    return (
      <Text
        selectable
        tone={
          dueState === "completed"
            ? "success"
            : dueState === "overdue"
            ? "danger"
            : dueState === "today" || dueState === "tomorrow"
            ? "warning"
            : "secondary"
        }
        variant="caption"
        accessibilityLabel={accessibilityLabel ?? label}
      >
        {label}
      </Text>
    );
  }

  return (
    <Badge
      dot={showIcon}
      label={label}
      size="sm"
      variant={getVariantForDueState(dueState)}
    />
  );
}
