import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type ConflictStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  detail?: React.ReactNode;
  onReload?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function ConflictState({
  accessibilityLabel,
  compact = false,
  description,
  detail,
  onReload,
  onReview,
  title,
}: ConflictStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.conflict.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.conflict.description")}
      detail={detail}
      primaryAction={
        onReload
          ? {
              accessibilityLabel: t("states.conflict.reload"),
              label: t("states.conflict.reload"),
              onPress: onReload,
            }
          : undefined
      }
      secondaryAction={
        onReview
          ? {
              accessibilityLabel: t("states.conflict.review"),
              label: t("states.conflict.review"),
              onPress: onReview,
              variant: "secondary",
            }
          : undefined
      }
      title={title ?? t("states.conflict.title")}
      tone="conflict"
      visual={<StateIcon compact={compact} tone="conflict" />}
    />
  );
}
