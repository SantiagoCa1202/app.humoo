import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { PrepAssignment } from "@/components/patterns/prep-assignment";
import { PrepStatusBadge } from "@/components/patterns/prep-status-badge";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createPrepItemValues,
  getPrepPrimaryAssignment,
  hasPrepItemErrors,
  normalizePrepItemValues,
  PREP_ITEM_STATUS_VALUES,
  type PrepAssignmentOption,
  type PrepItemEditorValues,
  type PrepItemRecord,
  type PrepUnitOption,
  type PrepItemValidationErrors,
  validatePrepItemValues,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: PrepItemValidationErrors,
  externalErrors?: PrepItemValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies PrepItemValidationErrors;
}

export type PrepItemEditorProps = {
  accessibilityLabel?: string;
  assignmentOptions?: PrepAssignmentOption[];
  compact?: boolean;
  disabled?: boolean;
  initialValue?: Partial<PrepItemRecord>;
  onCancel?: () => void;
  onSubmit: (value: PrepItemEditorValues) => void | Promise<void>;
  submitting?: boolean;
  timeZone: string;
  unitOptions?: PrepUnitOption[];
  validationErrors?: PrepItemValidationErrors;
};

export function PrepItemEditor({
  accessibilityLabel,
  assignmentOptions,
  compact = false,
  disabled = false,
  initialValue,
  onCancel,
  onSubmit,
  submitting = false,
  timeZone,
  unitOptions,
  validationErrors,
}: PrepItemEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValue ?? {});
  const defaultValues = useMemo(() => createPrepItemValues(initialValue), [initialSignature]);
  const [values, setValues] = useState<PrepItemEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<PrepItemValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const assignment = getPrepPrimaryAssignment(values.assignments);

  const handleSubmit = async () => {
    const normalized = normalizePrepItemValues(values);
    const nextErrors = validatePrepItemValues(normalized, t);

    if (hasPrepItemErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("prep.form.accessibilityLabel")}
      cancelLabel={t("prep.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("prep.actions.saveChanges")}
      submitting={submitting}
      subtitle={
        values.status ? (
          <PrepStatusBadge namespace="prepTasks" size="sm" status={values.status} />
        ) : undefined
      }
      title={t("prep.form.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          editable={!disabled}
          error={resolvedErrors.title}
          label={t("prep.form.fields.title.label")}
          onChangeText={(title) => setValues((current) => ({ ...current, title }))}
          placeholder={t("prep.form.fields.title.placeholder")}
          required
          value={values.title}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("prep.form.fields.description.label")}
          onChangeText={(description) => setValues((current) => ({ ...current, description }))}
          placeholder={t("prep.form.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        {unitOptions?.length ? (
          <QuantityInput
            disabled={disabled}
            error={resolvedErrors.quantity ?? resolvedErrors.unitId}
            label={t("prep.form.fields.quantity.label")}
            onChange={(quantity) => setValues((current) => ({ ...current, quantity }))}
            onUnitChange={(unitId) => {
              const unit = unitOptions.find((option) => option.value === unitId);
              setValues((current) => ({
                ...current,
                unit: unit ? { id: unitId, symbol: unit.label } : null,
                unitId,
              }));
            }}
            step={0.01}
            unit={values.unitId ?? undefined}
            units={unitOptions}
            value={values.quantity ?? 0}
          />
        ) : null}
        <StatusSelect
          disabled={disabled}
          error={resolvedErrors.status}
          label={t("prep.form.fields.status.label")}
          namespace="prepTasks"
          onChange={(status) =>
            setValues((current) => ({
              ...current,
              status: status as PrepItemEditorValues["status"],
            }))
          }
          options={PREP_ITEM_STATUS_VALUES.map((status) => ({ value: status }))}
          value={values.status ?? undefined}
        />
        <PrepAssignment
          assignment={assignment}
          candidates={assignmentOptions}
          compact={compact}
          disabled={disabled}
          editable={Boolean(assignmentOptions?.length)}
          onChange={(membershipId) =>
            setValues((current) => {
              const selectedCandidate = assignmentOptions?.find(
                (candidate) => candidate.value === membershipId
              );

              return {
                ...current,
                assignments: [
                  {
                    ...(assignment ?? {}),
                    id: assignment?.id ?? null,
                    isPrimary: true,
                    membershipId,
                    roleLabel:
                      selectedCandidate?.roleLabel ??
                      assignment?.roleLabel ??
                      null,
                    user: selectedCandidate
                      ? {
                          name: selectedCandidate.label ?? null,
                          source: selectedCandidate.avatarSource,
                        }
                      : null,
                  },
                ],
              };
            })
          }
          onClear={() => setValues((current) => ({ ...current, assignments: [] }))}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <DateTimeField
              editable={!disabled}
              error={resolvedErrors.startsAt}
              label={t("prep.form.fields.startsAt.label")}
              onChange={(startsAt) => setValues((current) => ({ ...current, startsAt }))}
              timeZone={timeZone}
              value={values.startsAt}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <DateTimeField
              editable={!disabled}
              error={resolvedErrors.dueAt}
              label={t("prep.form.fields.dueAt.label")}
              onChange={(dueAt) => setValues((current) => ({ ...current, dueAt }))}
              timeZone={timeZone}
              value={values.dueAt}
            />
          </View>
        </View>
        <TextField
          editable={!disabled}
          error={resolvedErrors.blockedReason}
          label={t("prep.form.fields.blockedReason.label")}
          onChangeText={(blockedReason) =>
            setValues((current) => ({ ...current, blockedReason }))
          }
          placeholder={t("prep.form.fields.blockedReason.placeholder")}
          value={values.blockedReason ?? ""}
        />
        <TextArea
          autoGrow
          editable={!disabled}
          error={resolvedErrors.notes}
          label={t("prep.form.fields.notes.label")}
          onChangeText={(notes) => setValues((current) => ({ ...current, notes }))}
          placeholder={t("prep.form.fields.notes.placeholder")}
          value={values.notes ?? ""}
        />
      </View>
    </FormCard>
  );
}
