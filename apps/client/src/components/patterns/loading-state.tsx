import { useTranslation } from "react-i18next";

import { Spinner } from "@/components/primitives/spinner";
import { StateLayout } from "@/components/patterns/state-layout";
import { useAppTheme } from "@/theme/ThemeProvider";

export type LoadingStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  title?: React.ReactNode;
};

export function LoadingState({
  accessibilityLabel,
  compact = false,
  description,
  title,
}: LoadingStateProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTitle = title ?? t("states.loading.title");
  const resolvedDescription = description ?? t("states.loading.description");

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel ?? t("states.loading.accessibilityLabel")}
      compact={compact}
      description={resolvedDescription}
      title={resolvedTitle}
      tone="loading"
      visual={
        <Spinner
          accessibilityLabel={accessibilityLabel ?? t("states.loading.accessibilityLabel")}
          size={compact ? "md" : "lg"}
          variant="primary"
        />
      }
    />
  );
}
