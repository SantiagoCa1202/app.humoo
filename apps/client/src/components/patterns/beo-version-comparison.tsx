import { View } from "react-native";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";

import { BEOFieldComparison } from "@/components/patterns/beo-field-comparison";
import { BEOVersionCard } from "@/components/patterns/beo-version-card";
import { Badge } from "@/components/primitives/badge";
import { Text } from "@/components/primitives/text";
import {
  buildBeoChangeSummary,
  buildBeoVersionComparisonSections,
  type BEOFieldChangeRecord,
  type BeoVersionRecord,
  type BEOVersionComparisonSection,
} from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOVersionComparisonProps = {
  accessibilityLabel?: string;
  baseVersion: BeoVersionRecord;
  changes: BEOFieldChangeRecord[];
  compact?: boolean;
  onFieldPress?: (change: BEOFieldChangeRecord) => void | Promise<void>;
  onSelectBase?: () => void | Promise<void>;
  onSelectTarget?: () => void | Promise<void>;
  sections?: BEOVersionComparisonSection[] | null;
  targetVersion: BeoVersionRecord;
};

export function BEOVersionComparison({
  accessibilityLabel,
  baseVersion,
  changes,
  compact = false,
  onFieldPress,
  onSelectBase,
  onSelectTarget,
  sections,
  targetVersion,
}: BEOVersionComparisonProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const summary = useMemo(() => buildBeoChangeSummary(changes), [changes]);
  const resolvedSections = useMemo(
    () => sections?.length ? sections : buildBeoVersionComparisonSections(changes, t),
    [changes, sections, t]
  );
  const changeMap = useMemo(() => new Map(changes.map((change) => [change.id, change])), [changes]);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("documents.comparison.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <BEOVersionCard
        compact={compact}
        isCurrent={false}
        onPress={onSelectBase}
        version={baseVersion}
      />
      <BEOVersionCard compact={compact} isCurrent onPress={onSelectTarget} version={targetVersion} />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
        <Badge
          label={t("documents.comparison.summary.changed", { count: summary.changed })}
          size="sm"
          variant="warning"
        />
        <Badge
          label={t("documents.comparison.summary.added", { count: summary.added })}
          size="sm"
          variant="success"
        />
        <Badge
          label={t("documents.comparison.summary.removed", { count: summary.removed })}
          size="sm"
          variant="danger"
        />
      </View>
      {resolvedSections.map((section) => (
        <View key={section.id} style={{ gap: theme.spacing[2] }}>
          <Text variant="title">{section.title}</Text>
          {section.description ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {section.description}
            </Text>
          ) : null}
          {section.changeIds.map((changeId) => {
            const change = changeMap.get(changeId);

            if (!change) {
              return null;
            }

            return (
              <BEOFieldComparison
                key={change.id}
                change={change}
                compact={compact}
                onPress={onFieldPress ? () => onFieldPress(change) : undefined}
              />
            );
          })}
        </View>
      ))}
    </View>
  );
}
