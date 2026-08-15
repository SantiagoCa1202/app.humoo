import { useEffect, useMemo, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { Pressable, View } from "react-native";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";
import { z } from "zod";

import type { ApiError } from "@/api/types";
import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { TextField } from "@/components/primitives/TextField";
import {
  useCreateEvent,
  useEvents,
  type CreateEventInput,
  type EventPriority,
  type EventRecord,
  type EventStatus,
} from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

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
  const { theme } = useAppTheme();
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
          <AlertMessage message={t("eventsApiRequired")} />
        ) : null}
        {isApiSession && !canCreateEvents ? (
          <AlertMessage message={t("eventsCreatePermissionMissing")} />
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 18 }}>
          {summary.map((item) => (
            <Card
              key={item.label}
              style={{ flex: 1, gap: 8, minWidth: 220 }}
            >
              <AppText variant="overline">{item.label}</AppText>
              <AppText variant="metric">{item.value}</AppText>
            </Card>
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
          <Card style={{ flex: 1, gap: 14, minWidth: 320 }}>
            <AppText variant="title">{t("eventsCreateTitle")}</AppText>
            <AppText muted>{t("eventsCreateBody")}</AppText>
            {submitError ? <AlertMessage tone="error" message={submitError} /> : null}
            {successMessage ? (
              <AlertMessage tone="success" message={successMessage} />
            ) : null}
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
            <Controller
              control={control}
              name="status"
              render={({ field }) => (
                <OptionGroup
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
                <OptionGroup
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
            <AppButton
              disabled={!canCreateEvents}
              label={t("eventCreateAction")}
              loading={isSubmitting || createEventMutation.isPending}
              onPress={onSubmit}
            />
          </Card>
          <Card style={{ flex: 1, gap: 14, minWidth: 320 }}>
            <View
              style={{
                alignItems: "center",
                flexDirection: "row",
                justifyContent: "space-between",
              }}
            >
              <View style={{ gap: 4 }}>
                <AppText variant="title">{t("eventsListTitle")}</AppText>
                <AppText muted>{t("eventsListBody")}</AppText>
              </View>
              <AppButton
                label={t("eventsRefresh")}
                onPress={async () => {
                  await eventsQuery.refetch();
                }}
                variant="secondary"
              />
            </View>
            {eventsQuery.isLoading ? (
              <AppText muted>{t("eventsLoading")}</AppText>
            ) : null}
            {eventsQuery.isError ? (
              <AlertMessage
                tone="error"
                message={
                  eventsQuery.error instanceof Error
                    ? eventsQuery.error.message
                    : t("eventsLoadError")
                }
              />
            ) : null}
            {!eventsQuery.isLoading &&
            !eventsQuery.isError &&
            events.length === 0 ? (
              <AppText muted>{t("eventsEmpty")}</AppText>
            ) : null}
            {!eventsQuery.isLoading &&
            !eventsQuery.isError &&
            events.length > 0 ? (
              <View style={{ gap: 12 }}>
                {events.map((event) => (
                  <View
                    key={event.id}
                    style={{
                      backgroundColor: theme.colors.surfaceMuted,
                      borderColor: theme.colors.borderStrong,
                      borderRadius: theme.radius.md,
                      borderWidth: 1,
                      gap: 10,
                      padding: 16,
                    }}
                  >
                    <View
                      style={{
                        alignItems: "flex-start",
                        flexDirection: "row",
                        flexWrap: "wrap",
                        gap: 10,
                        justifyContent: "space-between",
                      }}
                    >
                      <View style={{ flex: 1, gap: 6, minWidth: 180 }}>
                        <AppText variant="subtitle">{event.name}</AppText>
                        <AppText muted>
                          {formatEventDate(event.startsAt)}
                          {event.endsAt
                            ? ` - ${formatEventDate(event.endsAt)}`
                            : ""}
                        </AppText>
                        <AppText muted>
                          {event.timezone}
                          {event.guestCountExpected !== null
                            ? ` · ${event.guestCountExpected} ${t("eventsGuestsSuffix")}`
                            : ""}
                        </AppText>
                      </View>
                      <View style={{ alignItems: "flex-end", gap: 6 }}>
                        <Tag label={t(`eventStatus.${event.status}`)} tone="primary" />
                        <Tag
                          label={t(`eventPriority.${event.priority}`)}
                          tone="muted"
                        />
                      </View>
                    </View>
                    {event.serviceType || event.eventType ? (
                      <AppText muted>
                        {[event.serviceType, event.eventType]
                          .filter(Boolean)
                          .join(" · ")}
                      </AppText>
                    ) : null}
                    {event.notes ? <AppText muted>{event.notes}</AppText> : null}
                  </View>
                ))}
              </View>
            ) : null}
          </Card>
        </View>
      </View>
    </AppShell>
  );
}

function OptionGroup<T extends string>({
  label,
  options,
  selected,
  onChange,
}: {
  label: string;
  options: Array<{ value: T; label: string }>;
  selected: T;
  onChange: (value: T) => void;
}) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: 8 }}>
      <AppText variant="subtitle">{label}</AppText>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 10 }}>
        {options.map((option) => {
          const active = option.value === selected;

          return (
            <Pressable
              key={option.value}
              onPress={() => onChange(option.value)}
              style={{
                backgroundColor: active
                  ? theme.colors.primary
                  : theme.colors.surfaceMuted,
                borderColor: active
                  ? theme.colors.primary
                  : theme.colors.borderStrong,
                borderRadius: theme.radius.pill,
                borderWidth: 1,
                paddingHorizontal: 14,
                paddingVertical: 10,
              }}
            >
              <AppText
                style={{
                  color: active
                    ? theme.colors.primaryContrast
                    : theme.colors.text,
                }}
              >
                {option.label}
              </AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

function Tag({
  label,
  tone,
}: {
  label: string;
  tone: "primary" | "muted";
}) {
  const { theme } = useAppTheme();
  const active = tone === "primary";

  return (
    <View
      style={{
        backgroundColor: active ? theme.colors.primary : theme.colors.surface,
        borderColor: active ? theme.colors.primary : theme.colors.borderStrong,
        borderRadius: theme.radius.pill,
        borderWidth: 1,
        paddingHorizontal: 12,
        paddingVertical: 6,
      }}
    >
      <AppText
        variant="caption"
        style={{
          color: active ? theme.colors.primaryContrast : theme.colors.text,
        }}
      >
        {label}
      </AppText>
    </View>
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
