import { router, useLocalSearchParams, type Href } from "expo-router";
import { useDeferredValue, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { MenuAllergenSummary } from "@/components/patterns/menu-allergen-summary";
import { MenuConflictAlert } from "@/components/patterns/menu-conflict-alert";
import { MenuDetailHeader } from "@/components/patterns/menu-detail-header";
import { MenuDuplicateAction } from "@/components/patterns/menu-duplicate-action";
import { MenuEditorForm } from "@/components/patterns/menu-editor-form";
import { MenuEventLinkCard } from "@/components/patterns/menu-event-link-card";
import { MenuList } from "@/components/patterns/menu-list";
import { MenuRecipeSummary } from "@/components/patterns/menu-recipe-summary";
import { MenuSection } from "@/components/patterns/menu-section";
import { MenuSummaryCard } from "@/components/patterns/menu-summary-card";
import { MenuVersionCard } from "@/components/patterns/menu-version-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { Button } from "@/components/primitives/button";
import { SearchInput } from "@/components/primitives/search-input";
import { useEvents } from "@/features/events";
import {
  createMenuEditorValues,
  getMenuItemKey,
  getMenuSectionKey,
  type MenuEditorValidationErrors,
  type MenuEditorValues,
  useCreateMenu,
  useDuplicateMenu,
  useMenu,
  useMenus,
  useMenuVersions,
  useUpdateMenu,
} from "@/features/menus";
import { useRecipes } from "@/features/recipes";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapMenuValidationErrors(values: MenuEditorValues, error: unknown): MenuEditorValidationErrors {
  if (!isApiError(error) || !error.fieldErrors) {
    return {
      form: error instanceof Error ? error.message : undefined,
    };
  }

  const nextErrors: MenuEditorValidationErrors = {
    form: error.message,
  };

  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    const message = messages[0];

    if (!message) {
      continue;
    }

    if (field === "name" || field === "description" || field === "status" || field === "event_id") {
      const targetKey = field === "event_id" ? "eventId" : field;
      (nextErrors as Record<string, string | undefined>)[targetKey] = message;
      continue;
    }

    let match = field.match(/^sections\.(\d+)\.name$/);
    if (match) {
      const section = values.sections?.[Number(match[1])];
      if (!section) {
        continue;
      }

      const key = getMenuSectionKey(section);
      nextErrors.sections = nextErrors.sections ?? {};
      nextErrors.sections[key] = {
        ...(nextErrors.sections[key] ?? {}),
        name: message,
      };
      continue;
    }

    match = field.match(/^sections\.(\d+)\.items\.(\d+)\.(.+)$/);
    if (match) {
      const section = values.sections?.[Number(match[1])];
      const item = section?.items?.[Number(match[2])];
      if (!section || !item) {
        continue;
      }

      const sectionKey = getMenuSectionKey(section);
      const itemKey = getMenuItemKey(item);
      const itemField = match[3].replace(/_([a-z])/g, (_, char: string) => char.toUpperCase());

      nextErrors.sections = nextErrors.sections ?? {};
      nextErrors.sections[sectionKey] = nextErrors.sections[sectionKey] ?? {};
      nextErrors.sections[sectionKey].items = nextErrors.sections[sectionKey].items ?? {};
      nextErrors.sections[sectionKey].items![itemKey] = {
        ...(nextErrors.sections[sectionKey].items?.[itemKey] ?? {}),
        [itemField === "recipeId" ? "recipeId" : itemField]: message,
      };
    }
  }

  return nextErrors;
}

function useMenuOptions() {
  const eventsQuery = useEvents({ perPage: 100 });
  const recipesQuery = useRecipes({ perPage: 100 });

  const eventOptions = useMemo(
    () =>
      eventsQuery.events.map((event) => ({
        label: event.name,
        metadata: event.startsAt,
        value: event.id,
      })),
    [eventsQuery.events]
  );

  const recipeOptions = useMemo(
    () =>
      recipesQuery.recipes.map((recipe) => ({
        currentVersionId: recipe.currentVersionId ?? recipe.currentVersionRecord?.id ?? null,
        label: recipe.name,
        metadata: recipe.description ?? undefined,
        value: recipe.id,
      })),
    [recipesQuery.recipes]
  );

  return { eventOptions, eventsQuery, recipeOptions, recipesQuery };
}

