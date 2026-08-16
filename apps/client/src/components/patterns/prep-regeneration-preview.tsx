import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { AlertCard } from "@/components/patterns/alert-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { PrepVersionComparison } from "@/components/patterns/prep-version-comparison";
import {
  type PrepGenerationPreviewRecord,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
  type PrepVersionComparisonChange,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepRegenerationPreviewProps = {
  accessibilityLabel?: string;
  changes?: PrepVersionComparisonChange[];
  currentProgress?: PrepListProgressRecord | null;
  currentVersion: PrepListVersionRecord;
  loading?: boolean;
  onCancel?: () => void | Promise<void>;
  onConfirm?: () => void | Promise<void>;
  preserveAssignments?: boolean;
  preserveCompletedItems?: boolean;
  proposedProgress?: PrepListProgressRecord | null;
  proposedVersion: PrepListVersionRecord;
  warnings?: PrepGenerationPreviewRecord["warnings"] | null;
};

export function PrepRegenerationPreview({
  accessibilityLabel,
  changes,
  currentProgress,
  currentVersion,
  loading = false,
  onCancel,
  onConfirm,
  preserveAssignments,
  preserveCompletedItems,
  proposedProgress,
  proposedVersion,
  warnings,
}: PrepRegenerationPreviewProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.regeneration.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <ActionPreviewCard
        action={t("prep.regeneration.preview.action")}
        description={t("prep.regeneration.preview.description")}
        impact={t("prep.regeneration.preview.impact")}
        metadata={
          [
            {
              label: t("prep.regeneration.labels.currentVersion"),
              value: t("prep.version.label", { value: currentVersion.version }),
            },
            {
              label: t("prep.regeneration.labels.proposedVersion"),
              value: t("prep.version.label", { value: proposedVersion.version }),
            },
            preserveCompletedItems !== undefined
              ? {
                  label: t("prep.generation.labels.preserveCompletedItems"),
                  value: preserveCompletedItems
                    ? t("prep.generation.enabled")
                    : t("prep.generation.disabled"),
                }
              : null,
            preserveAssignments !== undefined
              ? {
                  label: t("prep.generation.labels.preserveAssignments"),
                  value: preserveAssignments
                    ? t("prep.generation.enabled")
                    : t("prep.generation.disabled"),
                }
              : null,
          ].filter(Boolean) as React.ComponentProps<typeof ActionPreviewCard>["metadata"]
        }
        title={t("prep.regeneration.preview.title")}
        type={t("prep.regeneration.preview.badge")}
      />
      {warnings?.map((warning, index) => (
        <AlertCard
          key={warning.id ?? `prep-regeneration-warning-${index}`}
          description={warning.description ?? undefined}
          title={warning.title}
          tone={warning.tone === "danger" ? "error" : warning.tone ?? "warning"}
          variant="muted"
        />
      ))}
      <PrepVersionComparison
        baseProgress={currentProgress}
        baseVersion={currentVersion}
        changes={changes}
        compact={loading}
        targetProgress={proposedProgress}
        targetVersion={proposedVersion}
      />
      {onConfirm || onCancel ? (
        <ConfirmationCard
          confirmLabel={t("prep.regeneration.preview.confirm")}
          description={t("prep.regeneration.preview.confirmDescription")}
          loading={loading}
          onCancel={onCancel}
          onConfirm={onConfirm}
          title={t("prep.regeneration.preview.confirmTitle")}
        />
      ) : null}
    </View>
  );
}
