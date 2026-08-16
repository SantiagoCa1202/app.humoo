import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { DetailCard } from "@/components/patterns/detail-card";
import { Badge } from "@/components/primitives/badge";
import {
  formatRecipeMeasurement,
  formatRecipeQuantity,
  getRecipeUnitLabel,
  type RecipeYieldRecord,
} from "@/features/recipes";
import type { DetailCardRow } from "@/components/patterns/detail-card";

export type RecipeYieldCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  onPress?: () => void | Promise<void>;
  primary?: boolean;
  yieldRecord: RecipeYieldRecord;
};

export function RecipeYieldCard({
  accessibilityLabel,
  compact = false,
  onPress,
  primary = false,
  yieldRecord,
}: RecipeYieldCardProps) {
  const { t, i18n } = useTranslation("common");
  const quantity = formatRecipeMeasurement(yieldRecord.quantity, yieldRecord.unit, i18n.language);
  const factorToBase =
    yieldRecord.factorToBase !== null && yieldRecord.factorToBase !== undefined
      ? formatRecipeQuantity(yieldRecord.factorToBase, i18n.language)
      : null;
  const rows = [
    {
      label: t("recipes.labels.quantity"),
      value: quantity ?? t("recipes.version.emptyValue"),
    },
    !compact && getRecipeUnitLabel(yieldRecord.unit)
      ? {
          label: t("recipes.labels.unit"),
          value: getRecipeUnitLabel(yieldRecord.unit),
        }
      : null,
    !compact && factorToBase
      ? {
          label: t("recipes.yields.factorToBase"),
          value: factorToBase,
        }
      : null,
  ].filter(Boolean) as DetailCardRow[];

  return (
    <DetailCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.yields.cardAccessibilityLabel")}
      onPress={onPress}
      rows={rows}
      subtitle={yieldRecord.label?.trim() || undefined}
      title={t("recipes.yields.title")}
      trailing={
        primary ? (
          <Badge label={t("recipes.yields.primary")} size="sm" variant="primary" />
        ) : undefined
      }
      variant={compact ? "default" : "elevated"}
    />
  );
}
