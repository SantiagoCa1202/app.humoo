import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ErrorState } from "@/components/patterns/error-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { ProgressCard } from "@/components/patterns/progress-card";
import { SuccessState } from "@/components/patterns/success-state";
import {
  getExtractionRunStatus,
  type DocumentRecord,
  type ExtractionRunRecord,
} from "@/features/documents";

export type BEOProcessingStateProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  document?: DocumentRecord | null;
  extractionRun?: ExtractionRunRecord | null;
  onReplaceFile?: () => void | Promise<void>;
  onRetry?: () => void | Promise<void>;
  progress?: number | null;
};

export function BEOProcessingState({
  accessibilityLabel,
  compact = false,
  document,
  extractionRun,
  onReplaceFile,
  onRetry,
  progress,
}: BEOProcessingStateProps) {
  const { t } = useTranslation("common");
  const extractionStatus = getExtractionRunStatus(extractionRun?.status);
  const documentStatus = document?.processingStatus;
  const errorDetail =
    extractionRun?.errorMessage?.trim() || document?.processingError?.trim() || undefined;

  if (extractionStatus === "failed" || documentStatus === "failed") {
    return (
      <ErrorState
        accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
        compact={compact}
        detail={errorDetail}
        onRetry={onRetry}
        title={t("documents.processing.states.failed")}
      />
    );
  }

  if (extractionStatus === "cancelled") {
    return (
      <AlertCard
        accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
        actionLabel={onReplaceFile ? t("documents.actions.replaceFile") : undefined}
        description={t("documents.processing.cancelledDescription")}
        onAction={onReplaceFile}
        title={t("documents.processing.states.cancelled")}
        tone="warning"
        variant="muted"
      />
    );
  }

  if (extractionStatus === "review_required" || extractionStatus === "completed") {
    return (
      <SuccessState
        accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
        compact={compact}
        description={t("documents.processing.readyDescription")}
        title={t("documents.processing.states.readyForReview")}
      />
    );
  }

  if (typeof progress === "number" && (extractionStatus === "pending" || extractionStatus === "processing")) {
    return (
      <ProgressCard
        accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
        percentage={progress}
        subtitle={t(
          extractionStatus === "pending"
            ? "documents.processing.states.processing"
            : "documents.processing.states.extracting"
        )}
        title={t("documents.processing.title")}
        variant="elevated"
      />
    );
  }

  if (extractionStatus === "pending" || extractionStatus === "processing" || documentStatus === "processing" || documentStatus === "uploaded") {
    return (
      <LoadingState
        accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
        compact={compact}
        description={t(
          extractionStatus === "processing"
            ? "documents.processing.states.extracting"
            : "documents.processing.states.processing"
        )}
        title={t("documents.processing.title")}
      />
    );
  }

  return (
    <AlertCard
      accessibilityLabel={accessibilityLabel ?? t("documents.processing.accessibilityLabel")}
      actionLabel={onReplaceFile ? t("documents.actions.replaceFile") : undefined}
      description={t("documents.processing.awaitingUploadDescription")}
      onAction={onReplaceFile}
      title={t("documents.processing.states.uploaded")}
      tone="info"
      variant="muted"
    />
  );
}
