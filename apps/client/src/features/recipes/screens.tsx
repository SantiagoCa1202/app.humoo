import { router, useLocalSearchParams, type Href } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { RecipeAllergenSummary } from "@/components/patterns/recipe-allergen-summary";
import { RecipeConflictAlert } from "@/components/patterns/recipe-conflict-alert";
import { RecipeCostSummary } from "@/components/patterns/recipe-cost-summary";
import { RecipeDetailHeader } from "@/components/patterns/recipe-detail-header";
import { RecipeEditorForm } from "@/components/patterns/recipe-editor-form";
import { RecipeIngredientsList } from "@/components/patterns/recipe-ingredients-list";
import { RecipeList } from "@/components/patterns/recipe-list";
import { RecipeScaler } from "@/components/patterns/recipe-scaler";
import { RecipeStepsList } from "@/components/patterns/recipe-steps-list";
import { RecipeSummaryCard } from "@/components/patterns/recipe-summary-card";
import { RecipeVersionCard } from "@/components/patterns/recipe-version-card";
import { RecipeVersionComparison } from "@/components/patterns/recipe-version-comparison";
import { RecipeYieldCard } from "@/components/patterns/recipe-yield-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { Button } from "@/components/primitives/button";
import {
  createRecipeEditorValues,
  getRecipeDefaultYield,
  getRecipeIngredientKey,
  getRecipeStepKey,
  getRecipeYieldKey,
  type RecipeEditorValidationErrors,
  type RecipeEditorValues,
  type RecipeYieldRecord,
  useCreateRecipe,
  useRecipe,
  useRecipeCatalog,
  useRecipeComparison,
  useRecipeVersion,
  useRecipeVersions,
  useRecipes,
  useUpdateRecipe,
} from "@/features/recipes";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapRecipeValidationErrors(values: RecipeEditorValues, error: unknown): RecipeEditorValidationErrors {
  if (!isApiError(error) || !error.fieldErrors) {
    return {
      form: error instanceof Error ? error.message : undefined,
    };
  }

  const nextErrors: RecipeEditorValidationErrors = {
    form: error.message,
  };

  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    const message = messages[0];

    if (!message) {
      continue;
    }

    if (field === "name" || field === "description" || field === "recipe_code" || field === "category" || field === "type" || field === "status") {
      const targetKey = field === "recipe_code" ? "recipeCode" : field;
      (nextErrors as Record<string, string | undefined>)[targetKey] = message;
      continue;
    }

    if (field === "version.name" || field === "version.description" || field === "version.change_summary" || field === "version.prep_time_minutes" || field === "version.cook_time_minutes" || field === "version.rest_time_minutes" || field === "version.total_time_minutes" || field === "version.status") {
      nextErrors.version = {
        ...(nextErrors.version ?? {}),
        [field.replace("version.", "").replace("_summary", "Summary").replace("_time_minutes", "TimeMinutes")]: message,
      } as RecipeEditorValidationErrors["version"];
      continue;
    }

    let match = field.match(/^version\.ingredients\.(\d+)\.(.+)$/);
    if (match) {
      const ingredient = values.version.ingredients?.[Number(match[1])];
      if (!ingredient) {
        continue;
      }

      const key = getRecipeIngredientKey(ingredient);
      const ingredientField = match[2].replace(/_([a-z])/g, (_, char: string) => char.toUpperCase());
      nextErrors.version = nextErrors.version ?? {};
      nextErrors.version.ingredients = nextErrors.version.ingredients ?? {};
      nextErrors.version.ingredients[key] = {
        ...(nextErrors.version.ingredients[key] ?? {}),
        [ingredientField === "unitId" ? "unitId" : ingredientField]: message,
      };
      continue;
    }

    match = field.match(/^version\.steps\.(\d+)\.(.+)$/);
    if (match) {
      const step = values.version.steps?.[Number(match[1])];
      if (!step) {
        continue;
      }

      const key = getRecipeStepKey(step);
      const stepField = match[2].replace(/_([a-z])/g, (_, char: string) => char.toUpperCase());
      nextErrors.version = nextErrors.version ?? {};
      nextErrors.version.steps = nextErrors.version.steps ?? {};
      nextErrors.version.steps[key] = {
        ...(nextErrors.version.steps[key] ?? {}),
        [stepField]: message,
      };
      continue;
    }

    match = field.match(/^version\.yields\.(\d+)\.(.+)$/);
    if (match) {
      const yieldRecord = values.version.yields?.[Number(match[1])];
      if (!yieldRecord) {
        continue;
      }

      const key = getRecipeYieldKey(yieldRecord);
      const yieldField = match[2].replace(/_([a-z])/g, (_, char: string) => char.toUpperCase());
      nextErrors.version = nextErrors.version ?? {};
      nextErrors.version.yields = nextErrors.version.yields ?? {};
      nextErrors.version.yields[key] = {
        ...(nextErrors.version.yields[key] ?? {}),
        [yieldField === "unitId" ? "unitId" : yieldField]: message,
      };
    }
  }

  return nextErrors;
}

