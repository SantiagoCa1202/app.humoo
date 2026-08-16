import type { TFunction } from "i18next";

import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import type { UserPickerOption } from "@/components/primitives/user-picker";
import type {
  TaskAssignmentRecord,
  TaskPriority,
  TaskRecord,
  TaskStatus,
} from "@/features/tasks/types";

export const TASK_STATUS_VALUES = [
  "todo",
  "in_progress",
  "blocked",
  "done",
  "cancelled",
] as const satisfies readonly TaskStatus[];

export const TASK_PRIORITY_VALUES = [
  "low",
  "normal",
  "high",
  "urgent",
] as const satisfies readonly TaskPriority[];

export const TASK_STATUS_ACTION_VALUES = [
  "start",
  "complete",
  "block",
  "skip",
  "reopen",
] as const;

export type TaskEditorMode = "create" | "edit";
export type TaskStatusActionId = (typeof TASK_STATUS_ACTION_VALUES)[number];

export type TaskStatusAction = {
  disabled?: boolean;
  icon?: React.ReactNode;
  id: TaskStatusActionId | (string & {});
  label?: string;
  translationKey?: string;
};

export type TaskEditorValues = TaskRecord;

export type TaskEditorValidationErrors = Partial<
  Record<
    | "title"
    | "description"
    | "status"
    | "priority"
    | "startsAt"
    | "dueAt"
    | "eventId"
    | "teamId"
    | "stationId"
    | "assignments"
    | "blockedReason"
    | "form",
    string
  >
>;

export type TaskFilters = {
  assigneeIds?: string[];
  dueFrom?: string | null;
  dueTo?: string | null;
  eventId?: string | null;
  overdue?: boolean;
  priorities?: TaskPriority[];
  search?: string;
  stationId?: string | null;
  statuses?: TaskStatus[];
  teamId?: string | null;
  unassigned?: boolean;
};

export type TaskAssignmentOption = UserPickerOption<string>;
export type TaskEntityOption = EntityPickerOption<string>;

let taskDraftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function createTaskDraftId(prefix = "task") {
  taskDraftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${taskDraftCounter}`;
}

function normalizeAssignments(assignments?: TaskAssignmentRecord[] | null) {
  return (
    assignments?.map((assignment) => ({
      ...assignment,
      membershipId: trimOrNull(assignment.membershipId),
      roleLabel: trimOrNull(assignment.roleLabel),
      user: assignment.user
        ? {
            ...assignment.user,
            name: trimOrNull(assignment.user.name),
          }
        : null,
    })) ?? []
  ).filter((assignment) => assignment.membershipId);
}

export function createTaskEditorValues(
  values?: Partial<TaskEditorValues>
): TaskEditorValues {
  return {
    assignments: values?.assignments ?? [],
    blockedReason: values?.blockedReason ?? null,
    completedAt: values?.completedAt ?? null,
    completedBy: values?.completedBy ?? null,
    createdAt: values?.createdAt ?? null,
    createdBy: values?.createdBy ?? null,
    description: values?.description ?? null,
    dueAt: values?.dueAt ?? null,
    event: values?.event ?? null,
    eventId: values?.eventId ?? null,
    id: values?.id ?? createTaskDraftId(),
    metadata: values?.metadata ?? null,
    priority: values?.priority ?? "normal",
    source: values?.source ?? "user",
    sourceId: values?.sourceId ?? null,
    sourceType: values?.sourceType ?? null,
    startsAt: values?.startsAt ?? null,
    station: values?.station ?? null,
    stationId: values?.stationId ?? null,
    status: values?.status ?? "todo",
    team: values?.team ?? null,
    teamId: values?.teamId ?? null,
    title: values?.title ?? "",
    type: values?.type ?? "general",
    updatedAt: values?.updatedAt ?? null,
    updatedBy: values?.updatedBy ?? null,
    version:
      typeof values?.version === "number" && Number.isFinite(values.version)
        ? Math.max(1, Math.trunc(values.version))
        : 1,
  };
}

export function normalizeTaskEditorValues(
  values: TaskEditorValues
): TaskEditorValues {
  return {
    ...values,
    assignments: normalizeAssignments(values.assignments),
    blockedReason: trimOrNull(values.blockedReason),
    description: trimOrNull(values.description),
    dueAt: trimOrNull(values.dueAt),
    eventId: trimOrNull(values.eventId),
    source: trimOrNull(values.source) ?? "user",
    sourceId: trimOrNull(values.sourceId),
    sourceType: trimOrNull(values.sourceType),
    startsAt: trimOrNull(values.startsAt),
    stationId: trimOrNull(values.stationId),
    teamId: trimOrNull(values.teamId),
    title: values.title.trim(),
    type: trimOrNull(values.type) ?? "general",
    version:
      typeof values.version === "number" && Number.isFinite(values.version)
        ? Math.max(1, Math.trunc(values.version))
        : 1,
  };
}

export function validateTaskEditorValues(
  values: TaskEditorValues,
  t: TFunction<"common">
): TaskEditorValidationErrors {
  const errors: TaskEditorValidationErrors = {};

  if (!values.title.trim()) {
    errors.title = t("tasks.form.errors.titleRequired");
  }

  if (values.startsAt && values.dueAt) {
    const startsAt = new Date(values.startsAt).getTime();
    const dueAt = new Date(values.dueAt).getTime();

    if (!Number.isNaN(startsAt) && !Number.isNaN(dueAt) && dueAt < startsAt) {
      errors.dueAt = t("tasks.form.errors.dueAfterStart");
    }
  }

  if (values.status === "blocked" && !values.blockedReason?.trim()) {
    errors.blockedReason = t("tasks.form.errors.blockedReasonRequired");
  }

  return errors;
}

export function hasTaskEditorErrors(errors?: TaskEditorValidationErrors | null) {
  if (!errors) {
    return false;
  }

  return Object.values(errors).some(Boolean);
}

export function createEmptyTaskFilters(): Required<TaskFilters> {
  return {
    assigneeIds: [],
    dueFrom: null,
    dueTo: null,
    eventId: null,
    overdue: false,
    priorities: [],
    search: "",
    stationId: null,
    statuses: [],
    teamId: null,
    unassigned: false,
  };
}

export function normalizeTaskFilters(filters?: TaskFilters | null): Required<TaskFilters> {
  const values = {
    ...createEmptyTaskFilters(),
    ...filters,
  };

  return {
    assigneeIds: values.assigneeIds?.map((value) => value.trim()).filter(Boolean) ?? [],
    dueFrom: trimOrNull(values.dueFrom),
    dueTo: trimOrNull(values.dueTo),
    eventId: trimOrNull(values.eventId),
    overdue: Boolean(values.overdue),
    priorities: values.priorities?.filter(Boolean) ?? [],
    search: values.search?.trim() ?? "",
    stationId: trimOrNull(values.stationId),
    statuses: values.statuses?.filter(Boolean) ?? [],
    teamId: trimOrNull(values.teamId),
    unassigned: Boolean(values.unassigned),
  };
}

export function resolveTaskActionLabel(
  action: TaskStatusAction,
  t: TFunction<"common">
) {
  if (action.translationKey) {
    return t(action.translationKey);
  }

  if (action.label) {
    return action.label;
  }

  return t(`tasks.actions.${action.id}`);
}
