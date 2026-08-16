import { Controller, type Control, type FieldErrors } from "react-hook-form";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { GuestCountEditor } from "@/components/patterns/guest-count-editor";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { MultiSelect } from "@/components/primitives/multi-select";
import { Select } from "@/components/primitives/select";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import { TimezonePicker } from "@/components/primitives/timezone-picker";
import { UserPicker, type UserPickerOption } from "@/components/primitives/user-picker";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  EVENT_PRIORITY_VALUES,
  EVENT_STATUS_VALUES,
  resolveTranslatedOptionLabel,
  type EventFormFieldName,
  type EventFormValues,
  type TranslatedSelectOption,
} from "@/features/events/forms";

type EventFormFieldsProps = {
  clientOptions?: EntityPickerOption<string>[];
  compact?: boolean;
  contactOptions?: EntityPickerOption<string>[];
  control: Control<EventFormValues>;
  disabled?: boolean;
  disabledFields?: readonly EventFormFieldName[];
  errors: FieldErrors<EventFormValues>;
  eventGroupOptions?: EntityPickerOption<string>[];
  eventTypeOptions?: TranslatedSelectOption[];
  hiddenFields?: readonly EventFormFieldName[];
  serviceTypeOptions?: TranslatedSelectOption[];
  staffOptions?: UserPickerOption<string>[];
  tagOptions?: TranslatedSelectOption[];
  timeZone: string;
  timeZones?: string[];
  venueOptions?: EntityPickerOption<string>[];
  memberOptions?: UserPickerOption<string>[];
  showEventType?: boolean;
  showPriority?: boolean;
};

function FieldCell({
  children,
  fullWidth = false,
}: {
  children: React.ReactNode;
  fullWidth?: boolean;
}) {
  return (
    <View
      style={{
        flexBasis: fullWidth ? "100%" : 240,
        flexGrow: 1,
        minWidth: fullWidth ? "100%" : 240,
      }}
    >
      {children}
    </View>
  );
}

