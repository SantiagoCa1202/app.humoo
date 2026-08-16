import type {
  PrepDisplayRecord,
  PrepEventReference,
  PrepItemAssignmentRecord,
  PrepItemConflictType,
  PrepItemRecord,
  PrepListProgressRecord,
  PrepListRecord,
  PrepListStatus,
  PrepListVersionRecord,
  PrepListVersionStatus,
  PrepTaskStatus,
  PrepUserReference,
} from "@/features/prep/types";
import type { ComparisonChange } from "@/components/patterns/comparison-card";

export type PrepStatusNamespace = "prepLists" | "prepListVersions" | "prepTasks";
export type PrepRenderableStatus = PrepListStatus | PrepListVersionStatus | PrepTaskStatus;

export function getPrepListStatus(prepList: PrepDisplayRecord): PrepListStatus | null {
  return prepList.status ?? null;
}

export function getPrepStatusNamespace(
  status: PrepRenderableStatus,
  namespace?: PrepStatusNamespace
): PrepStatusNamespace {
  if (namespace) {
    return namespace;
  }

  if (
    status === "todo" ||
    status === "blocked" ||
    status === "done" ||
    status === "skipped"
  ) {
    return "prepTasks";
  }

  if (status === "review" || status === "approved" || status === "superseded") {
    return "prepListVersions";
  }

  if (status === "active" || status === "in_progress" || status === "completed") {
    return "prepLists";
  }

  return "prepLists";
}

export function getPrepListTitle(prepList: PrepDisplayRecord) {
  return prepList.name.trim();
}

export function getPrepEventName(event?: PrepEventReference | null) {
  return event?.name?.trim() ?? null;
}

export function getPrepProgress(
  prepList?: PrepListRecord | null,
  progress?: PrepListProgressRecord | null
): PrepListProgressRecord {
  return {
    assignedStaff: progress?.assignedStaff ?? null,
    assignedStaffCount: progress?.assignedStaffCount ?? null,
    blocked: progress?.blocked ?? prepList?.blockedItems ?? null,
    completed: progress?.completed ?? prepList?.completedItems ?? null,
    dueAt: progress?.dueAt ?? prepList?.productionEndsAt ?? null,
    inProgress: progress?.inProgress ?? null,
    percentage: progress?.percentage ?? null,
    remaining: progress?.remaining ?? null,
    skipped: progress?.skipped ?? null,
    total: progress?.total ?? prepList?.totalItems ?? null,
    unassigned: progress?.unassigned ?? null,
  };
}

export function getPrepRemainingCount(progress?: PrepListProgressRecord | null) {
  if (typeof progress?.remaining === "number") {
    return progress.remaining;
  }

  if (typeof progress?.total === "number" && typeof progress?.completed === "number") {
    return Math.max(progress.total - progress.completed, 0);
  }

  return null;
}

export function calculatePrepProgressPercentage(progress?: PrepListProgressRecord | null) {
  if (typeof progress?.percentage === "number") {
    return Math.max(0, Math.min(100, progress.percentage));
  }

  if (
    typeof progress?.completed === "number" &&
    typeof progress?.total === "number" &&
    progress.total > 0
  ) {
    return Math.max(0, Math.min(100, (progress.completed / progress.total) * 100));
  }

  return 0;
}

