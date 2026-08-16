import type {
  TaskAssignmentRecord,
  TaskPriority,
  TaskRecord,
  TaskStatus,
  TaskSummaryRecord,
} from "@/features/tasks/types";
import type { SemanticStatusTone } from "@/theme/status-config";

export const TASK_STATUS_ORDER: TaskStatus[] = [
  "todo",
  "in_progress",
  "blocked",
  "done",
  "cancelled",
];

export const TASK_PRIORITY_CONFIG: Record<
  TaskPriority,
  { tone: SemanticStatusTone; translationKey: string }
> = {
  high: { tone: "warning", translationKey: "tasks.priority.high" },
  low: { tone: "neutral", translationKey: "tasks.priority.low" },
  normal: { tone: "info", translationKey: "tasks.priority.normal" },
  urgent: { tone: "danger", translationKey: "tasks.priority.urgent" },
};

export function getTaskStatus(task?: Pick<TaskRecord, "status"> | null) {
  return task?.status ?? null;
}

export function getTaskPriority(task?: Pick<TaskRecord, "priority"> | null) {
  return task?.priority ?? null;
}

export function getTaskPriorityMetadata(priority?: TaskPriority | null) {
  if (!priority) {
    return null;
  }

  return TASK_PRIORITY_CONFIG[priority] ?? null;
}

export function getTaskPrimaryAssignment(
  assignments?: TaskAssignmentRecord[] | null
) {
  if (!assignments?.length) {
    return null;
  }

  return assignments.find((assignment) => assignment.isPrimary) ?? assignments[0] ?? null;
}

export function getTaskAssignmentLabel(assignment?: TaskAssignmentRecord | null) {
  return assignment?.user?.name?.trim() || assignment?.roleLabel?.trim() || null;
}

export function getTaskAssignedUsers(task?: TaskRecord | null) {
  return (
    task?.assignments?.filter(
      (assignment): assignment is TaskAssignmentRecord =>
        Boolean(assignment?.user?.name?.trim())
    ) ?? []
  );
}

export function formatTaskDateTime(value?: string | null, locale?: string) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  } catch {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  }
}

export type TaskDueState =
  | "none"
  | "upcoming"
  | "today"
  | "tomorrow"
  | "overdue"
  | "completed";

function getDateParts(value: Date, timeZone?: string | null) {
  const formatter = new Intl.DateTimeFormat("en-CA", {
    day: "2-digit",
    month: "2-digit",
    timeZone: timeZone ?? undefined,
    year: "numeric",
  });

  const parts = formatter.formatToParts(value);

  return {
    day: Number(parts.find((part) => part.type === "day")?.value ?? "1"),
    month: Number(parts.find((part) => part.type === "month")?.value ?? "1"),
    year: Number(parts.find((part) => part.type === "year")?.value ?? "1970"),
  };
}

function getDayDifference(
  leftDate: Date,
  rightDate: Date,
  timeZone?: string | null
) {
  const left = getDateParts(leftDate, timeZone);
  const right = getDateParts(rightDate, timeZone);
  const leftUtc = Date.UTC(left.year, left.month - 1, left.day);
  const rightUtc = Date.UTC(right.year, right.month - 1, right.day);

  return Math.round((leftUtc - rightUtc) / 86400000);
}

export function isTaskOverdue(task?: TaskRecord | null, now = Date.now()) {
  if (!task?.dueAt || task.status === "done" || task.status === "cancelled") {
    return false;
  }

  const dueAt = new Date(task.dueAt).getTime();

  if (Number.isNaN(dueAt)) {
    return false;
  }

  return dueAt < now;
}

export function getTaskDueState(
  dueAt?: string | null,
  status?: TaskStatus | null,
  timeZone?: string | null,
  now = new Date()
): TaskDueState {
  if (!dueAt) {
    return "none";
  }

  if (status === "done") {
    return "completed";
  }

  const dueDate = new Date(dueAt);

  if (Number.isNaN(dueDate.getTime())) {
    return "upcoming";
  }

  if (status !== "cancelled" && dueDate.getTime() < now.getTime()) {
    const dayDifference = getDayDifference(dueDate, now, timeZone);

    if (dayDifference < 0) {
      return "overdue";
    }
  }

  const dayDifference = getDayDifference(dueDate, now, timeZone);

  if (dayDifference === 0) {
    return "today";
  }

  if (dayDifference === 1) {
    return "tomorrow";
  }

  return "upcoming";
}

export function getTaskContextLabel(task?: TaskRecord | null) {
  const labels = [
    task?.event?.name?.trim(),
    task?.station?.name?.trim(),
    task?.team?.name?.trim(),
  ].filter(Boolean);

  return labels.length ? labels.join(" - ") : null;
}

export function sortTasks(tasks: TaskRecord[]) {
  return [...tasks];
}

export function buildTaskSummary(tasks: TaskRecord[]): TaskSummaryRecord {
  const summary: TaskSummaryRecord = {
    assigned: 0,
    blocked: 0,
    cancelled: 0,
    done: 0,
    inProgress: 0,
    overdue: 0,
    todo: 0,
    total: tasks.length,
    unassigned: 0,
  };

  tasks.forEach((task) => {
    const status = task.status ?? "todo";
    const hasAssignments = Boolean(task.assignments?.length);

    if (status === "todo") {
      summary.todo = (summary.todo ?? 0) + 1;
    }

    if (status === "in_progress") {
      summary.inProgress = (summary.inProgress ?? 0) + 1;
    }

    if (status === "blocked") {
      summary.blocked = (summary.blocked ?? 0) + 1;
    }

    if (status === "done") {
      summary.done = (summary.done ?? 0) + 1;
    }

    if (status === "cancelled") {
      summary.cancelled = (summary.cancelled ?? 0) + 1;
    }

    if (isTaskOverdue(task)) {
      summary.overdue = (summary.overdue ?? 0) + 1;
    }

    if (hasAssignments) {
      summary.assigned = (summary.assigned ?? 0) + 1;
    } else {
      summary.unassigned = (summary.unassigned ?? 0) + 1;
    }
  });

  return summary;
}
