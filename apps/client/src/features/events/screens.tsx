import { router, useLocalSearchParams, type Href } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ApiError, isApiError } from "@/api/types";
import { useAuth } from "@/auth/useAuth";
import { AppShell } from "@/components/patterns/AppShell";
import { ClientCard } from "@/components/patterns/client-card";
import { ContactCard } from "@/components/patterns/contact-card";
import { EmptyState } from "@/components/patterns/empty-state";
import { ErrorState } from "@/components/patterns/error-state";
import { EventCalendar } from "@/components/patterns/event-calendar";
import { EventConflictAlert } from "@/components/patterns/event-conflict-alert";
import { EventCreateForm } from "@/components/patterns/event-create-form";
import { EventDetailHeader } from "@/components/patterns/event-detail-header";
import { EventEditForm } from "@/components/patterns/event-edit-form";
import { EventFiltersForm } from "@/components/patterns/event-filters-form";
import { EventList } from "@/components/patterns/event-list";
import { EventNotesPanel } from "@/components/patterns/event-notes-panel";
import { EventSummaryCard } from "@/components/patterns/event-summary-card";
import { EventTimeline } from "@/components/patterns/event-timeline";
import { ForbiddenState } from "@/components/patterns/forbidden-state";
import { LoadingState } from "@/components/patterns/loading-state";
import { SectionCard } from "@/components/patterns/SectionCard";
import { VenueCard } from "@/components/patterns/venue-card";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  useClientPickerOptions,
  useContactPickerOptions,
  useVenuePickerOptions,
} from "@/features/directory";
import {
  getDateKeyForValue,
  getDayBoundsForDateKey,
  getMonthGridDateKeys,
  getWeekDateKeys,
} from "@/features/events/calendar";
import { coerceEventRecord } from "@/features/events/api";
import {
  createEmptyEventFilters,
  type EventFilters,
  type EventFormPayload,
  type EventFormValidationErrors,
} from "@/features/events/forms";
import { useCreateEvent, useEvent, useEvents, useUpdateEvent } from "@/features/events/hooks/useEvents";
import type {
  EventClientValue,
  EventContactValue,
  EventNamedValue,
  EventRecord,
  EventVenueValue,
} from "@/features/events/types";
import type { EventCalendarView } from "@/features/events/calendar";
import { routes } from "@/navigation/routes";
import { spacing } from "@/theme";
import { useWorkspace } from "@/features/workspace";

function resolveRouteParam(value: string | string[] | undefined) {
  if (Array.isArray(value)) {
    return value[0] ?? null;
  }

  return value ?? null;
}

function mapEventFormErrors(error: unknown): {
  submitError: string | null;
  validationErrors: EventFormValidationErrors;
} {
  if (!isApiError(error)) {
    return {
      submitError: error instanceof Error ? error.message : null,
      validationErrors: {},
    };
  }

  const validationErrors: EventFormValidationErrors = {};

  for (const [field, messages] of Object.entries(error.fieldErrors ?? {})) {
    const message = messages[0];

    if (!message) {
      continue;
    }

    switch (field) {
      case "client_id":
        validationErrors.clientId = message;
        break;
      case "contact_id":
        validationErrors.contactId = message;
        break;
      case "event_group_id":
        validationErrors.eventGroupId = message;
        break;
      case "event_type":
        validationErrors.eventType = message;
        break;
      case "ends_at":
        validationErrors.endsAt = message;
        break;
      case "guest_count_expected":
        validationErrors.guestCountExpected = message;
        break;
      case "name":
      case "notes":
      case "priority":
      case "status":
      case "timezone":
        validationErrors[field] = message;
        break;
      case "service_type":
        validationErrors.serviceType = message;
        break;
      case "starts_at":
        validationErrors.startsAt = message;
        break;
      case "venue_id":
        validationErrors.venueId = message;
        break;
      case "version":
        validationErrors.form = message;
        break;
      default:
        break;
    }
  }

  return {
    submitError: error.message,
    validationErrors,
  };
}