function buildRecipeSummary(sections: MenuEditorValues["sections"] = []) {
  const byRecipe = new Map<string, { id: string; itemCount: number; itemNames: string[]; name?: string | null }>();
  let linkedCount = 0;
  let totalItems = 0;

  for (const section of sections) {
    for (const item of section.items ?? []) {
      totalItems += 1;

      if (!item.recipeId) {
        continue;
      }

      linkedCount += 1;
      const current = byRecipe.get(item.recipeId) ?? {
        id: item.recipeId,
        itemCount: 0,
        itemNames: [],
        name: item.recipe?.name ?? null,
      };

      current.itemCount += 1;
      current.itemNames.push(item.name);
      if (!current.name && item.recipe?.name) {
        current.name = item.recipe.name;
      }

      byRecipe.set(item.recipeId, current);
    }
  }

  return {
    linkedCount,
    recipes: Array.from(byRecipe.values()),
    totalItems,
    unlinkedCount: Math.max(totalItems - linkedCount, 0),
  };
}

export function MenuListScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const canView = hasPermission("menus.view");
  const canCreate = hasPermission("menus.create");
  const [search, setSearch] = useState("");
  const deferredSearch = useDeferredValue(search);
  const menusQuery = useMenus({ search: deferredSearch });

  if (!canView) {
    return (
      <AppShell subtitle={t("menus.subtitle")} title={t("menus.title")}>
        <ForbiddenState
          description={t("menus.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("menus.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("menus.subtitle")} title={t("menus.title")}>
      <View style={{ gap: spacing[4] }}>
        <SearchInput
          onChangeText={setSearch}
          placeholder={t("menus.searchPlaceholder")}
          value={search}
        />
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("menus.actions.create")}
                onPress={() => router.push(routes.app.menuCreate)}
                size="sm"
              />
            ) : null
          }
          description={t("menus.listDescription")}
          title={t("menus.listTitle", { count: menusQuery.menus.length })}
        >
          <MenuList
            error={menusQuery.isError && menusQuery.error instanceof Error ? menusQuery.error.message : undefined}
            loading={menusQuery.isLoading}
            menus={menusQuery.menus}
            onEndReached={() => {
              if (menusQuery.hasNextPage && !menusQuery.isFetchingNextPage) {
                void menusQuery.fetchNextPage();
              }
            }}
            onMenuPress={(menu) =>
              router.push({
                pathname: routes.app.menuDetail,
                params: { menuId: menu.id },
              } as Href)
            }
            onRefresh={async () => {
              await menusQuery.refetch();
            }}
            refreshing={menusQuery.isRefetching}
          />
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function MenuCreateScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const canCreate = hasPermission("menus.create");
  const { eventOptions, eventsQuery, recipeOptions, recipesQuery } = useMenuOptions();
  const createMutation = useCreateMenu();
  const [validationErrors, setValidationErrors] = useState<MenuEditorValidationErrors>({});

  if (!canCreate) {
    return (
      <AppShell subtitle={t("menus.createSubtitle")} title={t("menus.createTitle")}>
        <ForbiddenState
          description={t("menus.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("menus.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("menus.createSubtitle")} title={t("menus.createTitle")}>
      {(eventsQuery.isLoading || recipesQuery.isLoading) ? <LoadingState title={t("menus.loading")} /> : null}
      {eventsQuery.isError || recipesQuery.isError ? (
        <ErrorState
          detail={eventsQuery.error instanceof Error ? eventsQuery.error.message : recipesQuery.error instanceof Error ? recipesQuery.error.message : undefined}
          onRetry={async () => {
            await Promise.all([eventsQuery.refetch(), recipesQuery.refetch()]);
          }}
          title={t("menus.errorTitle")}
        />
      ) : null}
      {!eventsQuery.isLoading && !recipesQuery.isLoading ? (
        <MenuEditorForm
          eventOptions={eventOptions}
          mode="create"
          onCancel={() => router.back()}
          onSubmit={async (values) => {
            setValidationErrors({});
            try {
              const result = await createMutation.mutateAsync(values);
              router.replace({
                pathname: routes.app.menuDetail,
                params: { menuId: result.menu.id },
              } as Href);
            } catch (error) {
              setValidationErrors(mapMenuValidationErrors(values, error));
            }
          }}
          recipeOptions={recipeOptions}
          submitting={createMutation.isPending}
          validationErrors={validationErrors}
        />
      ) : null}
    </AppShell>
  );
}

export function MenuDetailScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const params = useLocalSearchParams<{ menuId?: string }>();
  const menuId = resolveRouteParam(params.menuId);
  const canView = hasPermission("menus.view");
  const canEdit = hasPermission("menus.edit");
  const canCreate = hasPermission("menus.create");
  const menuQuery = useMenu(menuId);
  const versionsQuery = useMenuVersions(menuId);
  const { eventOptions } = useMenuOptions();
  const duplicateMutation = useDuplicateMenu(menuId ?? "");

  if (!canView) {
    return (
      <AppShell subtitle={t("menus.detailSubtitle")} title={t("menus.detailTitle")}>
        <ForbiddenState
          description={t("menus.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("menus.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (!menuId) {
    return (
      <AppShell subtitle={t("menus.detailSubtitle")} title={t("menus.detailTitle")}>
        <ErrorState detail={t("menus.missingIdentifierDescription")} title={t("menus.missingIdentifierTitle")} />
      </AppShell>
    );
  }

  const detail = menuQuery.data ?? null;
  const menu = detail?.menu ?? null;
  const sections = menu?.sections ?? [];
  const recipeSummary = buildRecipeSummary(sections);
  const versions =
    (versionsQuery.data ?? []).map((version) => ({
      ...version,
      isCurrent: version.id === menu?.currentVersionId,
    })) ?? [];

  return (
    <AppShell subtitle={t("menus.detailSubtitle")} title={menu?.name ?? t("menus.detailTitle")}>
      <View style={{ gap: spacing[4] }}>
        {(menuQuery.isLoading || versionsQuery.isLoading) ? <LoadingState title={t("menus.loading")} /> : null}
        {menuQuery.isError || versionsQuery.isError ? (
          <ErrorState
            detail={menuQuery.error instanceof Error ? menuQuery.error.message : versionsQuery.error instanceof Error ? versionsQuery.error.message : undefined}
            onRetry={async () => {
              await Promise.all([menuQuery.refetch(), versionsQuery.refetch()]);
            }}
            title={t("menus.errorTitle")}
          />
        ) : null}
        {menu ? (
          <>
            <MenuDetailHeader
              actions={
                canEdit ? (
                  <Button
                    label={t("menus.actions.edit")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.menuEdit,
                        params: { menuId: menu.id },
                      } as Href)
                    }
                    size="sm"
                  />
                ) : null
              }
              menu={menu}
            />
            <MenuSummaryCard menu={menu} />
            {sections.length ? (
              sections.map((section) => (
                <MenuSection key={section.id ?? section.clientId ?? section.name} section={section} />
              ))
            ) : (
              <EmptyState
                description={t("menus.sectionsEmptyDescription")}
                title={t("menus.sectionsEmptyTitle")}
              />
            )}
            <MenuRecipeSummary
              linkedCount={recipeSummary.linkedCount}
              onRecipePress={(recipe) => {
                if (!recipe.id) {
                  return;
                }

                router.push({
                  pathname: routes.app.recipeDetail,
                  params: { recipeId: recipe.id },
                } as Href);
              }}
              recipes={recipeSummary.recipes}
              totalItems={recipeSummary.totalItems}
              unlinkedCount={recipeSummary.unlinkedCount}
            />
            <MenuAllergenSummary
              allergens={menu.allergens ?? []}
              showWarning
              unknownItemsCount={menu.unknownAllergenItemCount ?? 0}
            />
            <MenuEventLinkCard
              event={menu.event ?? null}
              menu={menu}
              onEventPress={
                menu.event?.id
                  ? () =>
                      router.push({
                        pathname: routes.app.eventDetail,
                        params: { eventId: menu.event?.id ?? "" },
                      } as Href)
                  : undefined
              }
              onLink={() =>
                router.push({
                  pathname: routes.app.menuEdit,
                  params: { menuId: menu.id },
                } as Href)
              }
            />
            {versions.length ? (
              <SectionCard
                description={t("menus.historyDescription")}
                title={t("menus.historyTitle")}
              >
                <View style={{ gap: spacing[3] }}>
                  {versions.map((version) => (
                    <MenuVersionCard key={version.id} version={version} />
                  ))}
                </View>
              </SectionCard>
            ) : null}
            {canCreate ? (
              <SectionCard
                description={t("menus.duplicateDescription")}
                title={t("menus.duplicateTitle")}
              >
                <MenuDuplicateAction
                  eventOptions={eventOptions}
                  loading={duplicateMutation.isPending}
                  menu={menu}
                  onConfirm={async (options) => {
                    const result = await duplicateMutation.mutateAsync(options);
                    router.replace({
                      pathname: routes.app.menuDetail,
                      params: { menuId: result.menu.id },
                    } as Href);
                  }}
                />
              </SectionCard>
            ) : null}
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function MenuEditScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const params = useLocalSearchParams<{ menuId?: string }>();
  const menuId = resolveRouteParam(params.menuId);
  const canEdit = hasPermission("menus.edit");
  const menuQuery = useMenu(menuId);
  const { eventOptions, eventsQuery, recipeOptions, recipesQuery } = useMenuOptions();
  const updateMutation = useUpdateMenu(menuId ?? "");
  const [validationErrors, setValidationErrors] = useState<MenuEditorValidationErrors>({});

  if (!canEdit) {
    return (
      <AppShell subtitle={t("menus.editSubtitle")} title={t("menus.editTitle")}>
        <ForbiddenState
          description={t("menus.forbiddenDescription")}
          onBack={() => router.back()}
          title={t("menus.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  const detail = menuQuery.data ?? null;
  const initialValues = detail?.menu ? createMenuEditorValues(detail.menu) : null;

  return (
    <AppShell subtitle={t("menus.editSubtitle")} title={t("menus.editTitle")}>
      {(menuQuery.isLoading || eventsQuery.isLoading || recipesQuery.isLoading) ? <LoadingState title={t("menus.loading")} /> : null}
      {menuQuery.isError || eventsQuery.isError || recipesQuery.isError ? (
        <ErrorState
          detail={menuQuery.error instanceof Error ? menuQuery.error.message : eventsQuery.error instanceof Error ? eventsQuery.error.message : recipesQuery.error instanceof Error ? recipesQuery.error.message : undefined}
          onRetry={async () => {
            await Promise.all([menuQuery.refetch(), eventsQuery.refetch(), recipesQuery.refetch()]);
          }}
          title={t("menus.errorTitle")}
        />
      ) : null}
      {updateMutation.isError && isApiError(updateMutation.error) && updateMutation.error.kind === "conflict" ? (
        <MenuConflictAlert
          conflictType="version_conflict"
          onReload={async () => {
            setValidationErrors({});
            await menuQuery.refetch();
          }}
        />
      ) : null}
      {initialValues ? (
        <MenuEditorForm
          eventOptions={eventOptions}
          initialValues={initialValues}
          mode="edit"
          onCancel={() => router.back()}
          onSubmit={async (values) => {
            setValidationErrors({});
            try {
              const result = await updateMutation.mutateAsync(values);
              router.replace({
                pathname: routes.app.menuDetail,
                params: { menuId: result.menu.id },
              } as Href);
            } catch (error) {
              setValidationErrors(mapMenuValidationErrors(values, error));
            }
          }}
          recipeOptions={recipeOptions}
          submitting={updateMutation.isPending}
          validationErrors={validationErrors}
        />
      ) : null}
    </AppShell>
  );
}
