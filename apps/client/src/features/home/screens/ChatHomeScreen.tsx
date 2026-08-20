import { router, type Href } from "expo-router";
import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { AppShell } from "@/components/patterns/AppShell";
import { AvailabilitySummary } from "@/components/patterns/availability-summary";
import { EventSummaryCard } from "@/components/patterns/event-summary-card";
import { MyTasksCard } from "@/components/patterns/my-tasks-card";
import { PrepProgress } from "@/components/patterns/prep-progress";
import { PrepSummaryCard } from "@/components/patterns/prep-summary-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StatCard } from "@/components/patterns/StatCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { TaskSummaryCard } from "@/components/patterns/task-summary-card";
import { Button } from "@/components/primitives/button";
import { useCommandCenter } from "@/features/home/hooks";
import type {
  CommandCenterAttentionItem,
  CommandCenterBeoAttentionItem,
} from "@/features/home/types";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";
import { useWorkspace } from "@/features/workspace";

function isForbiddenError(error: unknown) {
  return (
    typeof error === "object" &&
    error !== null &&
    "status" in error &&
    (error as { status?: number }).status === 403
  );
}

function getBeoAttentionRoute(item: CommandCenterBeoAttentionItem): Href {
  if (item.reason === "review_required") {
    return {
      pathname: routes.app.documentReview,
      params: { documentId: item.document.id },
    } as Href;
  }

  return {
    pathname: routes.app.documentDetail,
    params: { documentId: item.document.id },
  } as Href;
}

function getAttentionActionRoute(item: CommandCenterAttentionItem): Href {
  if (item.type === "prep_blocked") {
    return routes.app.prep;
  }

  return routes.app.myTasks;
}