export function EventFormFields({
  clientOptions,
  compact = false,
  contactOptions,
  control,
  disabled = false,
  disabledFields = [],
  errors,
  eventGroupOptions,
  eventTypeOptions,
  hiddenFields = [],
  serviceTypeOptions,
  showEventType = false,
  showPriority = false,
  staffOptions,
  tagOptions,
  timeZone,
  timeZones,
  venueOptions,
  memberOptions,
}: EventFormFieldsProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const isHidden = (fieldName: EventFormFieldName) => hiddenFields.includes(fieldName);
  const isFieldDisabled = (fieldName: EventFormFieldName) =>
    disabled || disabledFields.includes(fieldName);
  const serviceOptions = (serviceTypeOptions ?? []).map((option) => ({
    disabled: option.disabled,
    label: resolveTranslatedOptionLabel(option, t),
    value: option.value,
  }));
  const typeOptions = (eventTypeOptions ?? []).map((option) => ({
    disabled: option.disabled,
    label: resolveTranslatedOptionLabel(option, t),
    value: option.value,
  }));
  const resolvedTagOptions = (tagOptions ?? []).map((option) => ({
    disabled: option.disabled,
    label: resolveTranslatedOptionLabel(option, t),
    value: option.value,
  }));

  return (
    <View style={{ gap: compact ? theme.spacing[3] : theme.spacing[4] }}>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
        {isHidden("name") ? null : (
          <FieldCell fullWidth>
            <Controller
              control={control}
              name="name"
              render={({ field }) => (
                <TextField
                  accessibilityLabel={t("events.form.fields.name.accessibilityLabel")}
                  editable={!isFieldDisabled("name")}
                  error={errors.name?.message}
                  label={t("events.form.fields.name.label")}
                  onBlur={field.onBlur}
                  onChangeText={field.onChange}
                  placeholder={t("events.form.fields.name.placeholder")}
                  required
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("startsAt") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="startsAt"
              render={({ field }) => (
                <DateTimeField
                  accessibilityLabel={t("events.form.fields.startsAt.accessibilityLabel")}
                  editable={!isFieldDisabled("startsAt")}
                  error={errors.startsAt?.message}
                  label={t("events.form.fields.startsAt.label")}
                  locale={undefined}
                  onBlur={field.onBlur}
                  onChange={(nextValue) => field.onChange(nextValue ?? "")}
                  required
                  timeZone={timeZone}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("endsAt") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="endsAt"
              render={({ field }) => (
                <DateTimeField
                  accessibilityLabel={t("events.form.fields.endsAt.accessibilityLabel")}
                  editable={!isFieldDisabled("endsAt")}
                  error={errors.endsAt?.message}
                  helperText={t("events.form.fields.endsAt.helper")}
                  label={t("events.form.fields.endsAt.label")}
                  locale={undefined}
                  onBlur={field.onBlur}
                  onChange={field.onChange}
                  timeZone={timeZone}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("timezone") ? null : (
          <FieldCell fullWidth={compact}>
            <Controller
              control={control}
              name="timezone"
              render={({ field }) => (
                <TimezonePicker
                  accessibilityLabel={t("events.form.fields.timezone.accessibilityLabel")}
                  disabled={isFieldDisabled("timezone")}
                  error={errors.timezone?.message}
                  helperText={t("events.form.fields.timezone.helper")}
                  label={t("events.form.fields.timezone.label")}
                  onChange={field.onChange}
                  timeZones={timeZones}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("guestCountExpected") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="guestCountExpected"
              render={({ field }) => (
                <GuestCountEditor
                  accessibilityLabel={t("events.form.fields.guestCountExpected.accessibilityLabel")}
                  disabled={isFieldDisabled("guestCountExpected")}
                  error={errors.guestCountExpected?.message}
                  helperText={t("events.form.fields.guestCountExpected.helper")}
                  label={t("events.form.fields.guestCountExpected.label")}
                  onChange={field.onChange}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("status") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="status"
              render={({ field }) => (
                <StatusSelect
                  accessibilityLabel={t("events.form.fields.status.accessibilityLabel")}
                  disabled={isFieldDisabled("status")}
                  error={errors.status?.message}
                  label={t("events.form.fields.status.label")}
                  namespace="events"
                  onChange={(nextValue) => field.onChange(nextValue)}
                  options={EVENT_STATUS_VALUES.map((value) => ({ value }))}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {!showPriority || isHidden("priority") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="priority"
              render={({ field }) => (
                <Select
                  accessibilityLabel={t("events.form.fields.priority.accessibilityLabel")}
                  disabled={isFieldDisabled("priority")}
                  error={errors.priority?.message}
                  label={t("events.form.fields.priority.label")}
                  onChange={(nextValue) => field.onChange(nextValue)}
                  options={EVENT_PRIORITY_VALUES.map((value) => ({
                    label: t(`events.priority.${value}`),
                    value,
                  }))}
                  value={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("serviceType") ? null : serviceOptions.length > 0 ? (
          <FieldCell>
            <Controller
              control={control}
              name="serviceType"
              render={({ field }) => (
                <Select
                  accessibilityLabel={t("events.form.fields.serviceType.accessibilityLabel")}
                  disabled={isFieldDisabled("serviceType")}
                  error={errors.serviceType?.message}
                  label={t("events.form.fields.serviceType.label")}
                  onChange={field.onChange}
                  options={serviceOptions}
                  placeholder={t("events.form.fields.serviceType.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        ) : (
          <FieldCell>
            <Controller
              control={control}
              name="serviceType"
              render={({ field }) => (
                <TextField
                  accessibilityLabel={t("events.form.fields.serviceType.accessibilityLabel")}
                  editable={!isFieldDisabled("serviceType")}
                  error={errors.serviceType?.message}
                  label={t("events.form.fields.serviceType.label")}
                  onBlur={field.onBlur}
                  onChangeText={field.onChange}
                  placeholder={t("events.form.fields.serviceType.placeholder")}
                  value={field.value ?? ""}
                />
              )}
            />
          </FieldCell>
        )}
        {!showEventType || isHidden("eventType") ? null : typeOptions.length > 0 ? (
          <FieldCell>
            <Controller
              control={control}
              name="eventType"
              render={({ field }) => (
                <Select
                  accessibilityLabel={t("events.form.fields.eventType.accessibilityLabel")}
                  disabled={isFieldDisabled("eventType")}
                  error={errors.eventType?.message}
                  label={t("events.form.fields.eventType.label")}
                  onChange={field.onChange}
                  options={typeOptions}
                  placeholder={t("events.form.fields.eventType.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        ) : (
          <FieldCell>
            <Controller
              control={control}
              name="eventType"
              render={({ field }) => (
                <TextField
                  accessibilityLabel={t("events.form.fields.eventType.accessibilityLabel")}
                  editable={!isFieldDisabled("eventType")}
                  error={errors.eventType?.message}
                  label={t("events.form.fields.eventType.label")}
                  onBlur={field.onBlur}
                  onChangeText={field.onChange}
                  placeholder={t("events.form.fields.eventType.placeholder")}
                  value={field.value ?? ""}
                />
              )}
            />
          </FieldCell>
        )}
        {!eventGroupOptions?.length || isHidden("eventGroupId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="eventGroupId"
              render={({ field }) => (
                <EntityPicker
                  accessibilityLabel={t("events.form.fields.eventGroup.accessibilityLabel")}
                  disabled={isFieldDisabled("eventGroupId")}
                  error={errors.eventGroupId?.message}
                  entities={eventGroupOptions}
                  label={t("events.form.fields.eventGroup.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.eventGroup.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!clientOptions?.length || isHidden("clientId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="clientId"
              render={({ field }) => (
                <EntityPicker
                  accessibilityLabel={t("events.form.fields.client.accessibilityLabel")}
                  disabled={isFieldDisabled("clientId")}
                  error={errors.clientId?.message}
                  entities={clientOptions}
                  label={t("events.form.fields.client.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.client.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!contactOptions?.length || isHidden("contactId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="contactId"
              render={({ field }) => (
                <EntityPicker
                  accessibilityLabel={t("events.form.fields.contact.accessibilityLabel")}
                  disabled={isFieldDisabled("contactId")}
                  entities={contactOptions}
                  error={errors.contactId?.message}
                  label={t("events.form.fields.contact.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.contact.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!venueOptions?.length || isHidden("venueId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="venueId"
              render={({ field }) => (
                <EntityPicker
                  accessibilityLabel={t("events.form.fields.venue.accessibilityLabel")}
                  disabled={isFieldDisabled("venueId")}
                  entities={venueOptions}
                  error={errors.venueId?.message}
                  label={t("events.form.fields.venue.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.venue.placeholder")}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!memberOptions?.length || isHidden("responsibleMemberId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="responsibleMemberId"
              render={({ field }) => (
                <UserPicker
                  accessibilityLabel={t("events.form.fields.responsibleMember.accessibilityLabel")}
                  disabled={isFieldDisabled("responsibleMemberId")}
                  error={errors.responsibleMemberId?.message}
                  label={t("events.form.fields.responsibleMember.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.responsibleMember.placeholder")}
                  users={memberOptions}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!staffOptions?.length || isHidden("staffMemberId") ? null : (
          <FieldCell>
            <Controller
              control={control}
              name="staffMemberId"
              render={({ field }) => (
                <UserPicker
                  accessibilityLabel={t("events.form.fields.staff.accessibilityLabel")}
                  disabled={isFieldDisabled("staffMemberId")}
                  error={errors.staffMemberId?.message}
                  label={t("events.form.fields.staff.label")}
                  onChange={field.onChange}
                  placeholder={t("events.form.fields.staff.placeholder")}
                  users={staffOptions}
                  value={field.value ?? undefined}
                />
              )}
            />
          </FieldCell>
        )}
        {!resolvedTagOptions.length || isHidden("tags") ? null : (
          <FieldCell fullWidth>
            <Controller
              control={control}
              name="tags"
              render={({ field }) => (
                <MultiSelect
                  accessibilityLabel={t("events.form.fields.tags.accessibilityLabel")}
                  disabled={isFieldDisabled("tags")}
                  error={errors.tags?.message as string | undefined}
                  label={t("events.form.fields.tags.label")}
                  onChange={field.onChange}
                  options={resolvedTagOptions}
                  placeholder={t("events.form.fields.tags.placeholder")}
                  values={field.value}
                />
              )}
            />
          </FieldCell>
        )}
        {isHidden("notes") ? null : (
          <FieldCell fullWidth>
            <Controller
              control={control}
              name="notes"
              render={({ field }) => (
                <TextArea
                  accessibilityLabel={t("events.form.fields.notes.accessibilityLabel")}
                  autoGrow
                  editable={!isFieldDisabled("notes")}
                  error={errors.notes?.message}
                  label={t("events.form.fields.notes.label")}
                  minHeight={theme.spacing[16]}
                  onBlur={field.onBlur}
                  onChangeText={field.onChange}
                  placeholder={t("events.form.fields.notes.placeholder")}
                  value={field.value ?? ""}
                />
              )}
            />
          </FieldCell>
        )}
      </View>
    </View>
  );
}
