import type { TFunction } from "i18next";
import { z } from "zod";

import type {
  CreateEventInput,
  EventPriority,
  EventRecord,
  EventStatus,
} from "@/features/events/types";
import { getNextRoundedIsoDateTime, isValidTimeZone } from "@/utils/date-time";

export const EVENT_STATUS_VALUES = [
  "draft",
  "tentative",
  "confirmed",
  "in_production",
  "completed",
  "cancelled",
] as const satisfies readonly EventStatus[];

export const EVENT_PRIORITY_VALUES = [
  "low",
  "normal",
  "high",
  "urgent",
] as const satisfies readonly EventPriority[];

export type EventFilters = {
  clientId?: string | null;
  dateFrom?: string | null;
  dateTo?: string | null;
  memberId?: string | null;
  search?: string;
  serviceType?: string | null;
  statuses?: EventStatus[];
  venueId?: string | null;
};

export type EventFormValues = Omit<CreateEventInput, "guestCountExpected"> & {
  guestCountExpected: number;
  responsibleMemberId: string | null;
  staffMemberId: string | null;
  tags: string[];
};

export type EventFormPayload = EventFormValues;

export type EventFormFieldName = keyof EventFormValues;
export type EventFormValidationErrors = Partial<
  Record<EventFormFieldName | "form", string>
>;

export type TranslatedSelectOption<T extends string = string> = {
  disabled?: boolean;
  label?: string;
  translationKey?: string;
  value: T;
};

export function resolveTranslatedOptionLabel<T extends string>(
  option: TranslatedSelectOption<T>,
  t: TFunction<"common">
) {
  if (option.translationKey) {
    return t(option.translationKey);
  }

  return option.label ?? option.value;
}

export function createEmptyEventFilters(): Required<EventFilters> {
  return {
    clientId: null,
    dateFrom: null,
    dateTo: null,
    memberId: null,
    search: "",
    serviceType: null,
    statuses: [],
    venueId: null,
  };
}

export function createDefaultEventFormValues(
  timeZone?: string,
  initialValues?: Partial<EventFormValues>
): EventFormValues {
  const resolvedTimeZone = initialValues?.timezone ?? timeZone ?? "UTC";

  return {
    clientId: initialValues?.clientId ?? null,
    contactId: initialValues?.contactId ?? null,
    endsAt: initialValues?.endsAt ?? null,
    eventGroupId: initialValues?.eventGroupId ?? null,
    eventType: initialValues?.eventType ?? null,
    guestCountExpected: initialValues?.guestCountExpected ?? 0,
    name: initialValues?.name ?? "",
    notes: initialValues?.notes ?? null,
    priority: initialValues?.priority ?? "normal",
    responsibleMemberId: initialValues?.responsibleMemberId ?? null,
    serviceType: initialValues?.serviceType ?? null,
    staffMemberId: initialValues?.staffMemberId ?? null,
    startsAt: initialValues?.startsAt ?? getNextRoundedIsoDateTime(),
    status: initialValues?.status ?? "draft",
    tags: initialValues?.tags ?? [],
    timezone: resolvedTimeZone,
    venueId: initialValues?.venueId ?? null,
  };
}

export function buildEventFormSchema(t: TFunction<"common">) {
  const schema: z.ZodType<EventFormValues> = z
    .object({
      clientId: z.string().trim().nullable(),
      contactId: z.string().trim().nullable(),
      endsAt: z.string().datetime().nullable(),
      eventGroupId: z.string().trim().nullable(),
      eventType: z.string().trim().nullable(),
      guestCountExpected: z
        .number()
        .int(t("events.form.errors.guestsInteger"))
        .min(0, t("events.form.errors.guestsNonNegative")),
      name: z
        .string()
        .trim()
        .min(1, t("events.form.errors.nameRequired")),
      notes: z.string().trim().nullable(),
      priority: z.enum(EVENT_PRIORITY_VALUES),
      responsibleMemberId: z.string().trim().nullable(),
      serviceType: z.string().trim().nullable(),
      staffMemberId: z.string().trim().nullable(),
      startsAt: z
        .string()
        .min(1, t("events.form.errors.startsAtRequired"))
        .datetime(t("events.form.errors.startsAtRequired")),
      status: z.enum(EVENT_STATUS_VALUES),
      tags: z.array(z.string().trim()),
      timezone: z
        .string()
        .trim()
        .min(1, t("events.form.errors.timezoneRequired"))
        .refine((value) => isValidTimeZone(value), {
          message: t("events.form.errors.timezoneRequired"),
        }),
      venueId: z.string().trim().nullable(),
    })
    .refine(
      (values) => {
        if (!values.endsAt) {
          return true;
        }

        return new Date(values.endsAt).getTime() > new Date(values.startsAt).getTime();
      },
      {
        message: t("events.form.errors.endsAtAfterStartsAt"),
        path: ["endsAt"],
      }
    );

  return schema;
}

export function normalizeEventFormValues(values: EventFormValues): EventFormPayload {
  const trimOrNull = (nextValue?: string | null) => {
    const normalizedValue = nextValue?.trim();
    return normalizedValue ? normalizedValue : null;
  };

  return {
    clientId: trimOrNull(values.clientId),
    contactId: trimOrNull(values.contactId),
    endsAt: trimOrNull(values.endsAt),
    eventGroupId: trimOrNull(values.eventGroupId),
    eventType: trimOrNull(values.eventType),
    guestCountExpected:
      typeof values.guestCountExpected === "number" && Number.isFinite(values.guestCountExpected)
        ? Math.max(0, Math.trunc(values.guestCountExpected))
        : 0,
    name: values.name.trim(),
    notes: trimOrNull(values.notes),
    priority: values.priority,
    responsibleMemberId: trimOrNull(values.responsibleMemberId),
    serviceType: trimOrNull(values.serviceType),
    staffMemberId: trimOrNull(values.staffMemberId),
    startsAt: values.startsAt,
    status: values.status,
    tags: values.tags.map((tag) => tag.trim()).filter(Boolean),
    timezone: values.timezone.trim(),
    venueId: trimOrNull(values.venueId),
  };
}

export function mapEventRecordToFormValues(
  event: EventRecord,
  initialValues?: Partial<EventFormValues>
) {
  return createDefaultEventFormValues(event.timezone, {
    ...initialValues,
    clientId: event.clientId,
    contactId: event.contactId,
    endsAt: event.endsAt,
    eventGroupId: event.eventGroupId,
    eventType: event.eventType,
    guestCountExpected: event.guestCountExpected ?? 0,
    name: event.name,
    notes: event.notes,
    priority: event.priority,
    serviceType: event.serviceType,
    startsAt: event.startsAt,
    status: event.status,
    timezone: event.timezone,
    venueId: event.venueId,
  });
}
