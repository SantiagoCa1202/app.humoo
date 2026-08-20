import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { Button } from "@/components/primitives/button";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { Select } from "@/components/primitives/select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createAvailabilityEditorValues,
  createAvailabilityRecordValues,
  createAvailabilityRuleValues,
  hasAvailabilityEditorErrors,
  normalizeAvailabilityEditorValues,
  validateAvailabilityEditorValues,
  type AvailabilityEditorValidationErrors,
  type AvailabilityEditorValues,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: AvailabilityEditorValidationErrors,
  externalErrors?: AvailabilityEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
    records: {
      ...(localErrors.records ?? {}),
      ...(externalErrors.records ?? {}),
    },
    rules: {
      ...(localErrors.rules ?? {}),
      ...(externalErrors.rules ?? {}),
    },
  } satisfies AvailabilityEditorValidationErrors;
}

const AVAILABILITY_TYPE_OPTIONS = [
  "available",
  "unavailable",
  "preferred",
  "time_off",
] as const;

const WEEKDAY_OPTIONS = [
  { labelKey: "teamStaff.availabilityEditor.days.monday", value: 1 },
  { labelKey: "teamStaff.availabilityEditor.days.tuesday", value: 2 },
  { labelKey: "teamStaff.availabilityEditor.days.wednesday", value: 3 },
  { labelKey: "teamStaff.availabilityEditor.days.thursday", value: 4 },
  { labelKey: "teamStaff.availabilityEditor.days.friday", value: 5 },
  { labelKey: "teamStaff.availabilityEditor.days.saturday", value: 6 },
  { labelKey: "teamStaff.availabilityEditor.days.sunday", value: 7 },
] as const;

export type AvailabilityEditorProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  initialValues?: Partial<AvailabilityEditorValues>;
  membershipId: string;
  onCancel?: () => void;
  onSubmit: (value: AvailabilityEditorValues) => void | Promise<void>;
  submitting?: boolean;
  timeZone: string;
  validationErrors?: AvailabilityEditorValidationErrors;
};

