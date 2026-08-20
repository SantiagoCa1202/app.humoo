import { useEffect, useMemo } from "react";
import { useForm, type Resolver } from "react-hook-form";
import { View } from "react-native";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { EventFormFields } from "@/components/patterns/event-form-fields";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  buildEventFormSchema,
  createDefaultEventFormValues,
  normalizeEventFormValues,
  type EventFormFieldName,
  type EventFormPayload,
  type EventFormValidationErrors,
  type EventFormValues,
  type TranslatedSelectOption,
} from "@/features/events/forms";
import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { UserPickerOption } from "@/components/primitives/user-picker";

export type EventFormBaseProps = {
  accessibilityLabel?: string;
  clientOptions?: EntityPickerOption<string>[];
  compact?: boolean;
  contactOptions?: EntityPickerOption<string>[];
  disabled?: boolean;
  disabledFields?: readonly EventFormFieldName[];
  eventGroupOptions?: EntityPickerOption<string>[];
  eventTypeOptions?: TranslatedSelectOption[];
  hiddenFields?: readonly EventFormFieldName[];
  initialValues?: Partial<EventFormValues>;
  memberOptions?: UserPickerOption<string>[];
  onClientIdChange?: (clientId: string | null) => void;
  onCancel?: () => void;
  onSubmit: (payload: EventFormPayload) => void | Promise<void>;
  serviceTypeOptions?: TranslatedSelectOption[];
  showEventType?: boolean;
  showPriority?: boolean;
  staffOptions?: UserPickerOption<string>[];
  submitting?: boolean;
  submitLabel: string;
  tagOptions?: TranslatedSelectOption[];
  timeZones?: string[];
  validationErrors?: EventFormValidationErrors;
  venueOptions?: EntityPickerOption<string>[];
  requireDirtyToSubmit?: boolean;
};

export function EventForm({
  accessibilityLabel,
  clientOptions,
  compact = false,
  contactOptions,
  disabled = false,
  disabledFields,
  eventGroupOptions,
  eventTypeOptions,
  hiddenFields,
  initialValues,
  memberOptions,
  onClientIdChange,
  onCancel,
  onSubmit,
  requireDirtyToSubmit = false,
  serviceTypeOptions,
  showEventType = false,
  showPriority = false,
  staffOptions,
  submitting = false,
  submitLabel,
  tagOptions,
  timeZones,
  validationErrors,
  venueOptions,
}: EventFormBaseProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedTimeZone =
    initialValues?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC";
  const initialValuesSignature = JSON.stringify(initialValues ?? {});
  const schema = useMemo(() => buildEventFormSchema(t), [t]);
  const defaultValues = useMemo(
    () => createDefaultEventFormValues(resolvedTimeZone, initialValues),
    [initialValuesSignature, resolvedTimeZone]
  );
  const {
    control,
    handleSubmit,
    reset,
    setError,
    setValue,
    watch,
    formState: { errors, isDirty, isSubmitting },
  } = useForm<EventFormValues, undefined, EventFormValues>({
    defaultValues,
    resolver: zodResolver(schema as never) as unknown as Resolver<EventFormValues>,
  });
  const watchedClientId = watch("clientId");
  const watchedContactId = watch("contactId");
  const watchedTimeZone = watch("timezone");

  useEffect(() => {
    reset(defaultValues);
  }, [defaultValues, reset]);

  useEffect(() => {
    onClientIdChange?.(watchedClientId ?? null);
  }, [onClientIdChange, watchedClientId]);

  useEffect(() => {
    if (!watchedContactId || !contactOptions) {
      return;
    }

    const hasMatchingContact = contactOptions.some(
      (option) => option.value === watchedContactId
    );

    if (!hasMatchingContact) {
      setValue("contactId", null, {
        shouldDirty: true,
        shouldValidate: true,
      });
    }
  }, [contactOptions, setValue, watchedContactId]);

  useEffect(() => {
    if (!validationErrors) {
      return;
    }

    for (const [fieldName, message] of Object.entries(validationErrors)) {
      if (!message || fieldName === "form") {
        continue;
      }

      setError(fieldName as EventFormFieldName, {
        message,
        type: "manual",
      });
    }
  }, [setError, validationErrors]);

  const isSubmitDisabled =
    disabled ||
    submitting ||
    isSubmitting ||
    (requireDirtyToSubmit && !isDirty);

  return (
    <View
      accessibilityLabel={accessibilityLabel}
      style={{ gap: compact ? theme.spacing[3] : theme.spacing[4] }}
    >
      {validationErrors?.form ? (
        <Text selectable tone="danger" variant="bodySmall">
          {validationErrors.form}
        </Text>
      ) : null}
      <EventFormFields
        clientOptions={clientOptions}
        compact={compact}
        contactOptions={contactOptions}
        control={control}
        disabled={disabled}
        disabledFields={disabledFields}
        errors={errors}
        eventGroupOptions={eventGroupOptions}
        eventTypeOptions={eventTypeOptions}
        hiddenFields={hiddenFields}
        memberOptions={memberOptions}
        serviceTypeOptions={serviceTypeOptions}
        showEventType={showEventType}
        showPriority={showPriority}
        staffOptions={staffOptions}
        tagOptions={tagOptions}
        timeZone={watchedTimeZone || resolvedTimeZone}
        timeZones={timeZones}
        venueOptions={venueOptions}
      />
      <View
        style={{
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[3],
          justifyContent: "flex-end",
        }}
      >
        {onCancel ? (
          <Button
            accessibilityLabel={t("events.actions.cancel")}
            disabled={disabled || submitting || isSubmitting}
            label={t("events.actions.cancel")}
            onPress={onCancel}
            variant="ghost"
          />
        ) : null}
        <Button
          accessibilityLabel={submitLabel}
          disabled={isSubmitDisabled}
          label={submitLabel}
          loading={submitting || isSubmitting}
          onPress={handleSubmit(async (values) => {
            await onSubmit(normalizeEventFormValues(values));
          })}
          variant="primary"
        />
      </View>
    </View>
  );
}
