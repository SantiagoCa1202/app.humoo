import { useTranslation } from "react-i18next";

import { EventForm, type EventFormBaseProps } from "@/components/patterns/event-form";

export type EventCreateFormProps = Omit<EventFormBaseProps, "requireDirtyToSubmit" | "submitLabel">;

export function EventCreateForm(props: EventCreateFormProps) {
  const { t } = useTranslation("common");

  return <EventForm {...props} submitLabel={t("events.actions.create")} />;
}
