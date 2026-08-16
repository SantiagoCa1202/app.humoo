import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { FormCard } from "@/components/patterns/form-card";
import { TaskAssignment } from "@/components/patterns/task-assignment";
import { TaskStatusActions } from "@/components/patterns/task-status-actions";
import { TaskStatusBadge } from "@/components/patterns/task-status-badge";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { Select } from "@/components/primitives/select";
import { StatusSelect } from "@/components/primitives/status-select";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import {
  createTaskEditorValues,
  getTaskPriorityMetadata,
  getTaskPrimaryAssignment,
  hasTaskEditorErrors,
  normalizeTaskEditorValues,
  TASK_PRIORITY_VALUES,
  TASK_STATUS_VALUES,
  type TaskAssignmentOption,
  type TaskEditorMode,
  type TaskEditorValidationErrors,
  type TaskEditorValues,
  type TaskEntityOption,
  type TaskRecord,
  type TaskStatusAction,
  validateTaskEditorValues,
} from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

function mergeValidationErrors(
  localErrors: TaskEditorValidationErrors,
  externalErrors?: TaskEditorValidationErrors
) {
  if (!externalErrors) {
    return localErrors;
  }

  return {
    ...localErrors,
    ...externalErrors,
  } satisfies TaskEditorValidationErrors;
}

function mapMembershipIdsToAssignments(
  membershipIds: string[],
  candidates?: TaskAssignmentOption[]
) {
  return membershipIds.map((membershipId, index) => {
    const candidate = candidates?.find((option) => option.value === membershipId);

    return {
      id: null,
      isPrimary: index === 0,
      membershipId,
      roleLabel: candidate?.roleLabel ?? null,
      status: "assigned" as const,
      user: candidate
        ? {
            name: candidate.label ?? candidate.name ?? null,
            source: candidate.avatarSource,
          }
        : null,
    };
  });
}

export type TaskEditorFormProps = {
  accessibilityLabel?: string;
  assigneeOptions?: TaskAssignmentOption[];
  availablePriorities?: readonly string[];
  availableStatusActions?: TaskStatusAction[];
  availableStatuses?: readonly string[];
  compact?: boolean;
  disabled?: boolean;
  eventOptions?: TaskEntityOption[];
  initialValues?: Partial<TaskEditorValues>;
  mode?: TaskEditorMode;
  multipleAssignees?: boolean;
  onCancel?: () => void;
  onStatusAction?: (action: TaskStatusAction, values: TaskEditorValues) => void | Promise<void>;
  onSubmit: (value: TaskEditorValues) => void | Promise<void>;
  stationOptions?: TaskEntityOption[];
  statusMode?: "actions" | "select";
  submitting?: boolean;
  teamOptions?: TaskEntityOption[];
  timeZone?: string;
  validationErrors?: TaskEditorValidationErrors;
};