export default function ChatHomeScreen() {
  const { t, i18n } = useTranslation(["app", "common"]);
  const { theme } = useAppTheme();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const commandCenterQuery = useCommandCenter();

  const summaryCards = useMemo(() => {
    if (!commandCenterQuery.data) {
      return [];
    }

    const formatter = new Intl.NumberFormat(i18n.language);

    return [
      {
        key: "eventsToday",
        label: t("app:commandCenter.stats.eventsToday"),
        value: commandCenterQuery.data.workspaceSummary.eventsToday,
      },
      {
        key: "activePrepLists",
        label: t("app:commandCenter.stats.activePrepLists"),
        value: commandCenterQuery.data.workspaceSummary.activePrepLists,
      },
      {
        key: "openTasks",
        label: t("app:commandCenter.stats.openTasks"),
        value: commandCenterQuery.data.workspaceSummary.openTasks,
      },
      {
        key: "teamMembers",
        label: t("app:commandCenter.stats.teamMembers"),
        value: commandCenterQuery.data.workspaceSummary.teamMembers,
      },
      {
        key: "menus",
        label: t("menus.moduleTitle"),
        value: commandCenterQuery.data.workspaceSummary.menus,
      },
      {
        key: "recipes",
        label: t("recipes.moduleTitle"),
        value: commandCenterQuery.data.workspaceSummary.recipes,
      },
    ]
      .filter((item) => typeof item.value === "number")
      .map((item) => ({
        key: item.key,
        label: item.label,
        value: formatter.format(item.value ?? 0),
      }));
  }, [commandCenterQuery.data, i18n.language, t]);

  const quickActions = useMemo(
    () =>
      [
        hasPermission("events.view")
          ? {
              id: "events",
              label: t("app:commandCenter.actions.openEvents"),
              route: routes.app.events,
            }
          : null,
        hasPermission("events.create")
          ? {
              id: "documents",
              label: t("app:commandCenter.actions.openDocuments"),
              route: routes.app.documentUpload,
            }
          : null,
        hasPermission("prep_lists.create")
          ? {
              id: "prep",
              label: t("app:commandCenter.actions.generatePrep"),
              route: routes.app.prepGenerate,
            }
          : null,
        hasPermission("tasks.view")
          ? {
              id: "tasks",
              label: t("app:commandCenter.actions.openTasks"),
              route: routes.app.myTasks,
            }
          : null,
        hasPermission("members.view")
          ? {
              id: "team",
              label: t("app:commandCenter.actions.openTeam"),
              route: routes.app.teamRoster,
            }
          : null,
        hasPermission("menus.view")
          ? {
              id: "menus",
              label: t("app:commandCenter.actions.openMenus"),
              route: routes.app.menus,
            }
          : null,
        hasPermission("recipes.view")
          ? {
              id: "recipes",
              label: t("app:commandCenter.actions.openRecipes"),
              route: routes.app.recipes,
            }
          : null,
      ].filter(Boolean) as Array<{ id: string; label: string; route: Href }>,
    [hasPermission, t]
  );

  const hasAnySection = Boolean(
    summaryCards.length ||
      quickActions.length ||
      commandCenterQuery.data?.upcomingEvents.length ||
      commandCenterQuery.data?.activePrep ||
      commandCenterQuery.data?.myTasks.length ||
      commandCenterQuery.data?.taskSummary ||
      commandCenterQuery.data?.staffingSummary ||
      commandCenterQuery.data?.beoAttentionItems.length
  );

  const refreshButton = (
    <Button
      label={t("app:commandCenter.actions.refresh")}
      loading={commandCenterQuery.isRefetching}
      onPress={() => {
        void commandCenterQuery.refetch();
      }}
      size="sm"
      variant="secondary"
    />
  );

  if (commandCenterQuery.isLoading && !commandCenterQuery.data) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={t("app:commandCenter.loadingDescription")}
          title={t("app:commandCenter.loadingTitle")}
          tone="loading"
        />
      </AppShell>
    );
  }

  if (commandCenterQuery.isError && !commandCenterQuery.data) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={
            isForbiddenError(commandCenterQuery.error)
              ? t("app:commandCenter.forbiddenDescription")
              : commandCenterQuery.error.message
          }
          onAction={() => {
            void commandCenterQuery.refetch();
          }}
          title={
            isForbiddenError(commandCenterQuery.error)
              ? t("app:commandCenter.forbiddenTitle")
              : t("app:commandCenter.errorTitle")
          }
          tone={isForbiddenError(commandCenterQuery.error) ? "forbidden" : "error"}
        />
      </AppShell>
    );
  }

  if (!commandCenterQuery.data) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={t("app:commandCenter.emptyDescription")}
          title={t("app:commandCenter.emptyTitle")}
          tone="empty"
        />
      </AppShell>
    );
  }

  if (!hasAnySection) {
    return (
      <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
        <StateBlock
          description={t("app:commandCenter.forbiddenDescription")}
          title={t("app:commandCenter.forbiddenTitle")}
          tone="forbidden"
        />
      </AppShell>
    );
  }

  return (
    <AppShell title={t("chatTitle")} subtitle={t("chatSubtitle")}>
      <View style={{ gap: theme.spacing[4] }}>
        <SectionCard
          action={refreshButton}
          description={t("app:commandCenter.summaryDescription", {
            workspace:
              commandCenterQuery.data.workspace.name ??
              activeWorkspace?.name ??
              t("workspacePending"),
          })}
          title={t("app:commandCenter.summaryTitle")}
        >
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
            {summaryCards.map((card) => (
              <StatCard key={card.key} label={card.label} value={card.value} />
            ))}
          </View>
        </SectionCard>

        {quickActions.length ? (
          <SectionCard
            description={t("app:commandCenter.quickActionsDescription")}
            title={t("app:commandCenter.quickActionsTitle")}
          >
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
              {quickActions.map((action) => (
                <Button
                  key={action.id}
                  label={action.label}
                  onPress={() => router.push(action.route)}
                  variant="secondary"
                />
              ))}
            </View>
          </SectionCard>
        ) : null}

        {hasPermission("events.view") ? (
          <SectionCard
            action={
              <Button
                label={t("app:commandCenter.actions.openEvents")}
                onPress={() => router.push(routes.app.events)}
                size="sm"
                variant="ghost"
              />
            }
            description={t("app:commandCenter.upcomingEventsDescription")}
            title={t("app:commandCenter.upcomingEventsTitle")}
          >
            {commandCenterQuery.data.upcomingEvents.length ? (
              <View style={{ gap: theme.spacing[3] }}>
                {commandCenterQuery.data.upcomingEvents.map((event) => (
                  <EventSummaryCard key={event.id} compact event={event} />
                ))}
              </View>
            ) : (
              <StateBlock
                compact
                description={t("app:commandCenter.upcomingEventsEmptyDescription")}
                title={t("app:commandCenter.upcomingEventsEmptyTitle")}
                tone="empty"
              />
            )}
          </SectionCard>
        ) : null}

        {hasPermission("prep_lists.view") ? (
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[4],
            }}
          >
            <View style={{ flex: 1, minWidth: 320 }}>
              <SectionCard
                action={
                  <Button
                    label={t("prep.moduleAction")}
                    onPress={() => router.push(routes.app.prep)}
                    size="sm"
                    variant="ghost"
                  />
                }
                description={t("app:commandCenter.activePrepDescription")}
                title={t("app:commandCenter.activePrepTitle")}
              >
                {commandCenterQuery.data.activePrep ? (
                  <PrepSummaryCard
                    prepList={commandCenterQuery.data.activePrep}
                    progress={{
                      blocked: commandCenterQuery.data.prepProgress?.blocked ?? null,
                      completed: commandCenterQuery.data.prepProgress?.done ?? null,
                      inProgress: commandCenterQuery.data.prepProgress?.inProgress ?? null,
                      skipped: commandCenterQuery.data.prepProgress?.skipped ?? null,
                      total: commandCenterQuery.data.prepProgress?.total ?? null,
                    }}
                  />
                ) : (
                  <StateBlock
                    compact
                    description={t("app:commandCenter.activePrepEmptyDescription")}
                    title={t("app:commandCenter.activePrepEmptyTitle")}
                    tone="empty"
                  />
                )}
              </SectionCard>
            </View>
            {commandCenterQuery.data.prepProgress ? (
              <View style={{ flex: 1, minWidth: 320 }}>
                <PrepProgress
                  blocked={commandCenterQuery.data.prepProgress.blocked}
                  done={commandCenterQuery.data.prepProgress.done}
                  inProgress={commandCenterQuery.data.prepProgress.inProgress}
                  skipped={commandCenterQuery.data.prepProgress.skipped}
                  todo={commandCenterQuery.data.prepProgress.todo}
                  total={commandCenterQuery.data.prepProgress.total}
                />
              </View>
            ) : null}
          </View>
        ) : null}

        {hasPermission("tasks.view") ? (
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[4],
            }}
          >
            <View style={{ flex: 1, minWidth: 320 }}>
              <SectionCard
                description={t("app:commandCenter.tasksDescription")}
                title={t("app:commandCenter.tasksTitle")}
              >
                <MyTasksCard
                  maxItems={4}
                  onItemPress={(task) =>
                    router.push({
                      pathname: routes.app.taskDetail,
                      params: { taskId: task.id },
                    } as Href)
                  }
                  onViewAllPress={() => router.push(routes.app.myTasks)}
                  tasks={commandCenterQuery.data.myTasks}
                />
              </SectionCard>
            </View>
            {commandCenterQuery.data.taskSummary ? (
              <View style={{ flex: 1, minWidth: 320 }}>
                <TaskSummaryCard summary={commandCenterQuery.data.taskSummary} />
              </View>
            ) : null}
          </View>
        ) : null}

        {hasPermission("members.view") && commandCenterQuery.data.staffingSummary ? (
          <SectionCard
            action={
              <Button
                label={t("teamStaff.moduleAction")}
                onPress={() => router.push(routes.app.teamRoster)}
                size="sm"
                variant="ghost"
              />
            }
            description={t("app:commandCenter.staffingDescription")}
            title={t("app:commandCenter.staffingTitle")}
          >
            <AvailabilitySummary summary={commandCenterQuery.data.staffingSummary} />
          </SectionCard>
        ) : null}

        {commandCenterQuery.data.attentionItems.length ? (
          <SectionCard
            description={t("app:commandCenter.attentionDescription")}
            title={t("app:commandCenter.attentionTitle")}
          >
            <View style={{ gap: theme.spacing[3] }}>
              {commandCenterQuery.data.attentionItems.map((item) => (
                <AlertCard
                  actionLabel={t("app:commandCenter.actions.openAttention")}
                  description={t(`app:commandCenter.attention.${item.type}`, {
                    count: item.count ?? 0,
                  })}
                  key={`${item.type}-${item.count ?? 0}`}
                  onAction={() => router.push(getAttentionActionRoute(item))}
                  title={t(`app:commandCenter.attentionTitles.${item.type}`)}
                  tone={item.tone}
                />
              ))}
            </View>
          </SectionCard>
        ) : null}

        {commandCenterQuery.data.beoAttentionItems.length ? (
          <SectionCard
            action={
              <Button
                label={t("documents.moduleAction")}
                onPress={() => router.push(routes.app.documents)}
                size="sm"
                variant="ghost"
              />
            }
            description={t("app:commandCenter.beoAttentionDescription")}
            title={t("app:commandCenter.beoAttentionTitle")}
          >
            <View style={{ gap: theme.spacing[3] }}>
              {commandCenterQuery.data.beoAttentionItems.map((item) => (
                <AlertCard
                  actionLabel={
                    item.reason === "review_required"
                      ? t("app:commandCenter.actions.reviewDocument")
                      : t("app:commandCenter.actions.openDocument")
                  }
                  description={t(`app:commandCenter.beoAttention.reasons.${item.message}`)}
                  key={item.document.id}
                  onAction={() => router.push(getBeoAttentionRoute(item))}
                  title={
                    item.beo?.event?.name ??
                    item.document.linkedEvent?.name ??
                    item.document.originalFilename ??
                    item.document.name
                  }
                  tone={item.tone}
                />
              ))}
            </View>
          </SectionCard>
        ) : null}
      </View>
    </AppShell>
  );
}