function useRecipeOptions() {
  const catalogQuery = useRecipeCatalog();
  const unitOptions = useMemo(
    () =>
      (catalogQuery.data?.units ?? []).map((unit) => ({
        label: unit.symbol?.trim() || unit.name?.trim() || unit.key?.trim() || "",
        value: unit.id ?? "",
      })),
    [catalogQuery.data?.units]
  );
  const tagOptions = useMemo(
    () =>
      (catalogQuery.data?.tags ?? []).map((tag) => ({
        label: tag.name?.trim() || tag.key?.trim() || tag.id,
        value: tag.id,
      })),
    [catalogQuery.data?.tags]
  );
  const allergenOptions = useMemo(
    () =>
      (catalogQuery.data?.allergens ?? []).map((allergen) => ({
        key: allergen.key ?? null,
        label: allergen.name?.trim() || allergen.key?.trim() || allergen.id,
        metadata: allergen.metadata ?? null,
        name: allergen.name ?? null,
        value: allergen.id,
      })),
    [catalogQuery.data?.allergens]
  );

  return { allergenOptions, catalogQuery, tagOptions, unitOptions };
}

export function RecipeListScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const canView = hasPermission("recipes.view");
  const canCreate = hasPermission("recipes.create");
  const recipesQuery = useRecipes();

  if (!canView) {
    return (
      <AppShell subtitle={t("recipes.subtitle")} title={t("recipes.title")}>
        <ForbiddenState
          description={t("recipes.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("recipes.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("recipes.subtitle")} title={t("recipes.title")}>
      <SectionCard
        action={
          canCreate ? (
            <Button
              label={t("recipes.actions.create")}
              onPress={() => router.push(routes.app.recipeCreate)}
              size="sm"
            />
          ) : null
        }
        description={t("recipes.listDescription")}
        title={t("recipes.listTitle", { count: recipesQuery.recipes.length })}
      >
        <RecipeList
          error={recipesQuery.isError && recipesQuery.error instanceof Error ? recipesQuery.error.message : undefined}
          loading={recipesQuery.isLoading}
          onEndReached={() => {
            if (recipesQuery.hasNextPage && !recipesQuery.isFetchingNextPage) {
              void recipesQuery.fetchNextPage();
            }
          }}
          onRecipePress={(recipe) =>
            router.push({
              pathname: routes.app.recipeDetail,
              params: { recipeId: recipe.id },
            } as Href)
          }
          onRefresh={async () => {
            await recipesQuery.refetch();
          }}
          recipes={recipesQuery.recipes.map((recipe) => ({
            currentVersion: recipe.currentVersionRecord,
            recipe,
          }))}
          refreshing={recipesQuery.isRefetching}
        />
      </SectionCard>
    </AppShell>
  );
}

export function RecipeCreateScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const canCreate = hasPermission("recipes.create");
  const { allergenOptions, catalogQuery, tagOptions, unitOptions } = useRecipeOptions();
  const createMutation = useCreateRecipe();
  const [validationErrors, setValidationErrors] = useState<RecipeEditorValidationErrors>({});

  if (!canCreate) {
    return (
      <AppShell subtitle={t("recipes.createSubtitle")} title={t("recipes.createTitle")}>
        <ForbiddenState
          description={t("recipes.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("recipes.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("recipes.createSubtitle")} title={t("recipes.createTitle")}>
      {catalogQuery.isLoading ? <LoadingState title={t("recipes.loading")} /> : null}
      {catalogQuery.isError ? (
        <ErrorState
          detail={catalogQuery.error instanceof Error ? catalogQuery.error.message : undefined}
          onRetry={async () => {
            await catalogQuery.refetch();
          }}
          title={t("recipes.errorTitle")}
        />
      ) : null}
      {catalogQuery.data ? (
        <RecipeEditorForm
          allergenOptions={allergenOptions}
          mode="create"
          onCancel={() => router.back()}
          onSubmit={async (values) => {
            setValidationErrors({});
            try {
              const result = await createMutation.mutateAsync(values);
              router.replace({
                pathname: routes.app.recipeDetail,
                params: { recipeId: result.recipe.id },
              } as Href);
            } catch (error) {
              setValidationErrors(mapRecipeValidationErrors(values, error));
            }
          }}
          submitting={createMutation.isPending}
          tagOptions={tagOptions}
          unitOptions={unitOptions}
          validationErrors={validationErrors}
        />
      ) : null}
    </AppShell>
  );
}

export function RecipeDetailScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const recipeId = resolveRouteParam(useLocalSearchParams<{ recipeId?: string }>().recipeId);
  const canView = hasPermission("recipes.view");
  const canEdit = hasPermission("recipes.edit");
  const recipeQuery = useRecipe(recipeId);
  const versionsQuery = useRecipeVersions(recipeId);
  const detail = recipeQuery.data ?? null;
  const recipe = detail?.recipe ?? null;
  const currentVersion = detail?.currentVersion ?? recipe?.currentVersionRecord ?? null;
  const versions = versionsQuery.data ?? [];
  const defaultYield = getRecipeDefaultYield(currentVersion);
  const [targetYield, setTargetYield] = useState<RecipeYieldRecord | null>(defaultYield);

  useEffect(() => {
    setTargetYield(defaultYield ?? null);
  }, [defaultYield]);

  if (!canView) {
    return (
      <AppShell subtitle={t("recipes.detailSubtitle")} title={t("recipes.detailTitle")}>
        <ForbiddenState
          description={t("recipes.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("recipes.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!recipeId) {
    return (
      <AppShell subtitle={t("recipes.detailSubtitle")} title={t("recipes.detailTitle")}>
        <ErrorState detail={t("recipes.missingIdentifierDescription")} title={t("recipes.missingIdentifierTitle")} />
      </AppShell>
    );
  }

  const ingredientCosts = (currentVersion?.ingredients ?? []).map((ingredient) => ({
    cost: ingredient.extendedCost ?? ingredient.unitCost ?? null,
    id: ingredient.id ?? ingredient.clientId ?? ingredient.ingredientName,
    name: ingredient.ingredientName,
  }));
  const missingCostCount = ingredientCosts.filter((ingredient) => ingredient.cost === null || ingredient.cost === undefined).length;

  return (
    <AppShell subtitle={t("recipes.detailSubtitle")} title={recipe?.name ?? t("recipes.detailTitle")}>
      <View style={{ gap: spacing[4] }}>
        {(recipeQuery.isLoading || versionsQuery.isLoading) ? <LoadingState title={t("recipes.loading")} /> : null}
        {recipeQuery.isError || versionsQuery.isError ? (
          <ErrorState
            detail={recipeQuery.error instanceof Error ? recipeQuery.error.message : versionsQuery.error instanceof Error ? versionsQuery.error.message : undefined}
            onRetry={async () => {
              await Promise.all([recipeQuery.refetch(), versionsQuery.refetch()]);
            }}
            title={t("recipes.errorTitle")}
          />
        ) : null}
        {recipe && currentVersion ? (
          <>
            <RecipeDetailHeader
              actions={
                canEdit ? (
                  <Button
                    label={t("recipes.actions.edit")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.recipeEdit,
                        params: { recipeId: recipe.id },
                      } as Href)
                    }
                    size="sm"
                  />
                ) : null
              }
              currentVersion={currentVersion}
              recipe={recipe}
            />
            <RecipeSummaryCard currentVersion={currentVersion} recipe={recipe} />
            <RecipeIngredientsList ingredients={currentVersion.ingredients ?? []} />
            <RecipeStepsList steps={currentVersion.steps ?? []} />
            {(currentVersion.yields ?? []).length ? (
              <SectionCard
                description={t("recipes.yieldsDescription")}
                title={t("recipes.yieldsTitle")}
              >
                <View style={{ gap: spacing[3] }}>
                  {(currentVersion.yields ?? []).map((yieldRecord, index) => (
                    <RecipeYieldCard key={yieldRecord.id ?? `${yieldRecord.label}-${index}`} primary={Boolean(yieldRecord.isDefault)} yieldRecord={yieldRecord} />
                  ))}
                </View>
              </SectionCard>
            ) : null}
            <RecipeAllergenSummary allergens={currentVersion.allergens ?? []} showWarning />
            <RecipeCostSummary
              costPerYield={currentVersion.estimatedCostPerYield}
              currency={currentVersion.costCurrency}
              estimated={missingCostCount > 0}
              ingredientCosts={ingredientCosts}
              missingCostCount={missingCostCount}
              totalCost={currentVersion.estimatedTotalCost}
            />
            {defaultYield && targetYield ? (
              <RecipeScaler
                baseYield={defaultYield}
                onTargetYieldChange={setTargetYield}
                recipe={recipe}
                targetYield={targetYield}
                unitOptions={(detail?.catalog?.units ?? []).map((unit) => ({
                  label: unit.symbol?.trim() || unit.name?.trim() || unit.key?.trim() || "",
                  value: unit.id ?? "",
                }))}
                version={currentVersion}
              />
            ) : null}
            {versions.length ? (
              <SectionCard
                description={t("recipes.historyDescription")}
                title={t("recipes.historyTitle")}
              >
                <View style={{ gap: spacing[3] }}>
                  {versions.map((version) => (
                    <RecipeVersionCard
                      key={version.id ?? version.version}
                      isCurrent={version.id === currentVersion.id}
                      onCompare={() =>
                        router.push({
                          pathname: routes.app.recipeVersion,
                          params: { recipeId: recipe.id, versionId: version.id ?? "" },
                        } as Href)
                      }
                      onPress={() =>
                        router.push({
                          pathname: routes.app.recipeVersion,
                          params: { recipeId: recipe.id, versionId: version.id ?? "" },
                        } as Href)
                      }
                      version={version}
                    />
                  ))}
                </View>
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function RecipeEditScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const recipeId = resolveRouteParam(useLocalSearchParams<{ recipeId?: string }>().recipeId);
  const canEdit = hasPermission("recipes.edit");
  const recipeQuery = useRecipe(recipeId);
  const { allergenOptions, catalogQuery, tagOptions, unitOptions } = useRecipeOptions();
  const updateMutation = useUpdateRecipe(recipeId ?? "");
  const [validationErrors, setValidationErrors] = useState<RecipeEditorValidationErrors>({});

  if (!canEdit) {
    return (
      <AppShell subtitle={t("recipes.editSubtitle")} title={t("recipes.editTitle")}>
        <ForbiddenState
          description={t("recipes.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("recipes.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  const detail = recipeQuery.data ?? null;
  const currentVersion = detail?.currentVersion ?? detail?.recipe.currentVersionRecord ?? null;
  const initialValues = detail && currentVersion ? createRecipeEditorValues(detail.recipe, currentVersion) : null;

  return (
    <AppShell subtitle={t("recipes.editSubtitle")} title={t("recipes.editTitle")}>
      {(recipeQuery.isLoading || catalogQuery.isLoading) ? <LoadingState title={t("recipes.loading")} /> : null}
      {recipeQuery.isError || catalogQuery.isError ? (
        <ErrorState
          detail={recipeQuery.error instanceof Error ? recipeQuery.error.message : catalogQuery.error instanceof Error ? catalogQuery.error.message : undefined}
          onRetry={async () => {
            await Promise.all([recipeQuery.refetch(), catalogQuery.refetch()]);
          }}
          title={t("recipes.errorTitle")}
        />
      ) : null}
      {updateMutation.isError && isApiError(updateMutation.error) && updateMutation.error.kind === "conflict" ? (
        <RecipeConflictAlert
          conflictType="version_conflict"
          onReload={async () => {
            setValidationErrors({});
            await recipeQuery.refetch();
          }}
        />
      ) : null}
      {initialValues ? (
        <RecipeEditorForm
          allergenOptions={allergenOptions}
          initialRecipe={detail!.recipe}
          initialVersion={currentVersion ?? undefined}
          mode="edit"
          onCancel={() => router.back()}
          onSubmit={async (values) => {
            setValidationErrors({});
            try {
              const result = await updateMutation.mutateAsync(values);
              router.replace({
                pathname: routes.app.recipeDetail,
                params: { recipeId: result.recipe.id },
              } as Href);
            } catch (error) {
              setValidationErrors(mapRecipeValidationErrors(values, error));
            }
          }}
          submitting={updateMutation.isPending}
          tagOptions={tagOptions}
          unitOptions={unitOptions}
          validationErrors={validationErrors}
        />
      ) : null}
    </AppShell>
  );
}

export function RecipeVersionScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const params = useLocalSearchParams<{ recipeId?: string; versionId?: string }>();
  const recipeId = resolveRouteParam(params.recipeId);
  const versionId = resolveRouteParam(params.versionId);
  const canView = hasPermission("recipes.view");
  const versionQuery = useRecipeVersion(recipeId, versionId);
  const comparisonQuery = useRecipeComparison(recipeId, versionId);

  if (!canView) {
    return (
      <AppShell subtitle={t("recipes.versionSubtitle")} title={t("recipes.versionTitle")}>
        <ForbiddenState
          description={t("recipes.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("recipes.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!recipeId || !versionId) {
    return (
      <AppShell subtitle={t("recipes.versionSubtitle")} title={t("recipes.versionTitle")}>
        <ErrorState detail={t("recipes.missingIdentifierDescription")} title={t("recipes.missingIdentifierTitle")} />
      </AppShell>
    );
  }

  const version = versionQuery.data ?? null;
  const comparison = comparisonQuery.data ?? null;

  return (
    <AppShell subtitle={t("recipes.versionSubtitle")} title={t("recipes.versionTitle")}>
      <View style={{ gap: spacing[4] }}>
        {(versionQuery.isLoading || comparisonQuery.isLoading) ? <LoadingState title={t("recipes.loading")} /> : null}
        {versionQuery.isError || comparisonQuery.isError ? (
          <ErrorState
            detail={versionQuery.error instanceof Error ? versionQuery.error.message : comparisonQuery.error instanceof Error ? comparisonQuery.error.message : undefined}
            onRetry={async () => {
              await Promise.all([versionQuery.refetch(), comparisonQuery.refetch()]);
            }}
            title={t("recipes.errorTitle")}
          />
        ) : null}
        {version ? (
          <>
            <RecipeVersionCard isCurrent version={version} />
            <RecipeIngredientsList ingredients={version.ingredients ?? []} />
            <RecipeStepsList steps={version.steps ?? []} />
            {(version.yields ?? []).length ? (
              <SectionCard
                description={t("recipes.yieldsDescription")}
                title={t("recipes.yieldsTitle")}
              >
                <View style={{ gap: spacing[3] }}>
                  {(version.yields ?? []).map((yieldRecord, index) => (
                    <RecipeYieldCard key={yieldRecord.id ?? `${yieldRecord.label}-${index}`} primary={Boolean(yieldRecord.isDefault)} yieldRecord={yieldRecord} />
                  ))}
                </View>
              </SectionCard>
            ) : null}
          </>
        ) : null}
        {comparison?.baseVersion ? (
          <RecipeVersionComparison
            baseVersion={comparison.baseVersion}
            changes={comparison.changes}
            targetVersion={comparison.targetVersion}
          />
        ) : !comparisonQuery.isLoading ? (
          <EmptyState
            description={t("recipes.comparisonEmptyDescription")}
            title={t("recipes.comparisonEmptyTitle")}
          />
        ) : null}
      </View>
    </AppShell>
  );
}
