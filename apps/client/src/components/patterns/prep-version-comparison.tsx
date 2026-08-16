import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ComparisonCard } from "@/components/patterns/comparison-card";
import { PrepListVersionCard } from "@/components/patterns/prep-list-version-card";
import {
  buildPrepVersionComparisonChanges,
  type PrepListProgressRecord,
  type PrepListVersionRecord,
  type PrepVersionComparisonChange,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepVersionComparisonProps = {
  accessibilityLabel?: string;
  baseProgress?: PrepListProgressRecord | null;
  baseVersion: PrepListVersionRecord;
  changes?: PrepVersionComparisonChange[];
  compact?: boolean;
  onSelectBase?: () => void | Promise<void>;
  onSelectTarget?: () => void | Promise<void>;
  targetProgress?: PrepListProgressRecord | null;
  targetVersion: PrepListVersionRecord;
};

export function PrepVersionComparison({
  accessibilityLabel,
  baseProgress,
  baseVersion,
  changes,
  compact = false,
  onSelectBase,
  onSelectTarget,
  targetProgress,
  targetVersion,
}: PrepVersionComparisonProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedChanges =
    changes?.length
      ? changes
      : buildPrepVersionComparisonChanges(baseVersion, targetVersion, t, i18n.language);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.versionComparison.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <PrepListVersionCard
        compact={compact}
        isCurrent={false}
        onPress={onSelectBase}
        progress={baseProgress}
        selected={false}
        version={baseVersion}
      />
      <PrepListVersionCard
        compact={compact}
        isCurrent
        onPress={onSelectTarget}
        progress={targetProgress}
        selected={false}
        version={targetVersion}
      />
      <ComparisonCard
        changes={resolvedChanges}
        subtitle={t("prep.versionComparison.subtitle")}
        title={t("prep.versionComparison.title")}
        variant="outlined"
      />
    </View>
  );
}
