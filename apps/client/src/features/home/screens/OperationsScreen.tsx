import { useEffect, useMemo, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";
import { z } from "zod";

import type { ApiError } from "@/api/types";
import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppShell } from "@/components/patterns/AppShell";
import { FormSection } from "@/components/patterns/FormSection";
import { ListItemCard } from "@/components/patterns/ListItemCard";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StatCard } from "@/components/patterns/StatCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { OptionPicker } from "@/components/primitives/OptionPicker";
import { TextField } from "@/components/primitives/TextField";
import {
  useCreateEvent,
  useEvents,
  type CreateEventInput,
  type EventPriority,
  type EventRecord,
  type EventStatus,
} from "@/features/events";

const eventStatuses: EventStatus[] = [
  "draft",
  "tentative",
  "confirmed",
  "in_production",
  "completed",
  "cancelled",
];

const eventPriorities: EventPriority[] = ["low", "normal", "high", "urgent"];

const schema = z
  .object({
    name: z.string().trim().min(2),
    startsAt: z.string().min(16),
    endsAt: z.string().optional(),
    timezone: z.string().trim().min(3),
    status: z.enum(eventStatuses),
    priority: z.enum(eventPriorities),
    guestCountExpected: z.string().optional(),
    serviceType: z.string().optional(),
    eventType: z.string().optional(),
    notes: z.string().optional(),
  })
  .refine(
    (values) => {
      const startsAt = parseDateTimeInput(values.startsAt);
      return startsAt !== null;
    },
    {
      message: "Enter a valid start date and time.",
      path: ["startsAt"],
    }
  )
  .refine(
    (values) => {
      if (!values.endsAt?.trim()) {
        return true;
      }

      const startsAt = parseDateTimeInput(values.startsAt);
      const endsAt = parseDateTimeInput(values.endsAt);

      return startsAt !== null && endsAt !== null && endsAt > startsAt;
    },
    {
      message: "End time must be after the start time.",
      path: ["endsAt"],
    }
  )
  .refine(
    (values) => {
      if (!values.guestCountExpected?.trim()) {
        return true;
      }

      return Number.isInteger(Number(values.guestCountExpected));
    },
    {
      message: "Guest count must be a whole number.",
      path: ["guestCountExpected"],
    }
  );

type FormValues = z.infer<typeof schema>;

