import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { RecipeStepItem } from "@/components/patterns/recipe-step-item";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { SkeletonText } from "@/components/primitives/skeleton-text";
import { sortRecipeSteps, type RecipeStepRecord } from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeStepsListProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  editable?: boolean;
  loading?: boolean;
  onEditStep?: (step: RecipeStepRecord) => void | Promise<void>;
  onRemoveStep?: (step: RecipeStepRecord) => void | Promise<void>;
  onStepPress?: (step: RecipeStepRecord) => void | Promise<void>;
  steps: RecipeStepRecord[];
};

export function RecipeStepsList({
  accessibilityLabel,
  compact = false,
  editable = false,
  loading = false,
  onEditStep,
  onRemoveStep,
  onStepPress,
  steps,
}: RecipeStepsListProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const orderedSteps = sortRecipeSteps(steps);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.steps.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="default"
    >
      <CardHeader subtitle={t("recipes.steps.subtitle")} title={t("recipes.steps.title")} />
      <CardContent topDivider>
        {loading ? (
          <View style={{ gap: theme.spacing[3] }}>
            {Array.from({ length: compact ? 3 : 4 }).map((_, index) => (
              <SkeletonText key={`recipe-step-skeleton-${index}`} lines={3} />
            ))}
          </View>
        ) : orderedSteps.length === 0 ? (
          <EmptyState
            compact
            description={t("recipes.steps.emptyDescription")}
            title={t("recipes.steps.emptyTitle")}
          />
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            {orderedSteps.map((step, index) => (
              <View key={step.id} style={{ gap: theme.spacing[3] }}>
                <RecipeStepItem
                  compact={compact}
                  editable={editable}
                  index={index}
                  onEdit={onEditStep ? () => void onEditStep(step) : undefined}
                  onPress={onStepPress ? () => void onStepPress(step) : undefined}
                  onRemove={onRemoveStep ? () => void onRemoveStep(step) : undefined}
                  step={step}
                />
                {index < orderedSteps.length - 1 ? <Divider spacing="none" /> : null}
              </View>
            ))}
          </View>
        )}
      </CardContent>
    </BaseCard>
  );
}
