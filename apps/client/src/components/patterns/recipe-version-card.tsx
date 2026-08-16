import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { EntityCard, type EntityCardMetadataItem } from "@/components/patterns/entity-card";
import {
  formatRecipeDateTime,
  formatRecipeYield,
  getRecipeDefaultYield,
  getRecipeIngredientCount,
  getRecipeStepCount,
  getRecipeVersionLabel,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeVersionCardProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  isCurrent?: boolean;
  onCompare?: () => void | Promise<void>;
  onPress?: () => void | Promise<void>;
  selected?: boolean;
  version: RecipeVersionRecord;
};

export function RecipeVersionCard({
  accessibilityLabel,
  actions,
  compact = false,
  isCurrent = false,
  onCompare,
  onPress,
  selected = false,
  version,
}: RecipeVersionCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const createdAt = formatRecipeDateTime(version.createdAt, i18n.language);
  const primaryYield = formatRecipeYield(getRecipeDefaultYield(version), i18n.language);
  const metadata: EntityCardMetadataItem[] = [
    createdAt
      ? {
          label: t("recipes.version.fields.createdAt"),
          value: createdAt,
        }
      : null,
    version.createdBy?.name?.trim()
      ? {
          label: t("recipes.version.fields.createdBy"),
          value: version.createdBy.name.trim(),
        }
      : null,
    primaryYield
      ? {
          label: t("recipes.labels.yield"),
          value: primaryYield,
        }
      : null,
    typeof getRecipeIngredientCount(version) === "number"
      ? {
          label: t("recipes.labels.ingredients"),
          value: t("recipes.metrics.ingredients", { count: getRecipeIngredientCount(version) }),
        }
      : null,
    typeof getRecipeStepCount(version) === "number"
      ? {
          label: t("recipes.labels.steps"),
          value: t("recipes.metrics.steps", { count: getRecipeStepCount(version) }),
        }
      : null,
  ].filter(Boolean) as EntityCardMetadataItem[];

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.version.cardAccessibilityLabel")}
      leading={
        version.createdBy?.name?.trim() ? (
          <Avatar
            name={version.createdBy.name}
            size={compact ? "sm" : "md"}
            source={version.createdBy.source}
          />
        ) : undefined
      }
      metadata={metadata}
      onPress={onPress}
      selected={selected}
      subtitle={version.changeSummary?.trim() || version.description?.trim() || undefined}
      title={getRecipeVersionLabel(version, t) ?? t("recipes.version.current")}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          {actions}
          {isCurrent ? (
            <Badge label={t("recipes.version.current")} size="sm" variant="primary" />
          ) : null}
          {onCompare ? (
            <Button
              label={t("recipes.version.compare")}
              onPress={onCompare}
              size="sm"
              variant="ghost"
            />
          ) : null}
        </View>
      }
      variant={compact ? "default" : "elevated"}
    />
  );
}