export function AvailabilityEditor({
  accessibilityLabel,
  disabled = false,
  initialValues,
  membershipId,
  onCancel,
  onSubmit,
  submitting = false,
  timeZone,
  validationErrors,
}: AvailabilityEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createAvailabilityEditorValues(membershipId, initialValues),
    [initialSignature, membershipId]
  );
  const [values, setValues] = useState<AvailabilityEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<AvailabilityEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);

  const handleSubmit = async () => {
    const normalized = normalizeAvailabilityEditorValues(values);
    const nextErrors = validateAvailabilityEditorValues(normalized, t);

    if (hasAvailabilityEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.availabilityEditor.accessibilityLabel")}
      cancelLabel={t("teamStaff.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={t("teamStaff.actions.saveChanges")}
      submitting={submitting}
      title={t("teamStaff.availabilityEditor.title")}
    >
      <View style={{ gap: theme.spacing[4] }}>
        <View style={{ gap: theme.spacing[3] }}>
          <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
            <Button
              label={t("teamStaff.availabilityEditor.actions.addRecord")}
              onPress={() =>
                setValues((current) => ({
                  ...current,
                  records: [
                    ...current.records,
                    createAvailabilityRecordValues({ timezone: timeZone }),
                  ],
                }))
              }
              size="sm"
              variant="secondary"
            />
            <Button
              label={t("teamStaff.availabilityEditor.actions.addRule")}
              onPress={() =>
                setValues((current) => ({
                  ...current,
                  rules: [
                    ...current.rules,
                    createAvailabilityRuleValues({ timezone: timeZone }),
                  ],
                }))
              }
              size="sm"
              variant="ghost"
            />
          </View>
          {values.records.map((record) => {
            const recordErrors = resolvedErrors.records?.[record.id ?? ""];

            return (
              <View
                key={record.id ?? "record"}
                style={{
                  borderColor: theme.colors.border.default,
                  borderRadius: theme.radius.md,
                  borderWidth: 1,
                  gap: theme.spacing[3],
                  padding: theme.spacing[3],
                }}
              >
                <DateTimeField
                  error={recordErrors?.startsAt}
                  label={t("teamStaff.availabilityEditor.fields.startsAt.label")}
                  onChange={(startsAt) =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.map((currentRecord) =>
                        currentRecord.id === record.id
                          ? { ...currentRecord, startsAt }
                          : currentRecord
                      ),
                    }))
                  }
                  timeZone={record.timezone ?? timeZone}
                  value={record.startsAt ?? null}
                />
                <DateTimeField
                  error={recordErrors?.endsAt}
                  label={t("teamStaff.availabilityEditor.fields.endsAt.label")}
                  onChange={(endsAt) =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.map((currentRecord) =>
                        currentRecord.id === record.id
                          ? { ...currentRecord, endsAt }
                          : currentRecord
                      ),
                    }))
                  }
                  timeZone={record.timezone ?? timeZone}
                  value={record.endsAt ?? null}
                />
                <Select
                  label={t("teamStaff.availabilityEditor.fields.type.label")}
                  onChange={(type) =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.map((currentRecord) =>
                        currentRecord.id === record.id
                          ? {
                              ...currentRecord,
                              available: type === "available" || type === "preferred",
                              type,
                            }
                          : currentRecord
                      ),
                    }))
                  }
                  options={AVAILABILITY_TYPE_OPTIONS.map((type) => ({
                    label: t(`teamStaff.availability.${type === "time_off" ? "away" : type}`),
                    value: type,
                  }))}
                  value={record.type ?? undefined}
                />
                <TextField
                  error={recordErrors?.timezone}
                  label={t("teamStaff.shiftEditor.fields.timezone.label")}
                  onChangeText={(timezone) =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.map((currentRecord) =>
                        currentRecord.id === record.id
                          ? { ...currentRecord, timezone }
                          : currentRecord
                      ),
                    }))
                  }
                  value={record.timezone ?? timeZone}
                />
                <TextArea
                  autoGrow
                  label={t("teamStaff.availabilityEditor.fields.notes.label")}
                  onChangeText={(notes) =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.map((currentRecord) =>
                        currentRecord.id === record.id
                          ? { ...currentRecord, notes }
                          : currentRecord
                      ),
                    }))
                  }
                  value={record.notes ?? ""}
                />
                <Button
                  label={t("teamStaff.availabilityEditor.actions.removeRecord")}
                  onPress={() =>
                    setValues((current) => ({
                      ...current,
                      records: current.records.filter((currentRecord) => currentRecord.id !== record.id),
                    }))
                  }
                  size="sm"
                  variant="ghost"
                />
              </View>
            );
          })}
          {values.rules.map((rule) => {
            const ruleErrors = resolvedErrors.rules?.[rule.id ?? ""];

            return (
              <View
                key={rule.id ?? "rule"}
                style={{
                  borderColor: theme.colors.border.default,
                  borderRadius: theme.radius.md,
                  borderWidth: 1,
                  gap: theme.spacing[3],
                  padding: theme.spacing[3],
                }}
              >
                <Select
                  error={ruleErrors?.dayOfWeek}
                  label={t("teamStaff.availabilityEditor.fields.dayOfWeek.label")}
                  onChange={(dayOfWeek) =>
                    setValues((current) => ({
                      ...current,
                      rules: current.rules.map((currentRule) =>
                        currentRule.id === rule.id
                          ? { ...currentRule, dayOfWeek: Number(dayOfWeek) }
                          : currentRule
                      ),
                    }))
                  }
                  options={WEEKDAY_OPTIONS.map((option) => ({
                    label: t(option.labelKey),
                    value: String(option.value),
                  }))}
                  value={String(rule.dayOfWeek)}
                />
                <TextField
                  error={ruleErrors?.startsAt}
                  label={t("teamStaff.availabilityEditor.fields.ruleStartsAt.label")}
                  onChangeText={(startsAt) =>
                    setValues((current) => ({
                      ...current,
                      rules: current.rules.map((currentRule) =>
                        currentRule.id === rule.id
                          ? { ...currentRule, startsAt }
                          : currentRule
                      ),
                    }))
                  }
                  placeholder="09:00"
                  value={rule.startsAt}
                />
                <TextField
                  error={ruleErrors?.endsAt}
                  label={t("teamStaff.availabilityEditor.fields.ruleEndsAt.label")}
                  onChangeText={(endsAt) =>
                    setValues((current) => ({
                      ...current,
                      rules: current.rules.map((currentRule) =>
                        currentRule.id === rule.id
                          ? { ...currentRule, endsAt }
                          : currentRule
                      ),
                    }))
                  }
                  placeholder="17:00"
                  value={rule.endsAt}
                />
                <TextField
                  error={ruleErrors?.timezone}
                  label={t("teamStaff.shiftEditor.fields.timezone.label")}
                  onChangeText={(timezone) =>
                    setValues((current) => ({
                      ...current,
                      rules: current.rules.map((currentRule) =>
                        currentRule.id === rule.id
                          ? { ...currentRule, timezone }
                          : currentRule
                      ),
                    }))
                  }
                  value={rule.timezone ?? timeZone}
                />
                <Button
                  label={t("teamStaff.availabilityEditor.actions.removeRule")}
                  onPress={() =>
                    setValues((current) => ({
                      ...current,
                      rules: current.rules.filter((currentRule) => currentRule.id !== rule.id),
                    }))
                  }
                  size="sm"
                  variant="ghost"
                />
              </View>
            );
          })}
        </View>
      </View>
    </FormCard>
  );
}
