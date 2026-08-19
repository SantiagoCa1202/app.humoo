import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { getBeoImpactTitle, type BEOImpactRecord } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOImpactSummaryProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  impacts: BEOImpactRecord[];
  onImpactPress?: (impact: BEOImpactRecord) => void | Promise<void>;
};

export function BEOImpactSummary({
  accessibilityLabel,
  compact = false,
  impacts,
  onImpactPress,
}: BEOImpactSummaryProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("documents.impact.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="outlined"
    >
      <CardHeader
        padding="none"
        subtitle={t("documents.impact.subtitle")}
        title={t("documents.impact.title")}
      />
      <CardContent padding="none" topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {impacts.length ? (
            impacts.map((impact, index) => (
              <View key={impact.id} style={{ gap: theme.spacing[2] }}>
                <View
                  style={{
                    alignItems: "flex-start",
                    flexDirection: "row",
                    flexWrap: "wrap",
                    gap: theme.spacing[2],
                    justifyContent: "space-between",
                  }}
                >
                  <View style={{ flex: 1, gap: theme.spacing[2], minWidth: 180 }}>
                    <Text variant="title">{getBeoImpactTitle(impact, t)}</Text>
                    {impact.summary?.trim() ? (
                      <Text selectable variant="bodySmall">
                        {impact.summary.trim()}
                      </Text>
                    ) : (
                      <Text selectable tone="muted" variant="bodySmall">
                        {t("documents.impact.noImpact")}
                      </Text>
                    )}
                  </View>
                  <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
                    {impact.severity ? (
                      <Badge
                        label={t(`documents.impact.severity.${impact.severity}`)}
                        size="sm"
                        variant={impact.severity}
                      />
                    ) : null}
                    {impact.requiresReview ? (
                      <Badge
                        label={t("documents.impact.requiresReview")}
                        size="sm"
                        variant="warning"
                      />
                    ) : null}
                    {impact.requiresRegeneration ? (
                      <Badge
                        label={t("documents.impact.requiresRegeneration")}
                        size="sm"
                        variant="warning"
                      />
                    ) : null}
                  </View>
                </View>
                {onImpactPress ? (
                  <Button
                    label={t("documents.impact.reviewImpact")}
                    onPress={() => onImpactPress(impact)}
                    size="sm"
                    variant="secondary"
                  />
                ) : null}
                {index < impacts.length - 1 ? <Divider spacing="none" /> : null}
              </View>
            ))
          ) : (
            <Text selectable tone="secondary" variant="bodySmall">
              {t("documents.impact.empty")}
            </Text>
          )}
        </View>
      </CardContent>
    </BaseCard>
  );
}
