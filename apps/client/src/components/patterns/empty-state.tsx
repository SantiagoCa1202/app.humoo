import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type EmptyStateProps = {
  accessibilityLabel?: string;
  actionLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  icon?: React.ReactNode;
  onAction?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function EmptyState({
  accessibilityLabel,
  actionLabel,
  compact = false,
  description,
  icon,
  onAction,
  title,
}: EmptyStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.empty.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.empty.description")}
      primaryAction={
        actionLabel && onAction
          ? {
              accessibilityLabel: actionLabel,
              label: actionLabel,
              onPress: onAction,
            }
          : undefined
      }
      title={title ?? t("states.empty.title")}
      tone="empty"
      visual={<StateIcon compact={compact} icon={icon} tone="empty" />}
    />
  );
}
