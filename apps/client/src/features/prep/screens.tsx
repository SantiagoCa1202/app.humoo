import { router, useLocalSearchParams, type Href } from "expo-router";
import { useDeferredValue, useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { isApiError } from "@/api/types";
import { AppShell } from "@/components/patterns/AppShell";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { PrepGenerationOptions } from "@/components/patterns/prep-generation-options";
import { PrepGenerationPreview } from "@/components/patterns/prep-generation-preview";
import { PrepItemConflictAlert } from "@/components/patterns/prep-item-conflict-alert";
import { PrepItemEditor } from "@/components/patterns/prep-item-editor";
import { PrepItemList } from "@/components/patterns/prep-item-list";
import { PrepList } from "@/components/patterns/prep-list";
import { PrepListDetailHeader } from "@/components/patterns/prep-list-detail-header";
import { PrepListVersionCard } from "@/components/patterns/prep-list-version-card";
import { PrepProgress } from "@/components/patterns/prep-progress";
import { PrepRegenerationPreview } from "@/components/patterns/prep-regeneration-preview";
import { PrepSummaryCard } from "@/components/patterns/prep-summary-card";
import { PrepVersionComparison } from "@/components/patterns/prep-version-comparison";
import { SectionCard } from "@/components/patterns/SectionCard";
import { Button } from "@/components/primitives/button";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { SearchInput } from "@/components/primitives/search-input";
import { TextField } from "@/components/primitives/text-field";
import { useEvents } from "@/features/events";
import { useMenus } from "@/features/menus";
import { useRecipeCatalog } from "@/features/recipes";
import {
  createPrepListValues,
  createPrepItemValues,
  mapPrepItem,
  normalizePrepGenerationOptions,
  normalizePrepListValues,
  type PrepGenerationOptionsRecord,
  type PrepItemEditorValues,
  type PrepItemRecord,
  type PrepListValidationErrors,
  useCreatePrepList,
  useGeneratePrep,
  usePrepList,
  usePrepLists,
  usePrepPreview,
  usePrepVersions,
  useRegeneratePrep,
  useUpdatePrepItem,
  validatePrepListValues,
} from "@/features/prep";
import { useWorkspaceStaffMembers } from "@/features/team-staff";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";
import { spacing } from "@/theme";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function buildPrepAssignmentOptions(
  members: ReturnType<typeof useWorkspaceStaffMembers>["data"] = []
) {
  return members.map((member) => ({
    label: member.name ?? member.email ?? member.id,
    roleLabel: member.role?.name ?? undefined,
    value: member.id,
  }));
}

function buildPrepMenuOptions(
  menus: ReturnType<typeof useMenus>["menus"],
  eventId?: string | null
) {
  return menus
    .filter((menu) => !eventId || menu.eventId === eventId)
    .map((menu) => ({
      label: menu.currentVersionRecord?.versionNumber
        ? `${menu.name} - v${menu.currentVersionRecord.versionNumber}`
        : menu.name,
      metadata: menu.event?.name ?? undefined,
      value: menu.currentVersionId ?? menu.version?.id ?? "",
    }))
    .filter((option) => option.value.trim().length > 0);
}

export function PrepListScreen() {
  const { t } = useTranslation("app");
  const { hasPermission } = useWorkspace();
  const canCreate = hasPermission("prep_lists.create");
  const canView = hasPermission("prep_lists.view");
  const [search, setSearch] = useState("");
  const deferredSearch = useDeferredValue(search);
  const prepQuery = usePrepLists({ search: deferredSearch });

  if (!canView) {
    return (
      <AppShell subtitle={t("prep.moduleHelper")} title={t("prep.moduleTitle")}>
        <ForbiddenState
          description={t("prep.forbiddenDescription")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("prep.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell subtitle={t("prep.moduleHelper")} title={t("prep.moduleTitle")}>
      <View style={{ gap: spacing[4] }}>
        <SearchInput
          onChangeText={setSearch}
          placeholder={t("prep.searchPlaceholder")}
          value={search}
        />
        <SectionCard
          action={
            canCreate ? (
              <Button
                label={t("prep.actions.generate")}
                onPress={() => router.push(routes.app.prepGenerate)}
                size="sm"
              />
            ) : null
          }
          description={t("prep.listDescription")}
          title={t("prep.listTitle", { count: prepQuery.prepLists.length })}
        >
          <PrepList
            error={prepQuery.isError && prepQuery.error instanceof Error ? prepQuery.error.message : undefined}
            loading={prepQuery.isLoading}
            onEndReached={() => {
              if (prepQuery.hasNextPage && !prepQuery.isFetchingNextPage) {
                void prepQuery.fetchNextPage();
              }
            }}
            onPrepListPress={(prepList) =>
              router.push({
                pathname: routes.app.prepDetail,
                params: { prepListId: prepList.id },
              } as Href)
            }
            onRefresh={async () => {
              await prepQuery.refetch();
            }}
            prepLists={prepQuery.prepLists}
            refreshing={prepQuery.isRefetching}
          />
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function PrepGenerateScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const canCreate = hasPermission("prep_lists.create");
  const eventsQuery = useEvents({ perPage: 100 });
  const menusQuery = useMenus({ perPage: 100 });
  const membersQuery = useWorkspaceStaffMembers();
  const createMutation = useCreatePrepList();
  const [createdPrepListId, setCreatedPrepListId] = useState<string | null>(null);
  const previewMutation = usePrepPreview(createdPrepListId);
  const generateMutation = useGeneratePrep(createdPrepListId);
  const eventOptions = useMemo(
    () =>
      eventsQuery.events.map((event) => ({
        label: event.name,
        metadata: event.startsAt ?? undefined,
        value: event.id,
      })),
    [eventsQuery.events]
  );
  const [listValues, setListValues] = useState(() => createPrepListValues());
  const [listErrors, setListErrors] = useState<PrepListValidationErrors>({});
  const [generationValues, setGenerationValues] = useState<PrepGenerationOptionsRecord>({
    guestCount: null,
    includeAssignments: false,
    preserveAssignments: false,
    preserveCompletedItems: false,
    source: "manual",
  });

  useEffect(() => {
    if (eventsQuery.events.length === 1 && !listValues.eventId) {
      const singleEvent = eventsQuery.events[0];
      setListValues(createPrepListValues(singleEvent));
      setGenerationValues((current) => ({
        ...current,
        dueAt: current.dueAt ?? singleEvent.startsAt ?? null,
        guestCount: current.guestCount ?? singleEvent.guestCountConfirmed ?? singleEvent.guestCountExpected ?? null,
      }));
    }
  }, [eventsQuery.events, listValues.eventId]);

  const selectedEventRecord = useMemo(
    () => eventsQuery.events.find((event) => event.id === listValues.eventId) ?? null,
    [eventsQuery.events, listValues.eventId]
  );
  const menuOptions = useMemo(
    () => buildPrepMenuOptions(menusQuery.menus, listValues.eventId),
    [listValues.eventId, menusQuery.menus]
  );
  const assignmentOptions = useMemo(
    () => buildPrepAssignmentOptions(membersQuery.data ?? []),
    [membersQuery.data]
  );

  if (!canCreate) {
    return (
      <AppShell subtitle={t("app:prep.moduleHelper")} title={t("app:prep.generateTitle")}>
        <ForbiddenState
          description={t("app:prep.generateForbiddenDescription")}
          onBack={() => router.replace(routes.app.prep)}
          title={t("app:prep.generateForbiddenTitle")}
        />
      </AppShell>
    );
  }

  const handlePreview = async () => {
    const normalizedListValues = normalizePrepListValues(listValues);
    const nextErrors = validatePrepListValues(normalizedListValues, t);

    if (Object.values(nextErrors).some(Boolean)) {
      setListErrors(nextErrors);
      return;
    }

    setListErrors({});

    let prepListId = createdPrepListId;

    if (!prepListId) {
      const created = await createMutation.mutateAsync(normalizedListValues);
      prepListId = created.prepList.id;
      setCreatedPrepListId(prepListId);
    }

    await previewMutation.mutateAsync(
      normalizePrepGenerationOptions({
        ...generationValues,
        dueAt: generationValues.dueAt ?? normalizedListValues.productionEndsAt ?? selectedEventRecord?.startsAt ?? null,
        guestCount:
          generationValues.guestCount ??
          selectedEventRecord?.guestCountConfirmed ??
          selectedEventRecord?.guestCountExpected ??
          null,
        menuVersionId: generationValues.menuVersionId ?? menuOptions[0]?.value ?? null,
      })
    );
  };

  const handleGenerate = async () => {
    const result = await generateMutation.mutateAsync(
      normalizePrepGenerationOptions({
        ...generationValues,
        dueAt: generationValues.dueAt ?? listValues.productionEndsAt ?? selectedEventRecord?.startsAt ?? null,
        menuVersionId: generationValues.menuVersionId ?? menuOptions[0]?.value ?? null,
      })
    );

    if (result.prepList?.id) {
      router.replace({
        pathname: routes.app.prepDetail,
        params: { prepListId: result.prepList.id },
      } as Href);
    }
  };

  return (
    <AppShell subtitle={t("app:prep.generateSubtitle")} title={t("app:prep.generateTitle")}>
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          description={t("app:prep.generateFormDescription")}
          title={t("app:prep.generateFormTitle")}
        >
          <View style={{ gap: spacing[4] }}>
            <EntityPicker
              entities={eventOptions}
              error={listErrors.eventId}
              label={t("common:prep.generation.labels.event")}
              onChange={(eventId) => {
                const event = eventsQuery.events.find((entry) => entry.id === eventId) ?? null;
                setListValues(createPrepListValues(event, { ...listValues, eventId }));
                setGenerationValues((current) => ({
                  ...current,
                  dueAt: current.dueAt ?? event?.startsAt ?? null,
                  eventId,
                  guestCount: current.guestCount ?? event?.guestCountConfirmed ?? event?.guestCountExpected ?? null,
                }));
              }}
              placeholder={t("common:prep.generation.placeholders.event")}
              value={listValues.eventId ?? undefined}
            />
            <TextField
              error={listErrors.name}
              label={t("app:prep.listNameLabel")}
              onChangeText={(name) => setListValues((current) => ({ ...current, name }))}
              placeholder={t("app:prep.listNamePlaceholder")}
              value={listValues.name}
            />
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[3] }}>
              <View style={{ flex: 1, minWidth: 220 }}>
                <DateTimeField
                  error={listErrors.productionStartsAt}
                  label={t("app:prep.productionStartsAtLabel")}
                  onChange={(productionStartsAt) =>
                    setListValues((current) => ({ ...current, productionStartsAt }))
                  }
                  timeZone={listValues.timezone ?? selectedEventRecord?.timezone ?? "UTC"}
                  value={listValues.productionStartsAt ?? null}
                />
              </View>
              <View style={{ flex: 1, minWidth: 220 }}>
                <DateTimeField
                  error={listErrors.productionEndsAt}
                  label={t("app:prep.productionEndsAtLabel")}
                  onChange={(productionEndsAt) =>
                    setListValues((current) => ({ ...current, productionEndsAt }))
                  }
                  timeZone={listValues.timezone ?? selectedEventRecord?.timezone ?? "UTC"}
                  value={listValues.productionEndsAt ?? null}
                />
              </View>
            </View>
          </View>
        </SectionCard>
        <PrepGenerationOptions
          assignmentOptions={assignmentOptions}
          availableOptions={{
            allowAssignment: true,
            allowDueAt: true,
            allowGuestCount: true,
            allowIncludeAssignments: true,
            allowMenuVersion: true,
            allowNotes: true,
            allowPreserveAssignments: false,
            allowPreserveCompletedItems: false,
            allowSourceSelection: false,
          }}
          event={selectedEventRecord ? { id: selectedEventRecord.id, name: selectedEventRecord.name, startsAt: selectedEventRecord.startsAt, timezone: selectedEventRecord.timezone } : null}
          eventOptions={eventOptions}
          menuOptions={menuOptions}
          onChange={setGenerationValues}
          value={generationValues}
        />
        <Button
          disabled={createMutation.isPending || previewMutation.isPending || generateMutation.isPending}
          label={t("app:prep.previewAction")}
          onPress={() => void handlePreview()}
        />
        {previewMutation.data ? (
          <PrepGenerationPreview
            estimatedItems={previewMutation.data.preview.estimatedItems}
            event={previewMutation.data.preview.event}
            loading={generateMutation.isPending}
            menu={menuOptions.find((option) => option.value === generationValues.menuVersionId)?.label}
            onCancel={() => previewMutation.reset()}
            onConfirm={() => void handleGenerate()}
            options={generationValues}
            preview={previewMutation.data.preview}
          />
        ) : null}
        {createMutation.isPending || previewMutation.isPending ? <LoadingState /> : null}
        {createMutation.isError || previewMutation.isError || generateMutation.isError ? (
          <ErrorState
            detail={
              createMutation.error instanceof Error
                ? createMutation.error.message
                : previewMutation.error instanceof Error
                ? previewMutation.error.message
                : generateMutation.error instanceof Error
                ? generateMutation.error.message
                : undefined
            }
            title={t("app:prep.generateErrorTitle")}
          />
        ) : null}
      </View>
    </AppShell>
  );
}

export function PrepDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { theme } = useAppTheme();
  const { hasPermission } = useWorkspace();
  const params = useLocalSearchParams<{ prepListId?: string | string[] }>();
  const prepListId = resolveRouteParam(params.prepListId);
  const canEdit = hasPermission("prep_lists.edit");
  const canView = hasPermission("prep_lists.view");
  const prepListQuery = usePrepList(prepListId);
  const versionsQuery = usePrepVersions(prepListId);
  const membersQuery = useWorkspaceStaffMembers();
  const catalogQuery = useRecipeCatalog();
  const currentVersion = prepListQuery.data?.currentVersion ?? null;
  const items = currentVersion?.sections?.flatMap((section) => section.items ?? []) ?? [];
  const assignmentOptions = useMemo(
    () => buildPrepAssignmentOptions(membersQuery.data ?? []),
    [membersQuery.data]
  );
  const unitOptions = useMemo(
    () =>
      (catalogQuery.data?.units ?? []).map((unit) => ({
        label: unit.symbol?.trim() || unit.name?.trim() || unit.key?.trim() || unit.id || "",
        value: unit.id ?? "",
      })),
    [catalogQuery.data?.units]
  );
  const [selectedItemId, setSelectedItemId] = useState<string | null>(null);
  const [draftItem, setDraftItem] = useState<PrepItemEditorValues | null>(null);
  const [remoteConflictItem, setRemoteConflictItem] = useState<PrepItemRecord | null>(null);
  const [selectedVersionId, setSelectedVersionId] = useState<string | null>(null);
  const [regenerationValues, setRegenerationValues] = useState<PrepGenerationOptionsRecord>({
    dueAt: prepListQuery.data?.prepList.productionEndsAt ?? null,
    guestCount: currentVersion?.guestCountSnapshot ?? null,
    includeAssignments: false,
    menuVersionId: currentVersion?.menuVersionId ?? null,
    preserveAssignments: true,
    preserveCompletedItems: true,
    source: "regeneration",
  });
  const updateMutation = useUpdatePrepItem(selectedItemId);
  const { confirmMutation, previewMutation } = useRegeneratePrep(prepListId);

  useEffect(() => {
    if (!selectedItemId) {
      setDraftItem(null);
      return;
    }

    const current = items.find((item) => item.id === selectedItemId) ?? null;
    setDraftItem(current ? createPrepItemValues(current) : null);
  }, [items, selectedItemId]);

  useEffect(() => {
    if (!prepListQuery.data) {
      return;
    }

    setRegenerationValues((current) => ({
      ...current,
      dueAt: current.dueAt ?? prepListQuery.data.prepList.productionEndsAt ?? null,
      guestCount: current.guestCount ?? currentVersion?.guestCountSnapshot ?? null,
      menuVersionId: current.menuVersionId ?? currentVersion?.menuVersionId ?? null,
    }));
  }, [currentVersion?.guestCountSnapshot, currentVersion?.menuVersionId, prepListQuery.data]);

  const selectedItem = useMemo(
    () => items.find((item) => item.id === selectedItemId) ?? null,
    [items, selectedItemId]
  );
  const versions = versionsQuery.data ?? [];
  const comparisonBase =
    versions.find((version) => version.id === selectedVersionId) ??
    versions.find((version) => version.id !== currentVersion?.id) ??
    null;
  const todoCount = items.filter((item) => item.status === "todo" || !item.status).length;
  const inProgressCount = items.filter((item) => item.status === "in_progress").length;
  const doneCount = items.filter((item) => item.status === "done").length;
  const blockedCount = items.filter((item) => item.status === "blocked").length;
  const skippedCount = items.filter((item) => item.status === "skipped").length;

  if (!canView) {
    return (
      <AppShell subtitle={t("app:prep.moduleHelper")} title={t("app:prep.moduleTitle")}>
        <ForbiddenState
          description={t("app:prep.forbiddenDescription")}
          onBack={() => router.replace(routes.app.prep)}
          title={t("app:prep.forbiddenTitle")}
        />
      </AppShell>
    );
  }

  if (prepListQuery.isLoading) {
    return (
      <AppShell subtitle={t("app:prep.detailSubtitle")} title={t("app:prep.detailTitle")}>
        <LoadingState />
      </AppShell>
    );
  }

  if (prepListQuery.isError || !prepListQuery.data) {
    return (
      <AppShell subtitle={t("app:prep.detailSubtitle")} title={t("app:prep.detailTitle")}>
        <ErrorState
          detail={prepListQuery.error instanceof Error ? prepListQuery.error.message : undefined}
          title={t("app:prep.detailErrorTitle")}
        />
      </AppShell>
    );
  }

  const handleSaveItem = async (values: PrepItemEditorValues) => {
    setRemoteConflictItem(null);
    try {
      await updateMutation.mutateAsync(values);
      await Promise.all([prepListQuery.refetch(), versionsQuery.refetch()]);
    } catch (error) {
      if (isApiError(error) && error.status === 409 && error.details && typeof error.details === "object") {
                    setRemoteConflictItem(
                      mapPrepItem(error.details as Parameters<typeof mapPrepItem>[0])
                    );
      }

      throw error;
    }
  };

  return (
    <AppShell subtitle={t("app:prep.detailSubtitle")} title={prepListQuery.data.prepList.name}>
      <View style={{ gap: spacing[4] }}>
        <PrepListDetailHeader
          actions={
            canEdit ? (
              <Button
                label={t("app:prep.regenerateAction")}
                onPress={() => void previewMutation.mutateAsync(regenerationValues)}
                size="sm"
              />
            ) : null
          }
          currentVersion={currentVersion}
          prepList={prepListQuery.data.prepList}
          progress={prepListQuery.data.progress}
        />
        <PrepSummaryCard prepList={prepListQuery.data.prepList} progress={prepListQuery.data.progress} />
        <PrepProgress
          blocked={blockedCount}
          done={doneCount}
          inProgress={inProgressCount}
          skipped={skippedCount}
          todo={todoCount}
          total={items.length}
        />
        {previewMutation.data && currentVersion ? (
          <PrepRegenerationPreview
            currentProgress={prepListQuery.data.progress}
            currentVersion={currentVersion}
            loading={confirmMutation.isPending}
            onCancel={() => previewMutation.reset()}
            onConfirm={async () => {
              await confirmMutation.mutateAsync(regenerationValues);
              await Promise.all([prepListQuery.refetch(), versionsQuery.refetch()]);
              previewMutation.reset();
            }}
            preserveAssignments={Boolean(regenerationValues.preserveAssignments)}
            preserveCompletedItems={Boolean(regenerationValues.preserveCompletedItems)}
            proposedProgress={previewMutation.data.preview.progress}
            proposedVersion={previewMutation.data.currentVersion ?? currentVersion}
            warnings={previewMutation.data.preview.warnings}
          />
        ) : null}
        <SectionCard
          description={t("app:prep.itemsDescription")}
          title={t("app:prep.itemsTitle", { count: items.length })}
        >
          {items.length ? (
            <PrepItemList
              groupByStatus
              items={items}
              onItemPress={(item) => setSelectedItemId(item.id ?? null)}
              onRefresh={async () => {
                await Promise.all([prepListQuery.refetch(), versionsQuery.refetch()]);
              }}
              refreshing={prepListQuery.isRefetching}
              selectedItemId={selectedItemId}
            />
          ) : (
            <EmptyState
              description={t("app:prep.itemsEmptyDescription")}
              title={t("app:prep.itemsEmptyTitle")}
            />
          )}
        </SectionCard>
        {selectedItem && draftItem ? (
          <SectionCard
            description={t("app:prep.editorDescription")}
            title={t("app:prep.editorTitle")}
          >
            <View style={{ gap: theme.spacing[3] }}>
              {remoteConflictItem ? (
                <PrepItemConflictAlert
                  localItem={draftItem}
                  onDiscardLocal={() => {
                    setDraftItem(createPrepItemValues(remoteConflictItem));
                    setRemoteConflictItem(null);
                  }}
                  onReload={async () => {
                    await Promise.all([prepListQuery.refetch(), versionsQuery.refetch()]);
                    setRemoteConflictItem(null);
                  }}
                  remoteItem={remoteConflictItem}
                />
              ) : null}
              <PrepItemEditor
                assignmentOptions={assignmentOptions}
                initialValue={draftItem}
                onCancel={() => {
                  setDraftItem(createPrepItemValues(selectedItem));
                  setRemoteConflictItem(null);
                }}
                onSubmit={async (value) => {
                  setDraftItem(value);
                  await handleSaveItem(value);
                }}
                submitting={updateMutation.isPending}
                timeZone={prepListQuery.data.prepList.timezone ?? prepListQuery.data.prepList.event?.timezone ?? "UTC"}
                unitOptions={unitOptions}
              />
            </View>
          </SectionCard>
        ) : null}
        <SectionCard
          description={t("app:prep.versionsDescription")}
          title={t("app:prep.versionsTitle", { count: versions.length })}
        >
          <View style={{ gap: spacing[3] }}>
            {versions.map((version) => (
              <PrepListVersionCard
                isCurrent={version.id === currentVersion?.id}
                key={version.id ?? `prep-version-${version.version}`}
                onCompare={
                  currentVersion && version.id !== currentVersion.id
                    ? () => setSelectedVersionId(version.id ?? null)
                    : undefined
                }
                prepList={prepListQuery.data.prepList}
                progress={version.id === currentVersion?.id ? prepListQuery.data.progress : null}
                selected={version.id === selectedVersionId}
                version={version}
              />
            ))}
          </View>
        </SectionCard>
        {comparisonBase && currentVersion ? (
          <PrepVersionComparison
            baseProgress={null}
            baseVersion={comparisonBase}
            targetProgress={prepListQuery.data.progress}
            targetVersion={currentVersion}
          />
        ) : null}
      </View>
    </AppShell>
  );
}
