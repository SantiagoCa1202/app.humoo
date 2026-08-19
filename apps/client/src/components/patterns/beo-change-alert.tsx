import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  getBeoChangeAlertTone,
  getBeoImpactTitle,
  type BEOChangeSeverity,
  type BEOImpactRecord,
  type BeoVersionRecord,
} from "@/features/documents";
import { getBeoVersionLabel } from "@/features/documents";
import { useAppTheme } from "@/theme/ThemeProvider";

export type BEOChangeAlertProps = {
  accessibilityLabel?: string;
  changeCount?: number | null;
  compact?: boolean;
  impacts?: BEOImpactRecord[] | null;
  newVersion?: BeoVersionRecord | null;
  onDismiss?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
  previousVersion?: BeoVersionRecord | null;
  severity?: BEOChangeSeverity | null;
};

export function BEOChangeAlert({
  accessibilityLabel,
  changeCount,
  compact = false,
  impacts,
  newVersion,
  onDismiss,
  onReview,
  previousVersion,
  severity = "warning",
}: BEOChangeAlertProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <AlertCard
      accessibilityLabel={accessibilityLabel ?? t("documents.changeAlert.accessibilityLabel")}
      description={
        <View style={{ gap: theme.spacing[3] }}>
          <Text selectable variant="bodySmall">
            {t("documents.changeAlert.description", {
              count: changeCount ?? 0,
            })}
          </Text>
          {previousVersion || newVersion ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {previousVersion ? (
                <Badge
                  label={t("documents.changeAlert.previousVersion", {
                    version: getBeoVersionLabel(previousVersion, t),
                  })}
                  size="sm"
                  variant="neutral"
                />
              ) : null}
              {newVersion ? (
                <Badge
                  label={t("documents.changeAlert.newVersion", {
                    version: getBeoVersionLabel(newVersion, t),
                  })}
                  size="sm"
                  variant="primary"
                />
              ) : null}
            </View>
          ) : null}
          {impacts?.length ? (
            <View style={{ gap: theme.spacing[2] }}>
              <Text tone="secondary" variant="overline">
                {t("documents.changeAlert.impactTitle")}
              </Text>
              {impacts.slice(0, compact ? 2 : 4).map((impact) => (
                <Text key={impact.id} selectable variant="bodySmall">
                  {getBeoImpactTitle(impact, t)}
                  {impact.summary?.trim() ? `: ${impact.summary.trim()}` : ""}
                </Text>
              ))}
            </View>
          ) : null}
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
            {onReview ? (
              <Button
                label={t("documents.changeAlert.reviewChanges")}
                onPress={onReview}
                size="sm"
                variant="primary"
              />
            ) : null}
            {onDismiss ? (
              <Button
                label={t("documents.changeAlert.dismiss")}
                onPress={onDismiss}
                size="sm"
                variant="ghost"
              />
            ) : null}
          </View>
        </View>
      }
      title={t("documents.changeAlert.title")}
      tone={getBeoChangeAlertTone(severity)}
      variant="muted"
    />
  );
}
