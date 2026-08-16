import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import { PrepStatusBadge } from "@/components/patterns/prep-status-badge";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import {
  calculatePrepProgressPercentage,
  formatPrepDateRange,
  formatPrepDateTime,
  getPrepAssignedStaff,
  getPrepEventName,
  getPrepListStatus,
  getPrepListTitle,
  getPrepProgress,
  getPrepRemainingCount,
  getPrepVersionLabel,
  type PrepDisplayRecord,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepListCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  currentVersion?: PrepListVersionRecord | null;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  prepList: PrepDisplayRecord;
  progress?: PrepListProgressRecord | null;
  selected?: boolean;
  showEvent?: boolean;
  showProgress?: boolean;
  trailing?: React.ReactNode;
  version?: PrepListVersionRecord | null;
};

export function PrepListCard({
  accessibilityLabel,
  compact = false,
  currentVersion,
  disabled = false,
  onPress,
  prepList,
  progress,
  selected = false,
  showEvent = true,
  showProgress = true,
  trailing,
  version,
}: PrepListCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedVersion = currentVersion ?? version ?? null;
  const status = getPrepListStatus(prepList);
  const resolvedProgress = getPrepProgress(prepList, progress);
  const eventName = getPrepEventName(prepList.event);
  const eventSchedule = formatPrepDateTime(prepList.event?.startsAt, i18n.language);
  const productionWindow = formatPrepDateRange(
    prepList.productionStartsAt,
    prepList.productionEndsAt,
    i18n.language
  );
  const versionLabel = getPrepVersionLabel(resolvedVersion, t);
  const remaining = getPrepRemainingCount(resolvedProgress);
  const assignedStaff = getPrepAssignedStaff(resolvedProgress);
  const metadata: EntityCardMetadataItem[] = [
    showEvent && eventName
      ? {
          label: t("prep.labels.event"),
          value: eventSchedule ? `${eventName} - ${eventSchedule}` : eventName,
        }
      : null,
    showProgress &&
    typeof resolvedProgress.completed === "number" &&
    typeof resolvedProgress.total === "number"
      ? {
          label: t("prep.labels.progress"),
          value: t("prep.progress.summary", {
            completed: resolvedProgress.completed,
            percentage: Math.round(calculatePrepProgressPercentage(resolvedProgress)),
            total: resolvedProgress.total,
          }),
        }
      : null,
    typeof remaining === "number"
      ? {
          label: t("prep.labels.remaining"),
          value: t("prep.metrics.items", { count: remaining }),
        }
      : null,
    typeof resolvedProgress.blocked === "number" && resolvedProgress.blocked > 0
      ? {
          label: t("prep.labels.blocked"),
          tone: "danger",
          value: t("prep.metrics.items", { count: resolvedProgress.blocked }),
        }
      : null,
    typeof resolvedProgress.dueAt === "string"
      ? {
          label: t("prep.labels.due"),
          value:
            formatPrepDateTime(resolvedProgress.dueAt, i18n.language) ??
            resolvedProgress.dueAt,
        }
      : null,
    productionWindow
      ? {
          label: t("prep.labels.productionWindow"),
          value: productionWindow,
        }
      : null,
    typeof resolvedProgress.assignedStaffCount === "number"
      ? {
          label: t("prep.labels.assigned"),
          value: t("prep.metrics.staff", { count: resolvedProgress.assignedStaffCount }),
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("prep.card.accessibilityLabel")}
      disabled={disabled}
      eyebrow={versionLabel ?? undefined}
      leading={
        assignedStaff.length ? (
          <AvatarGroup
            size={compact ? "sm" : "md"}
            users={assignedStaff.map((user) => ({
              name: user.name ?? undefined,
              source: user.source,
              variant: "neutral",
            }))}
          />
        ) : undefined
      }
      metadata={compact ? metadata.slice(0, 3) : metadata}
      onPress={onPress}
      selected={selected}
      subtitle={resolvedVersion?.changeSummary?.trim() || undefined}
      title={getPrepListTitle(prepList)}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {trailing}
          {status ? <PrepStatusBadge namespace="prepLists" size="sm" status={status} /> : null}
        </View>
      }
      variant="elevated"
    />
  );
}
