import type { EntityPickerOption } from "@/components/primitives/entity-picker";

import type { StationRecord } from "@/features/team-staff/types";

export type StationEditorMode = "create" | "edit";
export type StationStatusValue = "active" | "inactive";
export type StationTeamOption = EntityPickerOption<string>;

export type StationEditorValues = StationRecord;

export type StationEditorValidationErrors = Partial<
  Record<"name" | "description" | "teamId" | "status" | "form", string>
>;

export const STATION_STATUS_VALUES = [
  "active",
  "inactive",
] as const satisfies readonly StationStatusValue[];

let stationDraftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function createStationDraftId() {
  stationDraftCounter += 1;
  return `station-draft-${Date.now()}-${stationDraftCounter}`;
}

export function createStationEditorValues(
  values?: Partial<StationEditorValues>
): StationEditorValues {
  return {
    description: values?.description ?? null,
    id: values?.id ?? createStationDraftId(),
    key: values?.key ?? null,
    members: values?.members ?? null,
    name: values?.name ?? "",
    position:
      typeof values?.position === "number" && Number.isFinite(values.position)
        ? values.position
        : null,
    status: values?.status ?? "active",
    team: values?.team ?? null,
    teamId: values?.teamId ?? null,
    type: values?.type ?? null,
    workload: values?.workload ?? null,
  };
}

export function normalizeStationEditorValues(
  values: StationEditorValues
): StationEditorValues {
  return {
    ...values,
    description: trimOrNull(values.description),
    key: trimOrNull(values.key),
    name: values.name.trim(),
    status: trimOrNull(values.status) ?? "active",
    teamId: trimOrNull(values.teamId),
    type: trimOrNull(values.type),
  };
}

export function validateStationEditorValues(
  values: StationEditorValues,
  t: (key: string) => string
): StationEditorValidationErrors {
  const errors: StationEditorValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = t("teamStaff.stationEditor.errors.nameRequired");
  }

  return errors;
}

export function hasStationEditorErrors(
  errors?: StationEditorValidationErrors | null
) {
  if (!errors) {
    return false;
  }

  return Object.values(errors).some(Boolean);
}
