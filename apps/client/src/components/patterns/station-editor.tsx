import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { Select } from "@/components/primitives/select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createStationEditorValues,
  hasStationEditorErrors,
  normalizeStationEditorValues,
  STATION_STATUS_VALUES,
  type StationEditorMode,
  type StationEditorValidationErrors,
  type StationEditorValues,
  type StationTeamOption,
  validateStationEditorValues,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: StationEditorValidationErrors,
  externalErrors?: StationEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies StationEditorValidationErrors;
}

export type StationEditorProps = {
  accessibilityLabel?: string;
  availableStatuses?: readonly string[];
  disabled?: boolean;
  initialValues?: Partial<StationEditorValues>;
  mode?: StationEditorMode;
  onCancel?: () => void;
  onSubmit: (value: StationEditorValues) => void | Promise<void>;
  submitting?: boolean;
  teamOptions?: StationTeamOption[];
  validationErrors?: StationEditorValidationErrors;
};

export function StationEditor({
  accessibilityLabel,
  availableStatuses,
  disabled = false,
  initialValues,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  teamOptions,
  validationErrors,
}: StationEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createStationEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<StationEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<StationEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedStatuses =
    (availableStatuses as readonly string[] | undefined) ?? STATION_STATUS_VALUES;

  const handleSubmit = async () => {
    const normalized = normalizeStationEditorValues(values);
    const nextErrors = validateStationEditorValues(normalized, t);

    if (hasStationEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.stationEditor.accessibilityLabel")}
      cancelLabel={t("teamStaff.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={
        mode === "edit"
          ? t("teamStaff.actions.saveChanges")
          : t("teamStaff.actions.createStation")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("teamStaff.stationEditor.editTitle")
          : t("teamStaff.stationEditor.createTitle")
      }
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          accessibilityLabel={t("teamStaff.stationEditor.fields.name.accessibilityLabel")}
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("teamStaff.stationEditor.fields.name.label")}
          onChangeText={(name) => setValues((current) => ({ ...current, name }))}
          placeholder={t("teamStaff.stationEditor.fields.name.placeholder")}
          required
          value={values.name}
        />
        <TextArea
          accessibilityLabel={t("teamStaff.stationEditor.fields.description.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("teamStaff.stationEditor.fields.description.label")}
          onChangeText={(description) =>
            setValues((current) => ({ ...current, description }))
          }
          placeholder={t("teamStaff.stationEditor.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        {teamOptions?.length ? (
          <EntityPicker
            accessibilityLabel={t("teamStaff.stationEditor.fields.team.accessibilityLabel")}
            disabled={disabled}
            entities={teamOptions}
            error={resolvedErrors.teamId}
            label={t("teamStaff.stationEditor.fields.team.label")}
            onChange={(teamId) => {
              const selectedTeam = teamOptions.find((option) => option.value === teamId);
              setValues((current) => ({
                ...current,
                team: selectedTeam
                  ? {
                      id: teamId,
                      name: selectedTeam.label ?? selectedTeam.name ?? null,
                    }
                  : null,
                teamId,
              }));
            }}
            placeholder={t("teamStaff.stationEditor.fields.team.placeholder")}
            value={values.teamId ?? undefined}
          />
        ) : null}
        <Select
          accessibilityLabel={t("teamStaff.stationEditor.fields.status.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.status}
          label={t("teamStaff.stationEditor.fields.status.label")}
          onChange={(status) =>
            setValues((current) => ({
              ...current,
              status: status as StationEditorValues["status"],
            }))
          }
          options={resolvedStatuses.map((status) => ({
            label: t(`status.${status}`),
            value: status,
          }))}
          placeholder={t("teamStaff.stationEditor.fields.status.placeholder")}
          value={values.status ?? undefined}
        />
      </View>
    </FormCard>
  );
}
