import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type SuccessStateProps = {
  accessibilityLabel?: string;
  actionLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  onAction?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function SuccessState({
  accessibilityLabel,
  actionLabel,
  compact = false,
  description,
  onAction,
  title,
}: SuccessStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.success.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.success.description")}
      primaryAction={
        actionLabel && onAction
          ? {
              accessibilityLabel: actionLabel,
              label: actionLabel,
              onPress: onAction,
            }
          : undefined
      }
      title={title ?? t("states.success.title")}
      tone="success"
      visual={<StateIcon compact={compact} tone="success" />}
    />
  );
}
