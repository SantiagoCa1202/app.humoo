import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Text } from "@/components/primitives/text";
import { PrepStatusBadge } from "@/components/patterns/prep-status-badge";
import {
  calculatePrepProgressPercentage,
  formatPrepDateTime,
  getPrepEventName,
  getPrepListStatus,
  getPrepProgress,
  type PrepDisplayRecord,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepListRowProps = {
  accessibilityLabel?: string;
  currentVersion?: PrepListVersionRecord | null;
  disabled?: boolean;
  onPress?: () => void | Promise<void>;
  prepList: PrepDisplayRecord;
  progress?: PrepListProgressRecord | null;
  selected?: boolean;
  showStatus?: boolean;
};

export function PrepListRow({
  accessibilityLabel,
  currentVersion,
  disabled = false,
  onPress,
  prepList,
  progress,
  selected = false,
  showStatus = true,
}: PrepListRowProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getPrepListStatus(prepList);
  const resolvedProgress = getPrepProgress(prepList, progress);
  const eventName = getPrepEventName(prepList.event);
  const dueAt = formatPrepDateTime(
    resolvedProgress.dueAt ?? prepList.productionEndsAt ?? prepList.event?.startsAt,
    i18n.language
  );
  const details = [
    eventName,
    typeof resolvedProgress.completed === "number" && typeof resolvedProgress.total === "number"
      ? t("prep.progress.summary", {
          completed: resolvedProgress.completed,
          percentage: Math.round(calculatePrepProgressPercentage(resolvedProgress)),
          total: resolvedProgress.total,
        })
      : null,
    dueAt ? `${t("prep.labels.due")}: ${dueAt}` : null,
    currentVersion ? t("prep.version.currentHint", { version: currentVersion.version }) : null,
  ]
    .filter(Boolean)
    .join(" - ");

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("prep.listRow.accessibilityLabel")}
      disabled={disabled}
      onPress={onPress}
      padding="md"
      radius="md"
      selected={selected}
      variant="muted"
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: theme.spacing[1] }}>
          <Text numberOfLines={2} selectable variant="title">
            {prepList.name}
          </Text>
          {details ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {details}
            </Text>
          ) : null}
        </View>
        {showStatus && status ? <PrepStatusBadge namespace="prepLists" size="sm" status={status} /> : null}
      </View>
    </BaseCard>
  );
}