function createPickerOptionFromClient(
  client: EventNamedValue | EventClientValue | null | undefined
) {
  if (!client || typeof client === "string" || !client.id) {
    return null;
  }

  const metadata =
    "organization" in client || "email" in client || "phone" in client
      ? [client.organization, client.email, client.phone].filter(Boolean).join(" • ") || undefined
      : undefined;
  const label =
    client.name ||
    ("organization" in client ? client.organization || undefined : undefined) ||
    client.id;

  return {
    label,
    metadata,
    value: client.id,
  };
}

function createPickerOptionFromContact(contact: EventContactValue | null | undefined) {
  if (!contact?.id) {
    return null;
  }

  return {
    label: contact.name ?? contact.id,
    metadata:
      [contact.organization, contact.title, contact.email].filter(Boolean).join(" • ") || undefined,
    value: contact.id,
  };
}

function createPickerOptionFromVenue(
  venue: EventNamedValue | EventVenueValue | null | undefined
) {
  if (!venue || typeof venue === "string" || !venue.id) {
    return null;
  }

  return {
    label: venue.name ?? venue.id,
    metadata: "summary" in venue ? venue.summary ?? undefined : undefined,
    value: venue.id,
  };
}

function mergeSelectedOption<T extends { value: string }>(
  options: T[],
  selectedOption: T | null,
  shouldInclude = true
) {
  if (!shouldInclude || !selectedOption) {
    return options;
  }

  if (options.some((option) => option.value === selectedOption.value)) {
    return options;
  }

  return [selectedOption, ...options];
}

function buildCalendarFilters(
  selectedDate: string,
  timeZone: string,
  locale: string,
  view: EventCalendarView
) {
  const dateKeys =
    view === "month"
      ? getMonthGridDateKeys(selectedDate, locale)
      : view === "week"
      ? getWeekDateKeys(selectedDate, locale)
      : [selectedDate];
  const firstDateKey = dateKeys[0];
  const lastDateKey = dateKeys[dateKeys.length - 1];

  return {
    dateFrom: getDayBoundsForDateKey(firstDateKey, timeZone).start,
    dateTo: getDayBoundsForDateKey(lastDateKey, timeZone).end,
  };
}

export function EventListScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const [filters, setFilters] = useState<EventFilters>(createEmptyEventFilters());
  const canCreate = hasPermission("events.create");
  const canView = hasPermission("events.view");
  const canViewClients = hasPermission("clients.view");
  const canViewVenues = hasPermission("venues.view");
  const clientOptions = useClientPickerOptions();
  const venueOptions = useVenuePickerOptions();
  const eventsQuery = useEvents({
    clientId: filters.clientId,
    dateFrom: filters.dateFrom,
    dateTo: filters.dateTo,
    perPage: 25,
    search: filters.search,
    serviceType: filters.serviceType,
    statuses: filters.statuses,
    venueId: filters.venueId,
  });

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:events.list.subtitle")}
        title={t("app:events.list.title")}
      >
        <ForbiddenState
          description={t("app:events.forbidden.description")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:events.forbidden.title")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={t("app:events.list.subtitle")}
      title={t("app:events.list.title")}
    >
      <View style={{ gap: spacing[4] }}>
        <SectionCard
          action={
            <View style={{ flexDirection: "row", gap: spacing[2] }}>
              <Button
                label={t("app:events.list.actions.calendar")}
                onPress={() => router.push(routes.app.eventCalendar)}
                size="sm"
                variant="secondary"
              />
              {canCreate ? (
                <Button
                  label={t("app:events.list.actions.create")}
                  onPress={() => router.push(routes.app.eventCreate)}
                  size="sm"
                />
              ) : null}
            </View>
          }
          description={t("app:events.list.filtersDescription")}
          title={t("app:events.list.filtersTitle")}
        >
          <EventFiltersForm
            clientOptions={canViewClients ? clientOptions : undefined}
            filters={filters}
            onChange={setFilters}
            onReset={() => setFilters(createEmptyEventFilters())}
            venueOptions={canViewVenues ? venueOptions : undefined}
          />
        </SectionCard>

        <SectionCard
          description={t("app:events.list.resultsDescription")}
          title={t("app:events.list.resultsTitle", { count: eventsQuery.events.length })}
        >
          {eventsQuery.isError ? (
            <ErrorState
              detail={eventsQuery.error instanceof Error ? eventsQuery.error.message : undefined}
              onRetry={async () => {
                await eventsQuery.refetch();
              }}
              title={t("common:events.error.title")}
            />
          ) : (
            <EventList
              empty={
                <EmptyState
                  actionLabel={canCreate ? t("app:events.list.actions.create") : undefined}
                  onAction={canCreate ? () => router.push(routes.app.eventCreate) : undefined}
                  title={t("common:events.empty.title")}
                />
              }
              events={eventsQuery.events}
              loading={eventsQuery.isLoading}
              onEndReached={() => {
                if (eventsQuery.hasNextPage && !eventsQuery.isFetchingNextPage) {
                  void eventsQuery.fetchNextPage();
                }
              }}
              onEventPress={(event) =>
                router.push({
                  pathname: routes.app.eventDetail,
                  params: { eventId: event.id },
                } as Href)
              }
              onRefresh={async () => {
                await eventsQuery.refetch();
              }}
              refreshing={eventsQuery.isRefetching}
            />
          )}
          {eventsQuery.isFetchingNextPage ? (
            <Text tone="muted" variant="bodySmall">
              {t("app:events.list.loadingMore")}
            </Text>
          ) : null}
        </SectionCard>
      </View>
    </AppShell>
  );
}

