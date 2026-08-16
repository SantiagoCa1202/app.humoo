import { useTranslation } from "react-i18next";

import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";

export type ForbiddenStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  onBack?: () => void | Promise<void>;
  onRequestAccess?: () => void | Promise<void>;
  title?: React.ReactNode;
};

export function ForbiddenState({
  accessibilityLabel,
  compact = false,
  description,
  onBack,
  onRequestAccess,
  title,
}: ForbiddenStateProps) {
  const { t } = useTranslation("common");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.forbidden.accessibilityLabel")}
      compact={compact}
      description={description ?? t("states.forbidden.description")}
      primaryAction={
        onRequestAccess
          ? {
              accessibilityLabel: t("states.forbidden.requestAccess"),
              label: t("states.forbidden.requestAccess"),
              onPress: onRequestAccess,
            }
          : undefined
      }
      secondaryAction={
        onBack
          ? {
              accessibilityLabel: t("states.forbidden.back"),
              label: t("states.forbidden.back"),
              onPress: onBack,
              variant: "secondary",
            }
          : undefined
      }
      title={title ?? t("states.forbidden.title")}
      tone="forbidden"
      visual={<StateIcon compact={compact} tone="forbidden" />}
    />
  );
}
