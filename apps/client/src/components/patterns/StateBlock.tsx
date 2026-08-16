import { ConflictState } from "@/components/patterns/conflict-state";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { OfflineState } from "@/components/patterns/offline-state";
import { StateIcon } from "@/components/patterns/state-icon";
import { StateLayout } from "@/components/patterns/state-layout";
import { SuccessState } from "@/components/patterns/success-state";
import type { AppStateTone } from "@/theme/status-config";

type StateBlockProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  title: React.ReactNode;
  description?: string;
  tone?: AppStateTone;
  actionLabel?: string;
  onAction?: () => void | Promise<void>;
};

export function StateBlock({
  accessibilityLabel,
  compact = false,
  title,
  description,
  tone = "info",
  actionLabel,
  onAction,
}: StateBlockProps) {
  if (tone === "loading") {
    return (
      <LoadingState
        accessibilityLabel={accessibilityLabel}
        compact={compact}
        description={description}
        title={title}
      />
    );
  }

  if (tone === "empty") {
    return (
      <EmptyState
        accessibilityLabel={accessibilityLabel}
        actionLabel={actionLabel}
        compact={compact}
        description={description}
        onAction={onAction}
        title={title}
      />
    );
  }

  if (tone === "error") {
    return (
      <ErrorState
        accessibilityLabel={accessibilityLabel}
        compact={compact}
        detail={description}
        title={title}
        onRetry={onAction}
      />
    );
  }

  if (tone === "forbidden") {
    return (
      <ForbiddenState
        accessibilityLabel={accessibilityLabel}
        compact={compact}
        description={description}
        title={title}
        onRequestAccess={onAction}
      />
    );
  }

  if (tone === "offline") {
    return (
      <OfflineState
        accessibilityLabel={accessibilityLabel}
        compact={compact}
        detail={description}
        title={title}
        onRetry={onAction}
      />
    );
  }

  if (tone === "success") {
    return (
      <SuccessState
        accessibilityLabel={accessibilityLabel}
        actionLabel={actionLabel}
        compact={compact}
        description={description}
        onAction={onAction}
        title={title}
      />
    );
  }

  if (tone === "conflict") {
    return (
      <ConflictState
        accessibilityLabel={accessibilityLabel}
        compact={compact}
        detail={description}
        title={title}
        onReload={onAction}
      />
    );
  }

  return (
    <StateLayout
      accessibilityLabel={accessibilityLabel}
      compact={compact}
      description={description}
      primaryAction={
        actionLabel && onAction
          ? {
              accessibilityLabel: actionLabel,
              label: actionLabel,
              onPress: onAction,
            }
          : undefined
      }
      title={title}
      tone="info"
      visual={<StateIcon compact={compact} tone="info" />}
    />
  );
}