export function TaskEditorForm({
  accessibilityLabel,
  assigneeOptions,
  availablePriorities,
  availableStatusActions,
  availableStatuses,
  compact = false,
  disabled = false,
  eventOptions,
  initialValues,
  mode = "create",
  multipleAssignees = false,
  onCancel,
  onStatusAction,
  onSubmit,
  stationOptions,
  statusMode = "select",
  submitting = false,
  teamOptions,
  timeZone,
  validationErrors,
}: TaskEditorFormProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const initialSignature = JSON.stringify(initialValues ?? {});
  const defaultValues = useMemo(
    () => createTaskEditorValues(initialValues),
    [initialSignature]
  );
  const [values, setValues] = useState<TaskEditorValues>(defaultValues);
  const [localErrors, setLocalErrors] = useState<TaskEditorValidationErrors>({});

  useEffect(() => {
    setValues(defaultValues);
    setLocalErrors({});
  }, [defaultValues]);

  const resolvedErrors = mergeValidationErrors(localErrors, validationErrors);
  const resolvedTimeZone =
    timeZone ??
    values.event?.timezone ??
    Intl.DateTimeFormat().resolvedOptions().timeZone ??
    "UTC";
  const submitLabel =
    mode === "edit" ? t("tasks.actions.saveChanges") : t("tasks.actions.create");
  const title =
    mode === "edit" ? t("tasks.form.editTitle") : t("tasks.form.createTitle");
  const priorityValues = (availablePriorities as readonly string[] | undefined) ?? TASK_PRIORITY_VALUES;
  const statusValues = (availableStatuses as readonly string[] | undefined) ?? TASK_STATUS_VALUES;
  const primaryAssignment = getTaskPrimaryAssignment(values.assignments);

  const handleSubmit = async () => {
    const normalized = normalizeTaskEditorValues(values);
    const nextErrors = validateTaskEditorValues(normalized, t);

    if (hasTaskEditorErrors(nextErrors)) {
      setLocalErrors(nextErrors);
      return;
    }

    setLocalErrors({});
    await onSubmit(normalized);
  };

  return (
    <FormCard
      accessibilityLabel={accessibilityLabel ?? t("tasks.form.accessibilityLabel")}
      cancelLabel={t("tasks.actions.cancel")}
      error={resolvedErrors.form}
      onCancel={onCancel}
      onSubmit={handleSubmit}
      submitLabel={submitLabel}
      submitting={submitting}
      subtitle={
        values.status ? <TaskStatusBadge size="sm" status={values.status} /> : undefined
      }
      title={title}
      variant="default"
    >
      <View style={{ gap: theme.spacing[4] }}>
        <TextField
          accessibilityLabel={t("tasks.form.fields.title.accessibilityLabel")}
          editable={!disabled}
          error={resolvedErrors.title}
          label={t("tasks.form.fields.title.label")}
          onChangeText={(titleValue) =>
            setValues((currentValues) => ({ ...currentValues, title: titleValue }))
          }
          placeholder={t("tasks.form.fields.title.placeholder")}
          required
          value={values.title}
        />
        <TextArea
          accessibilityLabel={t("tasks.form.fields.description.accessibilityLabel")}
          autoGrow
          editable={!disabled}
          error={resolvedErrors.description}
          label={t("tasks.form.fields.description.label")}
          onChangeText={(description) =>
            setValues((currentValues) => ({ ...currentValues, description }))
          }
          placeholder={t("tasks.form.fields.description.placeholder")}
          value={values.description ?? ""}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <Select
              accessibilityLabel={t("tasks.form.fields.priority.accessibilityLabel")}
              disabled={disabled}
              error={resolvedErrors.priority}
              label={t("tasks.form.fields.priority.label")}
              onChange={(priority) =>
                setValues((currentValues) => ({
                  ...currentValues,
                  priority: priority as TaskEditorValues["priority"],
                }))
              }
              options={priorityValues.map((priority) => ({
                label: t(
                  getTaskPriorityMetadata(priority as TaskEditorValues["priority"])?.translationKey ??
                    `tasks.priority.${priority}`
                ),
                value: priority,
              }))}
              placeholder={t("tasks.form.fields.priority.placeholder")}
              value={values.priority ?? undefined}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            {statusMode === "actions" && availableStatusActions?.length ? (
              <View style={{ gap: theme.spacing[2] }}>
                <TaskStatusActions
                  accessibilityLabel={t("tasks.statusActions.accessibilityLabel")}
                  availableActions={availableStatusActions}
                  compact={compact}
                  disabled={disabled || submitting}
                  onAction={(action) => onStatusAction?.(action, values)}
                />
                <TextField
                  editable={false}
                  label={t("tasks.form.fields.status.label")}
                  value={values.status ? t(`tasks.status.${values.status}`) : ""}
                />
              </View>
            ) : (
              <StatusSelect
                accessibilityLabel={t("tasks.form.fields.status.accessibilityLabel")}
                disabled={disabled}
                error={resolvedErrors.status}
                label={t("tasks.form.fields.status.label")}
                namespace="tasks"
                onChange={(status) =>
                  setValues((currentValues) => ({
                    ...currentValues,
                    status: status as TaskEditorValues["status"],
                  }))
                }
                options={statusValues.map((status) => ({
                  namespace: "tasks",
                  value: status as TaskRecord["status"] & string,
                }))}
                value={values.status ?? undefined}
              />
            )}
          </View>
        </View>
        <TaskAssignment
          accessibilityLabel={t("tasks.assignment.accessibilityLabel")}
          assignments={values.assignments}
          candidates={assigneeOptions}
          compact={compact}
          disabled={disabled}
          editable={Boolean(assigneeOptions?.length)}
          multiple={multipleAssignees}
          onChange={(membershipIds) =>
            setValues((currentValues) => ({
              ...currentValues,
              assignments: mapMembershipIdsToAssignments(membershipIds, assigneeOptions),
            }))
          }
          onClear={() => setValues((currentValues) => ({ ...currentValues, assignments: [] }))}
        />
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          {!eventOptions?.length ? null : (
            <View style={{ flex: 1, minWidth: 220 }}>
              <EntityPicker
                accessibilityLabel={t("tasks.form.fields.event.accessibilityLabel")}
                disabled={disabled}
                entities={eventOptions}
                error={resolvedErrors.eventId}
                label={t("tasks.form.fields.event.label")}
                onChange={(eventId) => {
                  const selectedEvent = eventOptions.find((option) => option.value === eventId);
                  setValues((currentValues) => ({
                    ...currentValues,
                    event: selectedEvent
                      ? {
                          id: eventId,
                          name: selectedEvent.label ?? selectedEvent.name ?? null,
                        }
                      : null,
                    eventId,
                  }));
                }}
                placeholder={t("tasks.form.fields.event.placeholder")}
                value={values.eventId ?? undefined}
              />
            </View>
          )}
          {!teamOptions?.length ? null : (
            <View style={{ flex: 1, minWidth: 220 }}>
              <EntityPicker
                accessibilityLabel={t("tasks.form.fields.team.accessibilityLabel")}
                disabled={disabled}
                entities={teamOptions}
                error={resolvedErrors.teamId}
                label={t("tasks.form.fields.team.label")}
                onChange={(teamId) => {
                  const selectedTeam = teamOptions.find((option) => option.value === teamId);
                  setValues((currentValues) => ({
                    ...currentValues,
                    team: selectedTeam
                      ? {
                          id: teamId,
                          name: selectedTeam.label ?? selectedTeam.name ?? null,
                        }
                      : null,
                    teamId,
                  }));
                }}
                placeholder={t("tasks.form.fields.team.placeholder")}
                value={values.teamId ?? undefined}
              />
            </View>
          )}
          {!stationOptions?.length ? null : (
            <View style={{ flex: 1, minWidth: 220 }}>
              <EntityPicker
                accessibilityLabel={t("tasks.form.fields.station.accessibilityLabel")}
                disabled={disabled}
                entities={stationOptions}
                error={resolvedErrors.stationId}
                label={t("tasks.form.fields.station.label")}
                onChange={(stationId) => {
                  const selectedStation = stationOptions.find(
                    (option) => option.value === stationId
                  );
                  setValues((currentValues) => ({
                    ...currentValues,
                    station: selectedStation
                      ? {
                          id: stationId,
                          name: selectedStation.label ?? selectedStation.name ?? null,
                        }
                      : null,
                    stationId,
                  }));
                }}
                placeholder={t("tasks.form.fields.station.placeholder")}
                value={values.stationId ?? undefined}
              />
            </View>
          )}
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
          <View style={{ flex: 1, minWidth: 220 }}>
            <DateTimeField
              accessibilityLabel={t("tasks.form.fields.startsAt.accessibilityLabel")}
              editable={!disabled}
              error={resolvedErrors.startsAt}
              label={t("tasks.form.fields.startsAt.label")}
              onChange={(startsAt) =>
                setValues((currentValues) => ({ ...currentValues, startsAt }))
              }
              timeZone={resolvedTimeZone}
              value={values.startsAt}
            />
          </View>
          <View style={{ flex: 1, minWidth: 220 }}>
            <DateTimeField
              accessibilityLabel={t("tasks.form.fields.dueAt.accessibilityLabel")}
              editable={!disabled}
              error={resolvedErrors.dueAt}
              label={t("tasks.form.fields.dueAt.label")}
              onChange={(dueAt) =>
                setValues((currentValues) => ({ ...currentValues, dueAt }))
              }
              timeZone={resolvedTimeZone}
              value={values.dueAt}
            />
          </View>
        </View>
        {values.status === "blocked" || primaryAssignment?.status === "declined" ? (
          <TextField
            accessibilityLabel={t("tasks.form.fields.blockedReason.accessibilityLabel")}
            editable={!disabled}
            error={resolvedErrors.blockedReason}
            label={t("tasks.form.fields.blockedReason.label")}
            onChangeText={(blockedReason) =>
              setValues((currentValues) => ({
                ...currentValues,
                blockedReason,
              }))
            }
            placeholder={t("tasks.form.fields.blockedReason.placeholder")}
            value={values.blockedReason ?? ""}
          />
        ) : null}
      </View>
    </FormCard>
  );
}
