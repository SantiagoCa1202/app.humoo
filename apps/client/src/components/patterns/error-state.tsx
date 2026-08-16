import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type ErrorStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  detail?: React.ReactNode;
  onRetry?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function ErrorState({
  accessibilityLabel,
  compact = false,
  description,
  detail,
  onRetry,
  title,
}: ErrorStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.error.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.error.description")}
      detail={detail}
      primaryAction={
        onRetry
          ? {
              accessibilityLabel: t("states.error.retry"),
              label: t("states.error.retry"),
              onPress: onRetry,
            }
          : undefined
      }
      title={title ?? t("states.error.title")}
      tone="error"
      visual={<StateIcon compact={compact} tone="error" />}
    />
  );
}
