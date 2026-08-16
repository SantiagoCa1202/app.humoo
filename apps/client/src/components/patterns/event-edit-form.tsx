import { useTranslation } from "react-i18next";

import { EventForm, type EventFormBaseProps } from "@/components/patterns/event-form";
import { mapEventRecordToFormValues, type EventFormValues } from "@/features/events/forms";
import type { EventRecord } from "@/features/events";

export type EventEditFormProps = Omit<
  EventFormBaseProps,
  "initialValues" | "requireDirtyToSubmit" | "submitLabel"
> & {
  event: EventRecord;
  initialValues?: Partial<EventFormValues>;
};

export function EventEditForm({
  event,
  initialValues,
  ...props
}: EventEditFormProps) {
  const { t } = useTranslation("common");

  return (
    <EventForm
      {...props}
      initialValues={mapEventRecordToFormValues(event, initialValues)}
      requireDirtyToSubmit
      submitLabel={t("events.actions.saveChanges")}
    />
  );
}
