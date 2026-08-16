import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/primitives/button";
import {
  resolveTaskActionLabel,
  type TaskRecord,
  type TaskStatusAction,
} from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

export type TaskStatusActionsProps = {
  accessibilityLabel?: string;
  availableActions?: TaskStatusAction[];
  compact?: boolean;
  disabled?: boolean;
  loadingAction?: string | null;
  onAction?: (action: TaskStatusAction) => void | Promise<void>;
  task?: TaskRecord | null;
};

function getVariant(actionId: string) {
  if (actionId === "complete") {
    return "primary" as const;
  }

  if (actionId === "block") {
    return "destructive" as const;
  }

  if (actionId === "skip") {
    return "ghost" as const;
  }

  if (actionId === "reopen") {
    return "secondary" as const;
  }

  return "secondary" as const;
}

export function TaskStatusActions({
  accessibilityLabel,
  availableActions = [],
  compact = false,
  disabled = false,
  loadingAction,
  onAction,
}: TaskStatusActionsProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  if (!availableActions.length) {
    return null;
  }

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("tasks.statusActions.accessibilityLabel")}
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        gap: theme.spacing[2],
      }}
    >
      {availableActions.map((action) => {
        const label = resolveTaskActionLabel(action, t);
        const isLoading = loadingAction === action.id;

        return (
          <Button
            accessibilityHint={t("tasks.statusActions.accessibilityHint", { action: label })}
            accessibilityLabel={label}
            disabled={disabled || Boolean(loadingAction) || action.disabled}
            key={action.id}
            label={label}
            loading={isLoading}
            onPress={() => onAction?.(action)}
            size={compact ? "sm" : "md"}
            variant={getVariant(action.id)}
          />
        );
      })}
    </View>
  );
}
