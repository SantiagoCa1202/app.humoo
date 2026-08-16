import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type OfflineStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  detail?: React.ReactNode;
  onRetry?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function OfflineState({
  accessibilityLabel,
  compact = false,
  description,
  detail,
  onRetry,
  title,
}: OfflineStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.offline.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.offline.description")}
      detail={detail}
      primaryAction={
        onRetry
          ? {
              accessibilityLabel: t("states.offline.retry"),
              label: t("states.offline.retry"),
              onPress: onRetry,
            }
          : undefined
      }
      title={title ?? t("states.offline.title")}
      tone="offline"
      visual={<StateIcon compact={compact} tone="offline" />}
    />
  );
}
