import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { Checkbox } from "@/components/primitives/checkbox";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker, type EntityPickerOption } from "@/components/primitives/entity-picker";
import { NumberField } from "@/components/primitives/number-field";
import { Switch } from "@/components/primitives/switch";
import { TextArea } from "@/components/primitives/text-area";
import { UserPicker } from "@/components/primitives/user-picker";
import {
  normalizePrepGenerationOptions,
  type PrepAssignmentOption,
  type PrepEventReference,
  type PrepGenerationAvailableOptions,
  type PrepGenerationOptionsRecord,
  type PrepGenerationSource,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

const PREP_GENERATION_SOURCE_VALUES = [
  "manual",
  "ai",
  "regeneration",
  "import",
] as const satisfies readonly PrepGenerationSource[];

export type PrepGenerationOptionsErrors = Partial<
  Record<
    | "eventId"
    | "menuVersionId"
    | "beoVersionId"
    | "guestCount"
    | "dueAt"
    | "assignmentMembershipId"
    | "notes"
    | "form",
    string
  >
>;

export type PrepGenerationOptionsProps = {
  accessibilityLabel?: string;
  assignmentOptions?: PrepAssignmentOption[];
  availableOptions?: PrepGenerationAvailableOptions;
  beoOptions?: EntityPickerOption<string>[];
  compact?: boolean;
  disabled?: boolean;
  event?: PrepEventReference | null;
  eventOptions?: EntityPickerOption<string>[];
  menuOptions?: EntityPickerOption<string>[];
  onChange: (value: PrepGenerationOptionsRecord) => void;
  validationErrors?: PrepGenerationOptionsErrors;
  value: PrepGenerationOptionsRecord;
};

export function PrepGenerationOptions({
  accessibilityLabel,
  assignmentOptions,
  availableOptions,
  beoOptions,
  compact = false,
  disabled = false,
  event,
  eventOptions,
  menuOptions,
  onChange,
  validationErrors,
  value,
}: PrepGenerationOptionsProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [localValue, setLocalValue] = useState<PrepGenerationOptionsRecord>(value);
  const timeZone = event?.timezone ?? "UTC";

  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  const sourceOptions = useMemo(
    () =>
      PREP_GENERATION_SOURCE_VALUES.map((option) => ({
        label: t(`prep.generation.source.${option}`),
        value: option,
      })),
    [t]
  );

  const updateValue = (nextValue: PrepGenerationOptionsRecord) => {
    const normalized = normalizePrepGenerationOptions(nextValue);
    setLocalValue(normalized);
    onChange(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("prep.generation.options.accessibilityLabel")}
      error={validationErrors?.form}
      subtitle={t("prep.generation.options.subtitle")}
      title={t("prep.generation.options.title")}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        {eventOptions?.length ? (
          <EntityPicker
            disabled={disabled}
            entities={eventOptions}
            error={validationErrors?.eventId}
            label={t("prep.generation.labels.event")}
            onChange={(eventId) => updateValue({ ...localValue, eventId })}
            placeholder={t("prep.generation.placeholders.event")}
            value={localValue.eventId ?? undefined}
          />
        ) : null}
        {menuOptions?.length && availableOptions?.allowMenuVersion ? (
          <EntityPicker
            disabled={disabled}
            entities={menuOptions}
            error={validationErrors?.menuVersionId}
            label={t("prep.generation.labels.menu")}
            onChange={(menuVersionId) => updateValue({ ...localValue, menuVersionId })}
            placeholder={t("prep.generation.placeholders.menu")}
            value={localValue.menuVersionId ?? undefined}
          />
        ) : null}
        {beoOptions?.length && availableOptions?.allowBeoVersion ? (
          <EntityPicker
            disabled={disabled}
            entities={beoOptions}
            error={validationErrors?.beoVersionId}
            label={t("prep.generation.labels.beo")}
            onChange={(beoVersionId) => updateValue({ ...localValue, beoVersionId })}
            placeholder={t("prep.generation.placeholders.beo")}
            value={localValue.beoVersionId ?? undefined}
          />
        ) : null}
        {availableOptions?.allowGuestCount ? (
          <NumberField
            disabled={disabled}
            error={validationErrors?.guestCount}
            label={t("prep.generation.labels.guestCount")}
            min={0}
            onChange={(guestCount) => updateValue({ ...localValue, guestCount })}
            value={localValue.guestCount ?? 0}
          />
        ) : null}
        {availableOptions?.allowDueAt ? (
          <DateTimeField
            editable={!disabled}
            error={validationErrors?.dueAt}
            label={t("prep.generation.labels.dueAt")}
            onChange={(dueAt) => updateValue({ ...localValue, dueAt })}
            timeZone={timeZone}
            value={localValue.dueAt}
          />
        ) : null}
        {assignmentOptions?.length && availableOptions?.allowAssignment ? (
          <UserPicker
            disabled={disabled}
            error={validationErrors?.assignmentMembershipId}
            label={t("prep.generation.labels.assignment")}
            onChange={(assignmentMembershipId) =>
              updateValue({ ...localValue, assignmentMembershipId })
            }
            placeholder={t("prep.generation.placeholders.assignment")}
            users={assignmentOptions}
            value={localValue.assignmentMembershipId ?? undefined}
          />
        ) : null}
        {availableOptions?.allowSourceSelection ? (
          <EntityPicker
            disabled={disabled}
            entities={sourceOptions}
            label={t("prep.generation.labels.source")}
            onChange={(source) =>
              updateValue({ ...localValue, source: source as PrepGenerationSource })
            }
            placeholder={t("prep.generation.placeholders.source")}
            value={localValue.source ?? undefined}
          />
        ) : null}
        {availableOptions?.allowIncludeAssignments ? (
          <Checkbox
            checked={Boolean(localValue.includeAssignments)}
            description={t("prep.generation.descriptions.includeAssignments")}
            disabled={disabled}
            label={t("prep.generation.labels.includeAssignments")}
            onChange={(includeAssignments) => updateValue({ ...localValue, includeAssignments })}
          />
        ) : null}
        {availableOptions?.allowPreserveCompletedItems ? (
          <Switch
            description={t("prep.generation.descriptions.preserveCompletedItems")}
            disabled={disabled}
            label={t("prep.generation.labels.preserveCompletedItems")}
            onChange={(preserveCompletedItems) =>
              updateValue({ ...localValue, preserveCompletedItems })
            }
            value={Boolean(localValue.preserveCompletedItems)}
          />
        ) : null}
        {availableOptions?.allowPreserveAssignments ? (
          <Switch
            description={t("prep.generation.descriptions.preserveAssignments")}
            disabled={disabled}
            label={t("prep.generation.labels.preserveAssignments")}
            onChange={(preserveAssignments) =>
              updateValue({ ...localValue, preserveAssignments })
            }
            value={Boolean(localValue.preserveAssignments)}
          />
        ) : null}
        {availableOptions?.allowNotes ? (
          <TextArea
            autoGrow
            editable={!disabled}
            error={validationErrors?.notes}
            label={t("prep.generation.labels.notes")}
            onChangeText={(notes) => updateValue({ ...localValue, notes })}
            placeholder={t("prep.generation.placeholders.notes")}
            value={localValue.notes ?? ""}
          />
        ) : null}
      </View>
    </FormCard>
  );
}