export function EventDetailScreen() {
  const { t } = useTranslation(["app", "common"]);
  const { hasPermission } = useWorkspace();
  const eventId = resolveRouteParam(useLocalSearchParams<{ eventId?: string }>().eventId);
  const canEdit = hasPermission("events.edit");
  const canView = hasPermission("events.view");
  const canViewClients = hasPermission("clients.view");
  const canViewContacts = hasPermission("contacts.view");
  const canViewVenues = hasPermission("venues.view");
  const eventQuery = useEvent(eventId);
  const event = eventQuery.data ?? null;

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:events.detail.subtitle")}
        title={t("app:events.detail.title")}
      >
        <ForbiddenState
          description={t("app:events.forbidden.description")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:events.forbidden.title")}
        />
      </AppShell>
    );
  }

  if (!eventId) {
    return (
      <AppShell
        subtitle={t("app:events.detail.subtitle")}
        title={t("app:events.detail.title")}
      >
        <ErrorState
          detail={t("app:events.shared.missingIdentifierDescription")}
          title={t("app:events.shared.missingIdentifierTitle")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={event?.eventGroup ?? t("app:events.detail.subtitle")}
      title={event?.name ?? t("app:events.detail.title")}
    >
      <View style={{ gap: spacing[4] }}>
        {eventQuery.isLoading ? <LoadingState title={t("app:events.loading")} /> : null}
        {eventQuery.isError && isApiError(eventQuery.error) && eventQuery.error.kind === "not_found" ? (
          <EmptyState
            actionLabel={t("app:events.list.actions.backToList")}
            onAction={() => router.replace(routes.app.events)}
            description={t("app:events.detail.notFoundDescription")}
            title={t("app:events.detail.notFoundTitle")}
          />
        ) : null}
        {eventQuery.isError &&
        (!isApiError(eventQuery.error) || eventQuery.error.kind !== "not_found") ? (
          <ErrorState
            detail={eventQuery.error instanceof Error ? eventQuery.error.message : undefined}
            onRetry={async () => {
              await eventQuery.refetch();
            }}
            title={t("app:events.errorTitle")}
          />
        ) : null}
        {event ? (
          <>
            <SectionCard
              action={
                canEdit ? (
                  <Button
                    label={t("app:events.detail.actions.edit")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.eventEdit,
                        params: { eventId: event.id },
                      } as Href)
                    }
                    size="sm"
                    variant="secondary"
                  />
                ) : null
              }
              description={t("app:events.detail.summaryDescription")}
              title={t("app:events.detail.summaryTitle")}
            >
              <View style={{ gap: spacing[3] }}>
                <EventDetailHeader event={event} />
                <EventSummaryCard event={event} />
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: spacing[2] }}>
                  <Button
                    label={t("app:documents.eventDocumentsAction")}
                    onPress={() =>
                      router.push({
                        pathname: routes.app.documents,
                        params: { eventId: event.id },
                      } as Href)
                    }
                    size="sm"
                    variant="ghost"
                  />
                  {canEdit ? (
                    <Button
                      label={t("app:documents.uploadForEventAction")}
                      onPress={() =>
                        router.push({
                          pathname: routes.app.documentUpload,
                          params: { eventId: event.id },
                        } as Href)
                      }
                      size="sm"
                      variant="ghost"
                    />
                  ) : null}
                </View>
              </View>
            </SectionCard>

            {event.client && canViewClients ? (
              <SectionCard
                description={t("app:events.detail.clientDescription")}
                title={t("app:events.detail.clientTitle")}
              >
                <ClientCard
                  client={event.client}
                  onPress={
                    event.clientId
                      ? () =>
                          router.push({
                            pathname: routes.app.clientDetail,
                            params: { id: event.clientId },
                          } as Href)
                      : undefined
                  }
                />
              </SectionCard>
            ) : null}

            {event.contact && canViewContacts ? (
              <SectionCard
                description={t("app:events.detail.contactDescription")}
                title={t("app:events.detail.contactTitle")}
              >
                <ContactCard
                  contact={event.contact}
                  onPress={
                    event.contactId
                      ? () =>
                          router.push({
                            pathname: routes.app.contactEdit,
                            params: { id: event.contactId },
                          } as Href)
                      : undefined
                  }
                />
              </SectionCard>
            ) : null}

            {event.venue && canViewVenues ? (
              <SectionCard
                description={t("app:events.detail.venueDescription")}
                title={t("app:events.detail.venueTitle")}
              >
                <VenueCard
                  venue={event.venue}
                  onPress={
                    event.venueId
                      ? () =>
                          router.push({
                            pathname: routes.app.venueDetail,
                            params: { id: event.venueId },
                          } as Href)
                      : undefined
                  }
                />
              </SectionCard>
            ) : null}

            <EventNotesPanel notes={event.notes} />

            <SectionCard
              description={t("app:events.detail.timelineDescription")}
              title={t("app:events.detail.timelineTitle")}
            >
              <EventTimeline events={[event]} />
            </SectionCard>
          </>
        ) : null}
      </View>
    </AppShell>
  );
}

