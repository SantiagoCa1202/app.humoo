import { router, type Href } from "expo-router";
import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AppShell } from "@/components/patterns/AppShell";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StatCard } from "@/components/patterns/StatCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { useClients, useContacts, useVenues } from "@/features/directory";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";
import { useWorkspace } from "@/features/workspace";

export default function OperationsScreen() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const clientsQuery = useClients();
  const contactsQuery = useContacts();
  const venuesQuery = useVenues();
  const canCreateEvents = hasPermission("events.create");
  const canViewEvents = hasPermission("events.view");
  const canViewClients = hasPermission("clients.view");
  const canViewContacts = hasPermission("contacts.view");
  const canViewVenues = hasPermission("venues.view");

  const summary = useMemo(() => {
    const eventSummary = canViewEvents
      ? {
          label: t("operations.eventsSummary"),
          value: t("operations.eventsSummaryValue"),
          caption: activeWorkspace?.name ?? undefined,
        }
      : {
          label: t("operations.eventsSummary"),
          value: t("operations.eventsSummaryLocked"),
          caption: t("operations.eventsLocked"),
        };

    return [
      eventSummary,
      {
        caption: undefined,
        label: t("directory.operations.clientsTitle"),
        value: String(clientsQuery.data?.data.length ?? 0),
      },
      {
        caption: undefined,
        label: t("directory.operations.venuesTitle"),
        value: String(venuesQuery.data?.data.length ?? 0),
      },
    ];
  }, [
    activeWorkspace?.name,
    canViewEvents,
    clientsQuery.data?.data.length,
    t,
    venuesQuery.data?.data.length,
  ]);

  const modules = useMemo(
    () => [
      {
        actionLabel: t("events.list.title"),
        enabled: canViewEvents,
        helper: t("operations.eventsHelper"),
        route: routes.app.events,
        secondaryActionLabel: canCreateEvents ? t("events.list.actions.create") : undefined,
        secondaryRoute: canCreateEvents ? routes.app.eventCreate : undefined,
        title: t("operations.eventsTitle"),
      },
      {
        actionLabel: t("events.calendar.title"),
        enabled: canViewEvents,
        helper: t("operations.calendarHelper"),
        route: routes.app.eventCalendar,
        title: t("operations.calendarTitle"),
      },
      {
        actionLabel: t("directory.clients.list.title"),
        count: clientsQuery.data?.data.length ?? 0,
        enabled: canViewClients,
        helper: t("directory.operations.clientsHelper"),
        route: routes.app.clients,
        title: t("directory.operations.clientsTitle"),
      },
      {
        actionLabel: t("directory.contacts.list.title"),
        count: contactsQuery.data?.data.length ?? 0,
        enabled: canViewContacts,
        helper: t("directory.operations.contactsHelper"),
        route: routes.app.contacts,
        title: t("directory.operations.contactsTitle"),
      },
      {
        actionLabel: t("directory.venues.list.title"),
        count: venuesQuery.data?.data.length ?? 0,
        enabled: canViewVenues,
        helper: t("directory.operations.venuesHelper"),
        route: routes.app.venues,
        title: t("directory.operations.venuesTitle"),
      },
    ],
    [
      canCreateEvents,
      canViewClients,
      canViewContacts,
      canViewEvents,
      canViewVenues,
      clientsQuery.data?.data.length,
      contactsQuery.data?.data.length,
      t,
      venuesQuery.data?.data.length,
    ]
  );

  return (
    <AppShell title={t("operationsTitle")} subtitle={t("operationsSubtitle")}>
      <View style={{ gap: spacing[4] }}>
        {!canViewEvents ? (
          <StateBlock
            description={t("operations.eventsLocked")}
            title={t("operations.eventsTitle")}
            tone="info"
          />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[4] }}>
          {summary.map((item) => (
            <StatCard
              caption={item.caption}
              key={item.label}
              label={item.label}
              value={item.value}
            />
          ))}
        </View>
        <SectionCard
          description={t("operations.modulesDescription")}
          title={t("operations.modulesTitle")}
        >
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[4] }}>
            {modules.map((item) => (
              <View
                key={item.title}
                style={{
                  borderColor: theme.colors.border.default,
                  borderCurve: "continuous",
                  borderRadius: theme.radius.lg,
                  borderWidth: 1,
                  flex: 1,
                  gap: spacing[3],
                  minWidth: 240,
                  padding: spacing[4],
                }}
              >
                <View style={{ gap: spacing[3], height: "100%" }}>
                  <View style={{ gap: spacing[1] }}>
                    <Text variant="h4">{item.title}</Text>
                    <Text tone="muted" variant="bodySmall">
                      {item.helper}
                    </Text>
                  </View>
                  {typeof item.count === "number" ? (
                    <StatCard
                      label={t("directory.operations.recordsLabel")}
                      value={String(item.count)}
                    />
                  ) : null}
                  <Button
                    disabled={!item.enabled}
                    label={item.actionLabel}
                    onPress={() => router.push(item.route)}
                    variant={item.enabled ? "secondary" : "ghost"}
                  />
                  {item.secondaryActionLabel && item.secondaryRoute ? (
                    <Button
                      disabled={!item.enabled}
                      label={item.secondaryActionLabel}
                      onPress={() => router.push(item.secondaryRoute as Href)}
                      variant="ghost"
                    />
                  ) : null}
                </View>
              </View>
            ))}
          </View>
        </SectionCard>
      </View>
    </AppShell>
  );
}
