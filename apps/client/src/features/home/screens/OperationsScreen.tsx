import { useMemo } from "react";
import { View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";

import { AppShell } from "@/components/patterns/AppShell";
import { EventList } from "@/components/patterns/event-list";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StatCard } from "@/components/patterns/StatCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import {
  SuggestionChips,
  type SuggestionChipItem,
} from "@/components/patterns/suggestion-chips";

import { Button } from "@/components/primitives/button";
import { AppText } from "@/components/primitives/AppText";

import { formatEventDateRange, useEvents } from "@/features/events";

import { useAppTheme } from "@/theme/ThemeProvider";

/* =========================================================
   HUMOO — OPERATIONS SCREEN

   Current integration status:
   - Events: connected to API
   - Prep: presentation layer available, query pending
   - Inventory: presentation layer available, query pending
   - Team: presentation layer available, query pending

   Do not introduce fake operational data here.
========================================================= */

export default function OperationsScreen() {
  const { i18n, t } = useTranslation("app");
  const { session } = useAuth();
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();

  const eventsQuery = useEvents();

  const isDesktop = width >= theme.breakpoints.lg;
  const isApiSession = session?.mode === "api" && Boolean(session.token);

  const events = eventsQuery.events;

  /* =======================================================
     REAL EVENT-DERIVED OPERATIONS DATA
  ======================================================= */

  const confirmedEvents = useMemo(
    () =>
      events.filter((event) =>
        ["confirmed", "in_production"].includes(event.status),
      ),
    [events],
  );

  const activeProductionEvents = useMemo(
    () => events.filter((event) => event.status === "in_production"),
    [events],
  );

  const nextEvent = events[0] ?? null;

  const operationalMetrics = useMemo(
    () => [
      {
        id: "events",
        label: t("operationsMetricEvents"),
        value: String(events.length),
        caption: t("operationsMetricEventsCaption"),
      },
      {
        id: "confirmed",
        label: t("operationsMetricConfirmed"),
        value: String(confirmedEvents.length),
        caption: t("operationsMetricConfirmedCaption"),
      },
      {
        id: "production",
        label: t("operationsMetricProduction"),
        value: String(activeProductionEvents.length),
        caption: t("operationsMetricProductionCaption"),
      },
      {
        id: "next",
        label: t("operationsMetricNextEvent"),
        value: nextEvent
          ? formatEventDateRange(nextEvent, i18n.language)
          : t("eventsNone"),
        caption: nextEvent?.name,
      },
    ],
    [
      activeProductionEvents.length,
      confirmedEvents.length,
      events.length,
      i18n.language,
      nextEvent,
      t,
    ],
  );

  /* =======================================================
     CHAT ENTRY POINTS

     ChatHomeScreen does not currently consume prompt params,
     so these navigate to Chat only.

     When chat messaging is connected, these should send
     the normalized prompt/intention to that existing flow.
  ======================================================= */

  const quickPrompts = useMemo<SuggestionChipItem[]>(
    () => [
      {
        id: "prep-today",
        label: t("operationsPromptPrepToday"),
        value: "prep_today",
      },
      {
        id: "events-week",
        label: t("operationsPromptEventsWeek"),
        value: "events_week",
      },
      {
        id: "missing-items",
        label: t("operationsPromptMissingItems"),
        value: "missing_items",
      },
      {
        id: "team-workload",
        label: t("operationsPromptTeamWorkload"),
        value: "team_workload",
      },
    ],
    [t],
  );

  const openChat = () => {
    router.push("/(app)/chat");
  };

  const handlePromptSelect = (_suggestion: SuggestionChipItem) => {
    /*
     * Future:
     *
     * send/open the selected intent through the existing
     * conversational workflow.
     *
     * Do not create a second AI/chat implementation here.
     */

    openChat();
  };

  /* =======================================================
     EVENTS STATE
  ======================================================= */

  const eventsError =
    eventsQuery.error instanceof Error ? eventsQuery.error.message : undefined;

  return (
    <AppShell
      title={t("operationsTitle")}
      subtitle={t("operationsCommandCenterSubtitle")}
    >
      <View
        style={{
          gap: theme.spacing[5],
        }}
      >
        {/* =================================================
            ASK HUMOO
        ================================================== */}

        <SectionCard
          action={
            <Button label={t("operationsAskHumooAction")} onPress={openChat} />
          }
          description={t("operationsAskHumooDescription")}
          title={t("operationsAskHumooTitle")}
        >
          <SuggestionChips
            accessibilityLabel={t("operationsQuickPromptsAccessibilityLabel")}
            onSelect={handlePromptSelect}
            suggestions={quickPrompts}
          />
        </SectionCard>

        {/* =================================================
            API SESSION WARNING
        ================================================== */}

        {!isApiSession ? (
          <StateBlock
            description={t("eventsApiRequired")}
            title={t("operationsLiveDataUnavailable")}
            tone="info"
          />
        ) : null}

        {/* =================================================
            OPERATIONAL OVERVIEW
        ================================================== */}

        <View
          style={{
            gap: theme.spacing[3],
          }}
        >
          <View
            style={{
              gap: theme.spacing[1],
            }}
          >
            <AppText variant="title">{t("operationsOverviewTitle")}</AppText>

            <AppText muted variant="bodyMedium">
              {t("operationsOverviewDescription")}
            </AppText>
          </View>

          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[3],
            }}
          >
            {operationalMetrics.map((metric) => (
              <StatCard
                caption={metric.caption}
                key={metric.id}
                label={metric.label}
                style={{
                  minWidth: isDesktop ? 190 : 150,
                }}
                value={metric.value}
              />
            ))}
          </View>
        </View>

        {/* =================================================
            MAIN OPERATIONS GRID
        ================================================== */}

        <View
          style={{
            alignItems: "flex-start",
            flexDirection: isDesktop ? "row" : "column",
            gap: theme.spacing[4],
          }}
        >
          {/* ===============================================
              LEFT COLUMN
          ================================================ */}

          <View
            style={{
              flex: isDesktop ? 1.4 : undefined,
              gap: theme.spacing[4],
              width: isDesktop ? undefined : "100%",
            }}
          >
            {/* UPCOMING EVENTS */}

            <SectionCard
              action={
                <Button
                  label={t("eventsRefresh")}
                  onPress={async () => {
                    await eventsQuery.refetch();
                  }}
                  variant="secondary"
                />
              }
              description={t("operationsUpcomingEventsDescription")}
              title={t("operationsUpcomingEventsTitle")}
            >
              <EventList
                compact
                events={events}
                error={eventsQuery.isError ? (eventsError ?? true) : undefined}
                loading={eventsQuery.isLoading}
                onRefresh={async () => {
                  await eventsQuery.refetch();
                }}
                refreshing={eventsQuery.isRefetching}
              />
            </SectionCard>

            {/* PREP */}

            <SectionCard
              description={t("operationsPrepPendingDescription")}
              title={t("operationsPrepTitle")}
            >
              <StateBlock
                description={t("operationsPrepConnectionPending")}
                title={t("operationsDataConnectionPending")}
                tone="info"
              />
            </SectionCard>
          </View>

          {/* ===============================================
              RIGHT COLUMN
          ================================================ */}

          <View
            style={{
              flex: isDesktop ? 1 : undefined,
              gap: theme.spacing[4],
              width: isDesktop ? undefined : "100%",
            }}
          >
            {/* INVENTORY */}

            <SectionCard
              description={t("operationsInventoryDescription")}
              title={t("operationsInventoryTitle")}
            >
              <StateBlock
                description={t("operationsInventoryConnectionPending")}
                title={t("operationsDataConnectionPending")}
                tone="info"
              />
            </SectionCard>

            {/* TEAM */}

            <SectionCard
              description={t("operationsTeamDescription")}
              title={t("operationsTeamTitle")}
            >
              <StateBlock
                description={t("operationsTeamConnectionPending")}
                title={t("operationsDataConnectionPending")}
                tone="info"
              />
            </SectionCard>
          </View>
        </View>

        {/* =================================================
            ACTIVITY
        ================================================== */}

        <SectionCard
          description={t("operationsActivityDescription")}
          title={t("operationsActivityTitle")}
        >
          <StateBlock
            description={t("operationsActivityConnectionPending")}
            title={t("operationsDataConnectionPending")}
            tone="info"
          />
        </SectionCard>
      </View>
    </AppShell>
  );
}