export function EventCreateScreen() {
  return <EventUpsertScreen mode="create" />;
}

export function EventEditScreen() {
  return <EventUpsertScreen mode="edit" />;
}

type EventUpsertMode = "create" | "edit";

function EventUpsertScreen({ mode }: { mode: EventUpsertMode }) {
  const { i18n, t } = useTranslation(["app", "common"]);
  const { session } = useAuth();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const eventId = resolveRouteParam(useLocalSearchParams<{ eventId?: string }>().eventId);
  const canCreate = hasPermission("events.create");
  const canEdit = hasPermission("events.edit");
  const canViewClients = hasPermission("clients.view");
  const canViewContacts = hasPermission("contacts.view");
  const canViewVenues = hasPermission("venues.view");
  const eventQuery = useEvent(mode === "edit" ? eventId : null);
  const createMutation = useCreateEvent();
  const updateMutation = useUpdateEvent(eventId ?? "");
  const [selectedClientId, setSelectedClientId] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<EventFormValidationErrors>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [conflictEvent, setConflictEvent] = useState<EventRecord | null>(null);
  const [overrideEvent, setOverrideEvent] = useState<EventRecord | null>(null);
  const allowed = mode === "create" ? canCreate : canEdit;
  const clientOptions = useClientPickerOptions();
  const rawContactOptions = useContactPickerOptions(selectedClientId);
  const venueOptions = useVenuePickerOptions();
  const defaultTimeZone = activeWorkspace?.timezone ?? session?.user.timezone ?? "UTC";
  const currentEvent = overrideEvent ?? eventQuery.data ?? null;

  useEffect(() => {
    if (mode === "edit" && currentEvent?.clientId) {
      setSelectedClientId((existingValue) => existingValue ?? currentEvent.clientId);
    }
  }, [currentEvent?.clientId, mode]);

  if (!allowed) {
    return (
      <AppShell
        subtitle={t(`app:events.${mode}.subtitle`)}
        title={t(`app:events.${mode}.title`)}
      >
        <ForbiddenState
          description={t("app:events.forbidden.description")}
          onBack={() => router.back()}
          title={t("app:events.forbidden.title")}
        />
      </AppShell>
    );
  }

  if (!session?.token) {
    return (
      <AppShell
        subtitle={t(`app:events.${mode}.subtitle`)}
        title={t(`app:events.${mode}.title`)}
      >
        <ErrorState
          detail={t("app:events.apiRequired")}
          title={t("app:events.errorTitle")}
        />
      </AppShell>
    );
  }

  const mergedClientOptions = mergeSelectedOption(
    clientOptions,
    createPickerOptionFromClient(currentEvent?.client ?? null),
    mode === "edit"
  );
  const mergedVenueOptions = mergeSelectedOption(
    venueOptions,
    createPickerOptionFromVenue(currentEvent?.venue ?? null),
    mode === "edit"
  );
  const mergedContactOptions = mergeSelectedOption(
    rawContactOptions,
    createPickerOptionFromContact(currentEvent?.contact ?? null),
    mode === "edit" &&
      Boolean(currentEvent?.contactId) &&
      (!selectedClientId || selectedClientId === currentEvent?.clientId)
  );

  const initialValues =
    mode === "create"
      ? {
          eventGroupId: null,
          timezone: defaultTimeZone,
        }
      : undefined;

  const handleSubmit = async (payload: EventFormPayload) => {
    try {
      setConflictEvent(null);
      setSubmitError(null);
      setValidationErrors({});

      const savedEvent =
        mode === "create"
          ? await createMutation.mutateAsync(payload)
          : await updateMutation.mutateAsync({
              ...payload,
              version: currentEvent?.version ?? 1,
            });

      router.replace({
        pathname: routes.app.eventDetail,
        params: { eventId: savedEvent.id },
      } as Href);
    } catch (error) {
      if (isApiError(error) && error.kind === "conflict") {
        setConflictEvent(coerceEventRecord(error.details));
        setSubmitError(error.message);
        return;
      }

      const mapped = mapEventFormErrors(error);
      setSubmitError(mapped.submitError);
      setValidationErrors(mapped.validationErrors);
    }
  };

  return (
    <AppShell
      subtitle={t(`app:events.${mode}.subtitle`)}
      title={
        mode === "edit" && currentEvent
          ? t("app:events.edit.titleWithName", { name: currentEvent.name })
          : t(`app:events.${mode}.title`)
      }
    >
      <View style={{ gap: spacing[4] }}>
        {mode === "edit" && eventQuery.isLoading ? (
          <LoadingState title={t("app:events.loading")} />
        ) : null}
        {mode === "edit" && eventQuery.isError ? (
          <ErrorState
            detail={eventQuery.error instanceof Error ? eventQuery.error.message : undefined}
            onRetry={async () => {
              await eventQuery.refetch();
            }}
            title={t("app:events.errorTitle")}
          />
        ) : null}
        {conflictEvent ? (
          <EventConflictAlert
            description={t("app:events.edit.conflictDescription")}
            onKeepCurrent={() => {
              setConflictEvent(null);
            }}
            onReload={async () => {
              setConflictEvent(null);
              setOverrideEvent(null);
              await eventQuery.refetch();
            }}
            onReview={() => {
              setOverrideEvent(conflictEvent);
              setSelectedClientId(conflictEvent.clientId);
              setConflictEvent(null);
            }}
          />
        ) : null}
        {(mode === "create" || currentEvent) && (
          <SectionCard
            description={t(`app:events.${mode}.description`)}
            title={t(`app:events.${mode}.cardTitle`)}
          >
            {mode === "create" ? (
              <EventCreateForm
                clientOptions={canViewClients ? mergedClientOptions : undefined}
                contactOptions={canViewContacts ? mergedContactOptions : undefined}
                initialValues={initialValues}
                key={`event-create-${defaultTimeZone}`}
                onCancel={() => router.back()}
                onClientIdChange={setSelectedClientId}
                onSubmit={handleSubmit}
                showEventType
                showPriority
                submitting={createMutation.isPending}
                validationErrors={{
                  ...validationErrors,
                  form: submitError ?? validationErrors.form,
                }}
                venueOptions={canViewVenues ? mergedVenueOptions : undefined}
              />
            ) : currentEvent ? (
              <EventEditForm
                clientOptions={canViewClients ? mergedClientOptions : undefined}
                contactOptions={canViewContacts ? mergedContactOptions : undefined}
                event={currentEvent}
                key={`event-edit-${currentEvent.id}-${currentEvent.version}-${selectedClientId ?? "none"}`}
                onCancel={() => router.back()}
                onClientIdChange={setSelectedClientId}
                onSubmit={handleSubmit}
                showEventType
                showPriority
                submitting={updateMutation.isPending}
                validationErrors={{
                  ...validationErrors,
                  form: submitError ?? validationErrors.form,
                }}
                venueOptions={canViewVenues ? mergedVenueOptions : undefined}
              />
            ) : null}
          </SectionCard>
        )}
        {mode === "create" ? (
          <Text tone="muted" variant="bodySmall">
            {t("app:events.create.relationshipHint", { locale: i18n.language })}
          </Text>
        ) : null}
      </View>
    </AppShell>
  );
}