export default function OperationsScreen() {
  const { t } = useTranslation("app");
  const { session } = useAuth();
  const eventsQuery = useEvents();
  const createEventMutation = useCreateEvent();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      startsAt: getDefaultDateTimeInput(),
      endsAt: "",
      timezone:
        session?.currentWorkspace?.timezone ?? session?.user.timezone ?? "UTC",
      status: "draft",
      priority: "normal",
      guestCountExpected: "",
      serviceType: "",
      eventType: "",
      notes: "",
    },
  });

  useEffect(() => {
    reset((currentValues) => ({
      ...currentValues,
      timezone:
        session?.currentWorkspace?.timezone ?? session?.user.timezone ?? "UTC",
    }));
  }, [reset, session?.currentWorkspace?.timezone, session?.user.timezone]);

  const events = eventsQuery.data?.data ?? [];
  const nextEvent = events[0] ?? null;
  const confirmedCount = events.filter((event) =>
    ["confirmed", "in_production"].includes(event.status)
  ).length;
  const canCreateEvents =
    session?.mode === "api" && session.permissions.includes("events.create");
  const isApiSession = session?.mode === "api" && Boolean(session.token);
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
        value: nextEvent ? formatEventDate(nextEvent.startsAt) : t("eventsNone"),
        caption: nextEvent?.name,
      },
    ],
    [confirmedCount, events.length, nextEvent, t]
  );

  const onSubmit = handleSubmit(async (values) => {
    try {
      setSubmitError(null);
      setSuccessMessage(null);

      const payload: CreateEventInput = {
        name: values.name.trim(),
        startsAt: toIsoString(values.startsAt),
        endsAt: values.endsAt?.trim() ? toIsoString(values.endsAt) : null,
        timezone: values.timezone.trim(),
        status: values.status,
        priority: values.priority,
        guestCountExpected: values.guestCountExpected?.trim()
          ? Number(values.guestCountExpected)
          : null,
        serviceType: values.serviceType?.trim() || null,
        eventType: values.eventType?.trim() || null,
        notes: values.notes?.trim() || null,
      };

      await createEventMutation.mutateAsync(payload);
      setSuccessMessage(t("eventCreateSuccess"));
      reset({
        ...values,
        name: "",
        endsAt: "",
        guestCountExpected: "",
        serviceType: "",
        eventType: "",
        notes: "",
        startsAt: getDefaultDateTimeInput(),
      });
    } catch (error) {
      const apiError = error as ApiError;
      const fieldErrors = apiError.fieldErrors ?? {};

      for (const [field, messages] of Object.entries(fieldErrors)) {
        const message = messages[0];

        if (!message) {
          continue;
        }

        if (field === "starts_at") {
          setError("startsAt", { message });
          continue;
        }

        if (field === "ends_at") {
          setError("endsAt", { message });
          continue;
        }

        if (field === "guest_count_expected") {
          setError("guestCountExpected", { message });
          continue;
        }

        if (field === "service_type") {
          setError("serviceType", { message });
          continue;
        }

        if (field === "event_type") {
          setError("eventType", { message });
          continue;
        }

        if (
          field === "name" ||
          field === "timezone" ||
          field === "status" ||
          field === "priority" ||
          field === "notes"
        ) {
          setError(field, { message });
        }
      }

      setSubmitError(
        error instanceof Error ? error.message : t("eventsLoadError")
      );
    }
  });

  return (
    <AppShell
      title={t("operationsTitle")}
      subtitle={t("operationsSubtitle")}
    >
      <View style={{ gap: 18 }}>
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
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 18 }}>
          {summary.map((item) => (
            <StatCard
              key={item.label}
              label={item.label}
              value={item.value}
              caption={item.caption}
            />
          ))}
        </View>
        <View
          style={{
            alignItems: "flex-start",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: 18,
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
            <FormSection>
              <Controller
                control={control}
                name="name"
                render={({ field }) => (
                  <TextField
                    label={t("eventFieldName")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.name?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="startsAt"
                render={({ field }) => (
                  <TextField
                    autoCapitalize="none"
                    hint={t("eventDateHint")}
                    label={t("eventFieldStartsAt")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.startsAt?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="endsAt"
                render={({ field }) => (
                  <TextField
                    autoCapitalize="none"
                    hint={t("eventDateHint")}
                    label={t("eventFieldEndsAt")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.endsAt?.message}
                  />
                )}
              />
            </FormSection>
            <FormSection>
              <Controller
                control={control}
                name="timezone"
                render={({ field }) => (
                  <TextField
                    autoCapitalize="none"
                    label={t("timezone")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.timezone?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="guestCountExpected"
                render={({ field }) => (
                  <TextField
                    keyboardType="number-pad"
                    label={t("eventFieldGuestCount")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.guestCountExpected?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="serviceType"
                render={({ field }) => (
                  <TextField
                    label={t("eventFieldServiceType")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.serviceType?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="eventType"
                render={({ field }) => (
                  <TextField
                    label={t("eventFieldEventType")}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    value={field.value}
                    error={errors.eventType?.message}
                  />
                )}
              />
              <Controller
                control={control}
                name="notes"
                render={({ field }) => (
                  <TextField
                    label={t("eventFieldNotes")}
                    multiline
                    numberOfLines={4}
                    onBlur={field.onBlur}
                    onChangeText={field.onChange}
                    style={{ minHeight: 96, textAlignVertical: "top" }}
                    value={field.value}
                    error={errors.notes?.message}
                  />
                )}
              />
            </FormSection>
            <FormSection>
              <Controller
                control={control}
                name="status"
                render={({ field }) => (
                  <OptionPicker
                    label={t("eventFieldStatus")}
                    onChange={field.onChange}
                    options={eventStatuses.map((value) => ({
                      value,
                      label: t(`eventStatus.${value}`),
                    }))}
                    selected={field.value}
                  />
                )}
              />
              <Controller
                control={control}
                name="priority"
                render={({ field }) => (
                  <OptionPicker
                    label={t("eventFieldPriority")}
                    onChange={field.onChange}
                    options={eventPriorities.map((value) => ({
                      value,
                      label: t(`eventPriority.${value}`),
                    }))}
                    selected={field.value}
                  />
                )}
              />
            </FormSection>
            <AppButton
              disabled={!canCreateEvents}
              label={t("eventCreateAction")}
              loading={isSubmitting || createEventMutation.isPending}
              onPress={onSubmit}
            />
          </SectionCard>
          <SectionCard
            action={
              <AppButton
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
              <View style={{ gap: 12 }}>
                {events.map((event) => (
                  <EventListCard key={event.id} event={event} />
                ))}
              </View>
            ) : null}
          </SectionCard>
        </View>
      </View>
    </AppShell>
  );
}

function EventListCard({ event }: { event: EventRecord }) {
  const { t } = useTranslation("app");

  return (
    <ListItemCard
      aside={
        <View style={{ alignItems: "flex-end", gap: 6 }}>
          <ChoiceChip active label={t(`eventStatus.${event.status}`)} />
          <ChoiceChip label={t(`eventPriority.${event.priority}`)} />
        </View>
      }
      meta={[
        `${formatEventDate(event.startsAt)}${
          event.endsAt ? ` - ${formatEventDate(event.endsAt)}` : ""
        }`,
        `${event.timezone}${
          event.guestCountExpected !== null
            ? ` · ${event.guestCountExpected} ${t("eventsGuestsSuffix")}`
            : ""
        }`,
      ]}
      title={event.name}
    >
      {event.serviceType || event.eventType ? (
        <AppText muted>
          {[event.serviceType, event.eventType].filter(Boolean).join(" · ")}
        </AppText>
      ) : null}
      {event.notes ? <AppText muted>{event.notes}</AppText> : null}
    </ListItemCard>
  );
}

function getDefaultDateTimeInput() {
  const date = new Date();
  date.setMinutes(0, 0, 0);
  date.setHours(date.getHours() + 2);

  return formatDateTimeInput(date);
}

function formatDateTimeInput(date: Date) {
  const year = String(date.getFullYear());
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");

  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function parseDateTimeInput(value: string) {
  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return null;
  }

  return parsed;
}

function toIsoString(value: string) {
  const parsed = parseDateTimeInput(value);

  if (!parsed) {
    throw new Error("Invalid date value.");
  }

  return parsed.toISOString();
}

function formatEventDate(value: string) {
  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
}
