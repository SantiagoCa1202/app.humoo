import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import {
  getMenuAllergenLabel,
  getMenuAllergenTone,
  hasMenuAllergenRisk,
  type MenuAllergenRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuAllergenSummaryProps = {
  accessibilityLabel?: string;
  allergens: MenuAllergenRecord[];
  compact?: boolean;
  showWarning?: boolean;
  unknownItemsCount?: number;
};

export function MenuAllergenSummary({
  accessibilityLabel,
  allergens,
  compact = false,
  showWarning = false,
  unknownItemsCount = 0,
}: MenuAllergenSummaryProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const hasRisk = hasMenuAllergenRisk(allergens);
  const hasIncompleteInfo = unknownItemsCount > 0;
  const shouldRenderAlert = showWarning && (hasRisk || hasIncompleteInfo);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("menus.allergens.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <BaseCard padding={compact ? "md" : "lg"} variant="default">
        <CardHeader
          subtitle={t("menus.allergens.subtitle")}
          title={t("menus.allergens.title")}
          trailing={
            <Badge
              label={new Intl.NumberFormat(i18n.language).format(allergens.length)}
              size="sm"
              variant={hasRisk ? "warning" : "neutral"}
            />
          }
        />
        <CardContent topDivider>
          <View style={{ gap: theme.spacing[3] }}>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {allergens.length ? (
                allergens.slice(0, compact ? 6 : allergens.length).map((allergen, index) => (
                  <Badge
                    key={allergen.id ?? allergen.code ?? `menu-allergen-${index}`}
                    label={getMenuAllergenLabel(allergen, t)}
                    size="sm"
                    variant={getMenuAllergenTone(allergen)}
                  />
                ))
              ) : (
                <Text tone="muted" variant="bodySmall">
                  {t("menus.allergens.empty")}
                </Text>
              )}
              {hasIncompleteInfo ? (
                <Badge
                  label={t("menus.allergens.unknownCount", { count: unknownItemsCount })}
                  size="sm"
                  variant="warning"
                />
              ) : null}
            </View>
            {allergens.some((allergen) => allergen.metadata?.trim()) ? (
              <View style={{ gap: theme.spacing[2] }}>
                {allergens
                  .filter((allergen) => allergen.metadata?.trim())
                  .slice(0, compact ? 2 : allergens.length)
                  .map((allergen, index) => (
                    <Text
                      key={allergen.id ?? allergen.code ?? `menu-allergen-meta-${index}`}
                      tone="secondary"
                      variant="caption"
                    >
                      {`${getMenuAllergenLabel(allergen, t)}: ${allergen.metadata?.trim()}`}
                    </Text>
                  ))}
              </View>
            ) : null}
          </View>
        </CardContent>
      </BaseCard>
      {shouldRenderAlert ? (
        <AlertCard
          description={
            hasIncompleteInfo
              ? t("menus.allergens.incompleteDescription", { count: unknownItemsCount })
              : t("menus.allergens.riskDescription")
          }
          title={
            hasIncompleteInfo
              ? t("menus.allergens.incompleteTitle")
              : t("menus.allergens.riskTitle")
          }
          tone={hasRisk && !hasIncompleteInfo ? "error" : "warning"}
          variant="muted"
        />
      ) : null}
    </View>
  );
}
