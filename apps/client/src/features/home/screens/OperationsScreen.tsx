import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import type { ApiError } from "@/api/types";
import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppShell } from "@/components/patterns/AppShell";
import { EventCreateForm } from "@/components/patterns/event-create-form";
import { EventList } from "@/components/patterns/event-list";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StatCard } from "@/components/patterns/StatCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { Button } from "@/components/primitives/button";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";
import {
  formatEventDateRange,
  useCreateEvent,
  useEvents,
  type CreateEventInput,
} from "@/features/events";
import type {
  EventFormPayload,
  EventFormValidationErrors,
} from "@/features/events/forms";

export default function OperationsScreen() {
  const { i18n, t } = useTranslation("app");
  const { session } = useAuth();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const eventsQuery = useEvents();
  const createEventMutation = useCreateEvent();
  const [formKey, setFormKey] = useState(0);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] =
    useState<EventFormValidationErrors | null>(null);

  const events = eventsQuery.data?.data ?? [];
  const nextEvent = events[0] ?? null;
  const confirmedCount = events.filter((event) =>
    ["confirmed", "in_production"].includes(event.status)
  ).length;
  const canCreateEvents = hasPermission("events.create");
  const isApiSession = Boolean(session?.token);
  const defaultTimeZone =
    activeWorkspace?.timezone ?? session?.user.timezone ?? "UTC";
  const initialValues = useMemo(
    () => ({
      priority: "normal" as const,
      status: "draft" as const,
      timezone: defaultTimeZone,
    }),
    [defaultTimeZone]
  );
  const summary = useMemo(
    () => [
      {
        label: t("eventsSummaryTotal"),
        value: String(events.length),
      },
      {
        label: t("eventsSummaryConfirmed"),
        value: String(confirmedCount),
      },
      {
        label: t("eventsSummaryNext"),
        value: nextEvent ? formatEventDateRange(nextEvent, i18n.language) : t("eventsNone"),
        caption: nextEvent?.name,
      },
    ],
    [confirmedCount, events.length, i18n.language, nextEvent, t]
  );

  const handleCreateEvent = async (payload: EventFormPayload) => {
    try {
      setSubmitError(null);
      setSuccessMessage(null);
      setValidationErrors(null);

      const createPayload: CreateEventInput = {
        endsAt: payload.endsAt,
        eventType: payload.eventType,
        guestCountExpected: payload.guestCountExpected,
        name: payload.name,
        notes: payload.notes,
        priority: payload.priority,
        serviceType: payload.serviceType,
        startsAt: payload.startsAt,
        status: payload.status,
        timezone: payload.timezone,
      };

      await createEventMutation.mutateAsync(createPayload);
      setSuccessMessage(t("eventCreateSuccess"));
      setFormKey((currentValue) => currentValue + 1);
    } catch (error) {
      const apiError = error as ApiError;
      const fieldErrors = apiError.fieldErrors ?? {};
      const nextValidationErrors: EventFormValidationErrors = {};

      for (const [field, messages] of Object.entries(fieldErrors)) {
        const message = messages[0];

        if (!message) {
          continue;
        }

        if (field === "starts_at") {
          nextValidationErrors.startsAt = message;
          continue;
        }

        if (field === "ends_at") {
          nextValidationErrors.endsAt = message;
          continue;
        }

        if (field === "guest_count_expected") {
          nextValidationErrors.guestCountExpected = message;
          continue;
        }

        if (field === "service_type") {
          nextValidationErrors.serviceType = message;
          continue;
        }

        if (field === "event_type") {
          nextValidationErrors.eventType = message;
          continue;
        }

        if (
          field === "name" ||
          field === "timezone" ||
          field === "status" ||
          field === "priority" ||
          field === "notes"
        ) {
          nextValidationErrors[field] = message;
        }
      }

      setValidationErrors(nextValidationErrors);
      setSubmitError(error instanceof Error ? error.message : t("eventsLoadError"));
    }
  };

  return (
    <AppShell title={t("operationsTitle")} subtitle={t("operationsSubtitle")}>
      <View style={{ gap: spacing[4] }}>
        {!isApiSession ? (
          <StateBlock
            description={t("eventsApiRequired")}
            title={t("eventsListTitle")}
            tone="info"
          />
        ) : null}
        {isApiSession && !canCreateEvents ? (
          <StateBlock
            description={t("eventsCreatePermissionMissing")}
            title={t("eventsCreateTitle")}
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
        <View
          style={{
            alignItems: "flex-start",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: spacing[4],
          }}
        >
          <SectionCard
            description={t("eventsCreateBody")}
            style={{ flex: 1, minWidth: 320 }}
            title={t("eventsCreateTitle")}
          >
            {submitError ? <AlertMessage tone="error" message={submitError} /> : null}
            {successMessage ? (
              <AlertMessage tone="success" message={successMessage} />
            ) : null}
            <EventCreateForm
              key={formKey}
              accessibilityLabel={t("eventsCreateTitle")}
              disabled={!canCreateEvents}
              initialValues={initialValues}
              onSubmit={handleCreateEvent}
              showEventType
              showPriority
              submitting={createEventMutation.isPending}
              validationErrors={validationErrors ?? undefined}
            />
          </SectionCard>
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
            description={t("eventsListBody")}
            style={{ flex: 1, minWidth: 320 }}
            title={t("eventsListTitle")}
          >
            {eventsQuery.isLoading ? (
              <StateBlock title={t("eventsLoading")} tone="loading" />
            ) : null}
            {eventsQuery.isError ? (
              <StateBlock
                description={
                  eventsQuery.error instanceof Error
                    ? eventsQuery.error.message
                    : undefined
                }
                title={t("eventsLoadError")}
                tone="error"
              />
            ) : null}
            {!eventsQuery.isLoading &&
            !eventsQuery.isError &&
            events.length === 0 ? (
              <StateBlock title={t("eventsEmpty")} tone="empty" />
            ) : null}
            {!eventsQuery.isLoading &&
            !eventsQuery.isError &&
            events.length > 0 ? (
              <EventList events={events} />
            ) : null}
          </SectionCard>
        </View>
      </View>
    </AppShell>
  );
}
