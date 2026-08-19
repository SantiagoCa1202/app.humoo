import { View } from "react-native";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { AlertCard } from "@/components/patterns/alert-card";
import { BEOImpactSummary } from "@/components/patterns/beo-impact-summary";
import { BEOVersionComparison } from "@/components/patterns/beo-version-comparison";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import {
  buildBeoChangeSummary,
  type BEOChangeWarningRecord,
  type BEOFieldChangeRecord,
  type BEOImpactRecord,
  type BeoVersionRecord,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOChangeReviewProps = {
  accessibilityLabel?: string;
  changes: BEOFieldChangeRecord[];
  impacts?: BEOImpactRecord[] | null;
  newVersion: BeoVersionRecord;
  onCancel?: () => void | Promise<void>;
  onConfirm: () => void | Promise<void>;
  previousVersion: BeoVersionRecord;
  selectedChangeIds?: string[] | null;
  submitting?: boolean;
  warnings?: BEOChangeWarningRecord[] | null;
};

export function BEOChangeReview({
  accessibilityLabel,
  changes,
  impacts,
  newVersion,
  onCancel,
  onConfirm,
  previousVersion,
  selectedChangeIds,
  submitting = false,
  warnings,
}: BEOChangeReviewProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const visibleChanges = useMemo(() => {
    if (!selectedChangeIds?.length) {
      return changes;
    }

    const selected = new Set(selectedChangeIds);
    return changes.filter((change) => selected.has(change.id));
  }, [changes, selectedChangeIds]);
  const summary = useMemo(() => buildBeoChangeSummary(visibleChanges), [visibleChanges]);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("documents.changeReview.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      {warnings?.map((warning) => (
        <AlertCard
          key={warning.id}
          description={warning.description?.trim() || undefined}
          title={warning.title?.trim() || t("documents.changeReview.warningTitle")}
          tone={warning.severity === "danger" ? "error" : warning.severity === "warning" ? "warning" : "info"}
          variant="muted"
        />
      ))}
      <BEOVersionComparison
        baseVersion={previousVersion}
        changes={visibleChanges}
        targetVersion={newVersion}
      />
      <BEOImpactSummary impacts={impacts ?? []} />
      <ActionPreviewCard
        accessibilityLabel={t("documents.changeReview.previewAccessibilityLabel")}
        impact={
          impacts?.length
            ? t("documents.changeReview.previewImpact", { count: impacts.length })
            : t("documents.changeReview.previewNoImpact")
        }
        metadata={[
          {
            label: t("documents.comparison.summary.changed", { count: summary.changed }),
            value: String(summary.changed),
          },
          {
            label: t("documents.comparison.summary.added", { count: summary.added }),
            value: String(summary.added),
          },
          {
            label: t("documents.comparison.summary.removed", { count: summary.removed }),
            value: String(summary.removed),
          },
        ]}
        title={t("documents.changeReview.previewTitle")}
      />
      <ConfirmationCard
        accessibilityLabel={t("documents.changeReview.confirmationAccessibilityLabel")}
        cancelLabel={t("documents.actions.cancel")}
        confirmLabel={t("documents.changeReview.applyChanges")}
        description={t("documents.changeReview.confirmationDescription")}
        disabled={submitting}
        loading={submitting}
        onCancel={onCancel}
        onConfirm={onConfirm}
        title={t("documents.changeReview.title")}
      />
    </View>
  );
}
