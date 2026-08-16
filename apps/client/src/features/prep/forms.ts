import type { QuantityUnitOption } from "@/components/primitives/quantity-input";
import type { UserPickerOption } from "@/components/primitives/user-picker";
import type { PrepItemRecord, PrepTaskStatus } from "@/features/prep/types";

export type PrepItemValidationErrors = Partial<
  Record<
    | "title"
    | "description"
    | "quantity"
    | "unitId"
    | "startsAt"
    | "dueAt"
    | "status"
    | "blockedReason"
    | "notes"
    | "assignments"
    | "form",
    string
  >
>;

export type PrepItemEditorValues = PrepItemRecord;

export const PREP_ITEM_STATUS_VALUES = [
  "todo",
  "in_progress",
  "blocked",
  "done",
  "skipped",
] as const satisfies readonly PrepTaskStatus[];

let draftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function normalizeNumber(value?: number | null) {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return null;
  }

  return value;
}

export function createPrepDraftKey(prefix: "item" = "item") {
  draftCounter += 1;
  return `${prefix}-draft-${Date.now()}-${draftCounter}`;
}

export function getPrepItemKey(item: Pick<PrepItemRecord, "clientId" | "id" | "title">) {
  return item.id ?? item.clientId ?? item.title ?? createPrepDraftKey("item");
}

export function sortPrepItems(items: PrepItemRecord[]) {
  return [...items].sort((left, right) => {
    const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
    const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition;
    }

    return left.title.localeCompare(right.title);
  });
}

export function createPrepItemValues(values?: Partial<PrepItemEditorValues>): PrepItemEditorValues {
  return {
    actualQuantity: values?.actualQuantity ?? null,
    actualUnit: values?.actualUnit ?? null,
    actualUnitId: values?.actualUnitId ?? null,
    assignments: values?.assignments ?? [],
    blockedReason: values?.blockedReason ?? null,
    clientId: values?.clientId ?? createPrepDraftKey("item"),
    completedAt: values?.completedAt ?? null,
    completedBy: values?.completedBy ?? null,
    createdAt: values?.createdAt ?? null,
    description: values?.description ?? null,
    dueAt: values?.dueAt ?? null,
    generated: values?.generated ?? null,
    id: values?.id ?? null,
    metadata: values?.metadata ?? null,
    notes: values?.notes ?? null,
    portions: values?.portions ?? null,
    position: values?.position ?? null,
    prepSectionId: values?.prepSectionId ?? null,
    priority: values?.priority ?? "normal",
    quantity: values?.quantity ?? null,
    recipeId: values?.recipeId ?? null,
    recipeName: values?.recipeName ?? null,
    recipeVersionId: values?.recipeVersionId ?? null,
    requiresConfirmation: values?.requiresConfirmation ?? null,
    scaleFactor: values?.scaleFactor ?? null,
    source: values?.source ?? null,
    startedAt: values?.startedAt ?? null,
    startsAt: values?.startsAt ?? null,
    status: values?.status ?? "todo",
    title: values?.title ?? "",
    unit: values?.unit ?? null,
    unitId: values?.unitId ?? null,
    updatedAt: values?.updatedAt ?? null,
    updatedBy: values?.updatedBy ?? null,
    version: values?.version ?? 1,
    yieldQuantity: values?.yieldQuantity ?? null,
    yieldUnit: values?.yieldUnit ?? null,
    yieldUnitId: values?.yieldUnitId ?? null,
  };
}

export function normalizePrepItemValues(values: PrepItemEditorValues): PrepItemEditorValues {
  return {
    ...values,
    assignments:
      values.assignments?.map((assignment) => ({
        ...assignment,
        membershipId: trimOrNull(assignment.membershipId),
        notes: trimOrNull(assignment.notes),
      })) ?? [],
    blockedReason: trimOrNull(values.blockedReason),
    description: trimOrNull(values.description),
    dueAt: trimOrNull(values.dueAt),
    notes: trimOrNull(values.notes),
    quantity: normalizeNumber(values.quantity),
    startsAt: trimOrNull(values.startsAt),
    title: values.title.trim(),
    unitId: trimOrNull(values.unitId),
    version:
      typeof values.version === "number" && Number.isFinite(values.version)
        ? Math.max(1, Math.trunc(values.version))
        : 1,
  };
}

export function validatePrepItemValues(
  values: PrepItemEditorValues,
  t: (key: string) => string
): PrepItemValidationErrors {
  const errors: PrepItemValidationErrors = {};

  if (!values.title.trim()) {
    errors.title = t("prep.form.errors.titleRequired");
  }

  if (
    values.quantity !== null &&
    values.quantity !== undefined &&
    (!Number.isFinite(values.quantity) || values.quantity < 0)
  ) {
    errors.quantity = t("prep.form.errors.quantityNonNegative");
  }

  if ((values.quantity ?? null) !== null && !values.unitId?.trim()) {
    errors.unitId = t("prep.form.errors.unitRequired");
  }

  if (values.status === "blocked" && !values.blockedReason?.trim()) {
    errors.blockedReason = t("prep.form.errors.blockedReasonRequired");
  }

  if (values.startsAt && values.dueAt) {
    const startsAt = new Date(values.startsAt).getTime();
    const dueAt = new Date(values.dueAt).getTime();

    if (!Number.isNaN(startsAt) && !Number.isNaN(dueAt) && dueAt < startsAt) {
      errors.dueAt = t("prep.form.errors.dueAfterStart");
    }
  }

  return errors;
}

export function hasPrepItemErrors(errors?: PrepItemValidationErrors | null) {
  if (!errors) {
    return false;
  }

  return Object.values(errors).some(Boolean);
}

export type PrepAssignmentOption = UserPickerOption<string>;
export type PrepUnitOption = QuantityUnitOption<string>;