export function formatPrepDateTime(value?: string | null, locale?: string) {
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

export function formatPrepDateRange(
  startsAt?: string | null,
  endsAt?: string | null,
  locale?: string
) {
  const startLabel = formatPrepDateTime(startsAt, locale);
  const endLabel = formatPrepDateTime(endsAt, locale);

  if (startLabel && endLabel) {
    return `${startLabel} - ${endLabel}`;
  }

  return startLabel ?? endLabel;
}

export function getPrepAssignedStaff(progress?: PrepListProgressRecord | null) {
  return progress?.assignedStaff?.filter((user): user is PrepUserReference => Boolean(user?.name?.trim())) ?? [];
}

export function getPrepVersionLabel(
  currentVersion?: PrepListVersionRecord | null,
  t?: (key: string, options?: Record<string, unknown>) => string
) {
  if (!currentVersion) {
    return null;
  }

  return t ? t("prep.version.label", { value: currentVersion.version }) : String(currentVersion.version);
}

export function getPrepVersionStatus(
  currentVersion?: PrepListVersionRecord | null
): PrepListVersionStatus | null {
  return currentVersion?.status ?? null;
}

function getPrepUnitLabel(unit?: PrepItemRecord["unit"] | null) {
  return unit?.symbol?.trim() || unit?.name?.trim() || unit?.key?.trim() || null;
}

export function getPrepPrimaryAssignment(
  assignments?: PrepItemAssignmentRecord[] | null
) {
  if (!assignments?.length) {
    return null;
  }

  return assignments.find((assignment) => assignment.isPrimary) ?? assignments[0] ?? null;
}

export function formatPrepQuantity(
  quantity?: number | null,
  unit?: PrepItemRecord["unit"] | null,
  locale?: string
) {
  if (quantity === null || quantity === undefined) {
    return getPrepUnitLabel(unit);
  }

  const formatted = new Intl.NumberFormat(locale, {
    maximumFractionDigits: 2,
  }).format(quantity);
  const unitLabel = getPrepUnitLabel(unit);

  return unitLabel ? `${formatted} ${unitLabel}` : formatted;
}

export function getPrepAssignmentLabel(assignment?: PrepItemAssignmentRecord | null) {
  return assignment?.user?.name?.trim() ?? null;
}

export function buildPrepItemConflictChanges(
  localItem?: PrepItemRecord | null,
  remoteItem?: PrepItemRecord | null,
  t?: (key: string, options?: Record<string, unknown>) => string,
  locale?: string
): ComparisonChange[] {
  if (!localItem || !remoteItem || !t) {
    return [];
  }

  const changes: ComparisonChange[] = [];
  const pushChange = (id: string, label: string, before?: string | null, after?: string | null) => {
    if ((before ?? null) === (after ?? null)) {
      return;
    }

    changes.push({
      after: after ?? t("prep.conflict.emptyValue"),
      before: before ?? t("prep.conflict.emptyValue"),
      id,
      label,
    });
  };

  pushChange("title", t("prep.form.fields.title.label"), localItem.title, remoteItem.title);
  pushChange(
    "status",
    t("prep.form.fields.status.label"),
    localItem.status ? t(`status.${localItem.status}`) : null,
    remoteItem.status ? t(`status.${remoteItem.status}`) : null
  );
  pushChange(
    "quantity",
    t("prep.labels.quantity"),
    formatPrepQuantity(localItem.quantity, localItem.unit, locale),
    formatPrepQuantity(remoteItem.quantity, remoteItem.unit, locale)
  );
  pushChange(
    "assigned",
    t("prep.labels.assignedTo"),
    getPrepAssignmentLabel(getPrepPrimaryAssignment(localItem.assignments)),
    getPrepAssignmentLabel(getPrepPrimaryAssignment(remoteItem.assignments))
  );
  pushChange(
    "due",
    t("prep.labels.due"),
    formatPrepDateTime(localItem.dueAt, locale),
    formatPrepDateTime(remoteItem.dueAt, locale)
  );
  pushChange(
    "notes",
    t("prep.form.fields.notes.label"),
    localItem.notes?.trim() ?? null,
    remoteItem.notes?.trim() ?? null
  );

  return changes;
}

export function getPrepItemConflictDescriptionKey(conflictType?: PrepItemConflictType) {
  if (conflictType === "remote_update") {
    return "prep.conflict.types.remote_update";
  }

  if (conflictType === "stale_data") {
    return "prep.conflict.types.stale_data";
  }

  if (conflictType === "status_changed") {
    return "prep.conflict.types.status_changed";
  }

  if (conflictType === "assignment_changed") {
    return "prep.conflict.types.assignment_changed";
  }

  if (conflictType === "quantity_changed") {
    return "prep.conflict.types.quantity_changed";
  }

  return "prep.conflict.types.version_conflict";
}
