import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Badge } from "@/components/primitives/badge";
import { Divider } from "@/components/primitives/divider";
import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import { PrepStatusBadge } from "@/components/patterns/prep-status-badge";
import {
  calculatePrepProgressPercentage,
  formatPrepDateRange,
  formatPrepDateTime,
  getPrepAssignedStaff,
  getPrepEventName,
  getPrepListStatus,
  getPrepProgress,
  getPrepRemainingCount,
  getPrepVersionLabel,
  type PrepDisplayRecord,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepListDetailHeaderProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  currentVersion?: PrepListVersionRecord | null;
  prepList: PrepDisplayRecord;
  progress?: PrepListProgressRecord | null;
};

export function PrepListDetailHeader({
  accessibilityLabel,
  actions,
  compact = false,
  currentVersion,
  prepList,
  progress,
}: PrepListDetailHeaderProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getPrepListStatus(prepList);
  const resolvedProgress = getPrepProgress(prepList, progress);
  const eventName = getPrepEventName(prepList.event);
  const eventDate = formatPrepDateTime(prepList.event?.startsAt, i18n.language);
  const dueAt = formatPrepDateTime(
    resolvedProgress.dueAt ?? prepList.productionEndsAt,
    i18n.language
  );
  const productionWindow = formatPrepDateRange(
    prepList.productionStartsAt,
    prepList.productionEndsAt,
    i18n.language
  );
  const versionLabel = getPrepVersionLabel(currentVersion, t);
  const assignedStaff = getPrepAssignedStaff(resolvedProgress);
  const remaining = getPrepRemainingCount(resolvedProgress);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.detailHeader.accessibilityLabel")}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4], width: "100%" }}
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[2] }}>
          <Heading
            eyebrow={versionLabel ?? undefined}
            level={compact ? "h3" : "h2"}
            subtitle={eventName ? (eventDate ? `${eventName} - ${eventDate}` : eventName) : undefined}
            title={prepList.name}
          />
          {status ? <PrepStatusBadge namespace="prepLists" size={compact ? "sm" : "md"} status={status} /> : null}
        </View>
        {actions ? <View>{actions}</View> : null}
      </View>
      <Divider spacing="none" />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
        {eventName ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.event")}
            </Text>
            <Text selectable variant="bodySmall">
              {eventDate ? `${eventName} - ${eventDate}` : eventName}
            </Text>
          </View>
        ) : null}
        {productionWindow ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.productionWindow")}
            </Text>
            <Text selectable variant="bodySmall">
              {productionWindow}
            </Text>
          </View>
        ) : null}
        {dueAt ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.due")}
            </Text>
            <Text selectable variant="bodySmall">
              {dueAt}
            </Text>
          </View>
        ) : null}
        {typeof resolvedProgress.completed === "number" && typeof resolvedProgress.total === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.progress")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("prep.progress.summary", {
                completed: resolvedProgress.completed,
                percentage: Math.round(calculatePrepProgressPercentage(resolvedProgress)),
                total: resolvedProgress.total,
              })}
            </Text>
          </View>
        ) : null}
        {typeof remaining === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.remaining")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("prep.metrics.items", { count: remaining })}
            </Text>
          </View>
        ) : null}
        {typeof resolvedProgress.blocked === "number" ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("prep.labels.blocked")}
            </Text>
            <Text selectable variant="bodySmall">
              {t("prep.metrics.items", { count: resolvedProgress.blocked })}
            </Text>
          </View>
        ) : null}
      </View>
      {assignedStaff.length ? (
        <View style={{ gap: theme.spacing[2] }}>
          <Text tone="muted" variant="caption">
            {t("prep.labels.assigned")}
          </Text>
          <AvatarGroup
            size="sm"
            users={assignedStaff.map((user) => ({
              name: user.name ?? undefined,
              source: user.source,
              variant: "neutral",
            }))}
          />
        </View>
      ) : null}
      {currentVersion ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          <Badge
            label={versionLabel ?? t("prep.version.current")}
            size="sm"
            variant="neutral"
          />
          {currentVersion.status ? (
            <PrepStatusBadge namespace="prepListVersions" size="sm" status={currentVersion.status} />
          ) : null}
        </View>
      ) : null}
    </View>
  );
}
