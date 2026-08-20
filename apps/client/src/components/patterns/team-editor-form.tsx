import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { MultiSelect } from "@/components/primitives/multi-select";
import { Select } from "@/components/primitives/select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createTeamEditorValues,
  hasTeamEditorErrors,
  normalizeTeamEditorValues,
  TEAM_STATUS_VALUES,
  type TeamEditorMode,
  type TeamEditorValidationErrors,
  type TeamEditorValues,
  type TeamMemberOption,
  validateTeamEditorValues,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: TeamEditorValidationErrors,
  externalErrors?: TeamEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies TeamEditorValidationErrors;
}

export type TeamEditorFormProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  initialValues?: Partial<TeamEditorValues>;
  memberOptions: TeamMemberOption[];
  mode?: TeamEditorMode;
  onCancel?: () => void;
  onSubmit: (value: TeamEditorValues) => void | Promise<void>;
  submitting?: boolean;
  validationErrors?: TeamEditorValidationErrors;
};

export function TeamEditorForm({
  accessibilityLabel,
  disabled = false,
  initialValues,
  memberOptions,
  mode = "create",
  onCancel,
  onSubmit,
  submitting = false,
  validationErrors,
}: TeamEditorFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createTeamEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<TeamEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<TeamEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);

  const handleSubmit = async () => {
    const normalized = normalizeTeamEditorValues(values);
    const nextErrors = validateTeamEditorValues(normalized, t);

    if (hasTeamEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.teamEditor.accessibilityLabel")}
      cancelLabel={t("teamStaff.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={
        mode === "edit"
          ? t("teamStaff.actions.saveChanges")
          : t("teamStaff.teamEditor.actions.create")
      }
      submitting={submitting}
      title={
        mode === "edit"
          ? t("teamStaff.teamEditor.editTitle")
          : t("teamStaff.teamEditor.createTitle")
      }
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          editable={!disabled}
          error={resolvedErrors.name}
          label={t("teamStaff.teamEditor.fields.name.label")}
          onChangeText={(name) => setValues((current) => ({ ...current, name }))}
          placeholder={t("teamStaff.teamEditor.fields.name.placeholder")}
          required
          value={values.name}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("teamStaff.teamEditor.fields.description.label")}
          onChangeText={(description) =>
            setValues((current) => ({ ...current, description }))
          }
          placeholder={t("teamStaff.teamEditor.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        <TextField
          editable={!disabled}
          label={t("teamStaff.teamEditor.fields.type.label")}
          onChangeText={(type) => setValues((current) => ({ ...current, type }))}
          placeholder={t("teamStaff.teamEditor.fields.type.placeholder")}
          value={values.type ?? ""}
        />
        <MultiSelect
          accessibilityLabel={t("teamStaff.teamEditor.fields.members.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.memberIds}
          label={t("teamStaff.teamEditor.fields.members.label")}
          onChange={(memberIds) => setValues((current) => ({ ...current, memberIds }))}
          options={memberOptions.map((member) => ({
            label: member.label ?? member.value,
            value: member.value,
          }))}
          placeholder={t("teamStaff.teamEditor.fields.members.placeholder")}
          required
          values={values.memberIds}
        />
        {memberOptions.length ? (
          <EntityPicker
            accessibilityLabel={t("teamStaff.teamEditor.fields.lead.accessibilityLabel")}
            disabled={disabled}
            entities={memberOptions}
            error={resolvedErrors.leadMembershipId}
            label={t("teamStaff.teamEditor.fields.lead.label")}
            onChange={(leadMembershipId) =>
              setValues((current) => ({
                ...current,
                leadMembershipId,
              }))
            }
            placeholder={t("teamStaff.teamEditor.fields.lead.placeholder")}
            value={values.leadMembershipId ?? undefined}
          />
        ) : null}
        <Select
          accessibilityLabel={t("teamStaff.teamEditor.fields.status.accessibilityLabel")}
          disabled={disabled}
          error={resolvedErrors.status}
          label={t("teamStaff.teamEditor.fields.status.label")}
          onChange={(status) =>
            setValues((current) => ({
              ...current,
              status: status as TeamEditorValues["status"],
            }))
          }
          options={TEAM_STATUS_VALUES.map((status) => ({
            label: t(`status.${status}`),
            value: status,
          }))}
          placeholder={t("teamStaff.teamEditor.fields.status.placeholder")}
          value={values.status ?? undefined}
        />
      </View>
    </FormCard>
  );
}