export function EventCalendarScreen() {
  const { i18n, t } = useTranslation(["app", "common"]);
  const { session } = useAuth();
  const { activeWorkspace, hasPermission } = useWorkspace();
  const canView = hasPermission("events.view");
  const timeZone = activeWorkspace?.timezone ?? session?.user.timezone ?? "UTC";
  const [view, setView] = useState<EventCalendarView>("month");
  const [selectedDate, setSelectedDate] = useState(() => getDateKeyForValue(new Date(), timeZone));
  const rangeFilters = useMemo(
    () => buildCalendarFilters(selectedDate, timeZone, i18n.language, view),
    [i18n.language, selectedDate, timeZone, view]
  );
  const eventsQuery = useEvents({
    dateFrom: rangeFilters.dateFrom,
    dateTo: rangeFilters.dateTo,
    perPage: 100,
  });

  if (!canView) {
    return (
      <AppShell
        subtitle={t("app:events.calendar.subtitle")}
        title={t("app:events.calendar.title")}
      >
        <ForbiddenState
          description={t("app:events.forbidden.description")}
          onBack={() => router.replace(routes.app.operations)}
          title={t("app:events.forbidden.title")}
        />
      </AppShell>
    );
  }

  return (
    <AppShell
      subtitle={t("app:events.calendar.subtitle")}
      title={t("app:events.calendar.title")}
    >
      <View style={{ gap: spacing[4] }}>
        {eventsQuery.isError ? (
          <ErrorState
            detail={eventsQuery.error instanceof Error ? eventsQuery.error.message : undefined}
            onRetry={async () => {
              await eventsQuery.refetch();
            }}
            title={t("app:events.errorTitle")}
          />
        ) : (
          <EventCalendar
            accessibilityLabel={t("common:events.calendar.accessibilityLabel")}
            events={eventsQuery.events}
            loading={eventsQuery.isLoading}
            onEventPress={(event) =>
              router.push({
                pathname: routes.app.eventDetail,
                params: { eventId: event.id },
              } as Href)
            }
            onSelectedDateChange={setSelectedDate}
            onViewChange={setView}
            selectedDate={selectedDate}
            timeZone={timeZone}
            view={view}
          />
        )}
      </View>
    </AppShell>
  );
}
