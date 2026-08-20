import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createShiftEditorValues,
  hasShiftEditorErrors,
  normalizeShiftEditorValues,
  SHIFT_STATUS_VALUES,
  type ShiftEditorMode,
  type ShiftEditorValidationErrors,
  type ShiftEditorValues,
  type ShiftEventOption,
  type ShiftMemberOption,
  type ShiftStationOption,
  type ShiftTeamOption,
  validateShiftEditorValues,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: ShiftEditorValidationErrors,
  externalErrors?: ShiftEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies ShiftEditorValidationErrors;
}

export type ShiftEditorProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  eventOptions?: ShiftEventOption[];
  initialValues?: Partial<ShiftEditorValues>;
  memberOptions: ShiftMemberOption[];
  mode?: ShiftEditorMode;
  onCancel?: () => void;
  onSubmit: (value: ShiftEditorValues) => void | Promise<void>;
  stationOptions?: ShiftStationOption[];
  submitting?: boolean;
  teamOptions?: ShiftTeamOption[];
  timeZone: string;
  validationErrors?: ShiftEditorValidationErrors;
};

export function ShiftEditor({
  accessibilityLabel,
  disabled = false,
  eventOptions,
  initialValues,
  memberOptions,
  mode = "create",
  onCancel,
  onSubmit,
  stationOptions,
  submitting = false,
  teamOptions,
  timeZone,
  validationErrors,
}: ShiftEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createShiftEditorValues({
      timezone: timeZone,
      ...initialValues,
    }),
    [initialSignature, timeZone]
  );
  const [values, setValues] = useState<ShiftEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<ShiftEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);

  const handleSubmit = async () => {
    const normalized = normalizeShiftEditorValues(values);
    const nextErrors = validateShiftEditorValues(normalized, t);

    if (hasShiftEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.shiftEditor.accessibilityLabel")}
      cancelLabel={t("teamStaff.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={
        mode === "edit"
          ? t("teamStaff.actions.saveChanges")
          : t("teamStaff.shiftEditor.actions.create")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("teamStaff.shiftEditor.editTitle")
          : t("teamStaff.shiftEditor.createTitle")
      }
    >
      <View style={{ gap: theme.spacing[4] }}>
        <EntityPicker
          accessibilityLabel={t("teamStaff.shiftEditor.fields.member.accessibilityLabel")}
          disabled={disabled}
          entities={memberOptions}
          error={resolvedErrors.membershipId}
          label={t("teamStaff.shiftEditor.fields.member.label")}
          onChange={(membershipId) => setValues((current) => ({ ...current, membershipId }))}
          placeholder={t("teamStaff.shiftEditor.fields.member.placeholder")}
          value={values.membershipId || undefined}
        />
        {teamOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("teamStaff.shiftEditor.fields.team.accessibilityLabel")}
            disabled={disabled}
            entities={teamOptions}
            error={resolvedErrors.teamId}
            label={t("teamStaff.shiftEditor.fields.team.label")}
            onChange={(teamId) => setValues((current) => ({ ...current, teamId }))}
            placeholder={t("teamStaff.shiftEditor.fields.team.placeholder")}
            value={values.teamId ?? undefined}
          />
        ) : null}
        {stationOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("teamStaff.shiftEditor.fields.station.accessibilityLabel")}
            disabled={disabled}
            entities={stationOptions}
            error={resolvedErrors.stationId}
            label={t("teamStaff.shiftEditor.fields.station.label")}
            onChange={(stationId) => setValues((current) => ({ ...current, stationId }))}
            placeholder={t("teamStaff.shiftEditor.fields.station.placeholder")}
            value={values.stationId ?? undefined}
          />
        ) : null}
        {eventOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("teamStaff.shiftEditor.fields.event.accessibilityLabel")}
            disabled={disabled}
            entities={eventOptions}
            error={resolvedErrors.eventId}
            label={t("teamStaff.shiftEditor.fields.event.label")}
            onChange={(eventId) => setValues((current) => ({ ...current, eventId }))}
            placeholder={t("teamStaff.shiftEditor.fields.event.placeholder")}
            value={values.eventId ?? undefined}
          />
        ) : null}
        <DateTimeField
          error={resolvedErrors.startsAt}
          label={t("teamStaff.shiftEditor.fields.startsAt.label")}
          onChange={(startsAt) => setValues((current) => ({ ...current, startsAt }))}
          required
          timeZone={values.timezone ?? timeZone}
          value={values.startsAt ?? null}
        />
        <DateTimeField
          error={resolvedErrors.endsAt}
          label={t("teamStaff.shiftEditor.fields.endsAt.label")}
          onChange={(endsAt) => setValues((current) => ({ ...current, endsAt }))}
          required
          timeZone={values.timezone ?? timeZone}
          value={values.endsAt ?? null}
        />
        <TextField
          error={resolvedErrors.timezone}
          label={t("teamStaff.shiftEditor.fields.timezone.label")}
          onChangeText={(timezone) => setValues((current) => ({ ...current, timezone }))}
          required
          value={values.timezone ?? timeZone}
        />
        <TextField
          label={t("teamStaff.shiftEditor.fields.role.label")}
          onChangeText={(role) => setValues((current) => ({ ...current, role }))}
          placeholder={t("teamStaff.shiftEditor.fields.role.placeholder")}
          value={values.role ?? ""}
        />
        <StatusSelect
          error={resolvedErrors.status}
          label={t("teamStaff.shiftEditor.fields.status.label")}
          namespace="shifts"
          onChange={(status) =>
            setValues((current) => ({
              ...current,
              status: status as ShiftEditorValues["status"],
            }))
          }
          options={SHIFT_STATUS_VALUES.map((status) => ({ value: status }))}
          value={values.status ?? undefined}
        />
        <TextArea
          autoGrow
          label={t("teamStaff.shiftEditor.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("teamStaff.shiftEditor.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
      </View>
    </FormCard>
  );
}
