import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { Badge } from "@/components/primitives/badge";
import { Divider } from "@/components/primitives/divider";
import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import { RecipeStatusBadge } from "@/components/patterns/recipe-status-badge";
import {
  formatRecipeDateTime,
  formatRecipeYield,
  getRecipeDefaultYield,
  getRecipeStatus,
  getRecipeTagLabel,
  getRecipeVersionLabel,
  type RecipeDisplayRecord,
  type RecipeVersionRecord,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeDetailHeaderProps = {
  accessibilityLabel?: string;
  actions?: React.ReactNode;
  compact?: boolean;
  currentVersion?: RecipeVersionRecord | null;
  recipe: RecipeDisplayRecord;
};

export function RecipeDetailHeader({
  accessibilityLabel,
  actions,
  compact = false,
  currentVersion,
  recipe,
}: RecipeDetailHeaderProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const status = getRecipeStatus(recipe);
  const recipeYield = formatRecipeYield(getRecipeDefaultYield(currentVersion), i18n.language);
  const createdBy = recipe.createdBy?.name?.trim() || currentVersion?.createdBy?.name?.trim() || null;
  const updatedAt = formatRecipeDateTime(recipe.updatedAt, i18n.language);
  const tags = recipe.tags?.map(getRecipeTagLabel).filter(Boolean) ?? [];
  const versionLabel = getRecipeVersionLabel(currentVersion, t);

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("recipes.detailHeader.accessibilityLabel")}
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
            subtitle={currentVersion?.description ?? recipe.description ?? undefined}
            title={recipe.name}
          />
          {status ? <RecipeStatusBadge size={compact ? "sm" : "md"} status={status} /> : null}
        </View>
        {actions ? <View>{actions}</View> : null}
      </View>
      <Divider spacing="none" />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
        {recipeYield ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("recipes.labels.yield")}
            </Text>
            <Text selectable variant="bodySmall">
              {recipeYield}
            </Text>
          </View>
        ) : null}
        {createdBy ? (
          <View style={{ gap: theme.spacing[2] }}>
            <Text tone="muted" variant="caption">
              {t("recipes.labels.createdBy")}
            </Text>
            <View style={{ alignItems: "center", flexDirection: "row", gap: theme.spacing[2] }}>
              <Avatar
                name={createdBy}
                size="sm"
                source={recipe.createdBy?.source ?? currentVersion?.createdBy?.source}
              />
              <Text selectable variant="bodySmall">
                {createdBy}
              </Text>
            </View>
          </View>
        ) : null}
        {updatedAt ? (
          <View style={{ gap: theme.spacing[1] }}>
            <Text tone="muted" variant="caption">
              {t("recipes.labels.updatedAt")}
            </Text>
            <Text selectable variant="bodySmall">
              {updatedAt}
            </Text>
          </View>
        ) : null}
      </View>
      {tags.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
          {tags.map((tag) => (
            <Badge key={tag} label={tag} size="sm" variant="neutral" />
          ))}
        </View>
      ) : null}
    </View>
  );
}
