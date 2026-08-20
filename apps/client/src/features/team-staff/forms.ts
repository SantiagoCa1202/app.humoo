import type { EntityPickerOption } from "@/components/primitives/entity-picker";

import type {
  AvailabilityRuleRecord,
  MemberAvailabilityRecord,
  MemberShiftRecord,
  StationRecord,
  TeamRecord,
} from "@/features/team-staff/types";
import type { ShiftStatus } from "@/theme/status-config";

export type StationEditorMode = "create" | "edit";
export type StationStatusValue = "active" | "inactive";
export type StationTeamOption = EntityPickerOption<string>;
export type TeamEditorMode = "create" | "edit";
export type TeamStatusValue = "active" | "inactive";
export type TeamMemberOption = EntityPickerOption<string>;
export type ShiftEditorMode = "create" | "edit";
export type ShiftMemberOption = EntityPickerOption<string>;
export type ShiftTeamOption = EntityPickerOption<string>;
export type ShiftStationOption = EntityPickerOption<string>;
export type ShiftEventOption = EntityPickerOption<string>;
export type ShiftStatusValue = ShiftStatus;

export type StationEditorValues = StationRecord;
export type TeamEditorValues = Pick<
  TeamRecord,
  "description" | "id" | "key" | "leadMembershipId" | "members" | "name" | "status" | "type"
> & {
  memberIds: string[];
};
export type AvailabilityEditorValues = {
  membershipId: string;
  records: MemberAvailabilityRecord[];
  rules: AvailabilityRuleRecord[];
};
export type ShiftEditorValues = MemberShiftRecord & {
  membershipId: string;
};

export type StationEditorValidationErrors = Partial<
  Record<"name" | "description" | "teamId" | "status" | "form", string>
>;
export type TeamEditorValidationErrors = Partial<
  Record<"name" | "description" | "memberIds" | "leadMembershipId" | "status" | "form", string>
>;
export type AvailabilityEditorValidationErrors = Partial<
  Record<"form", string>
> & {
  records?: Record<string, Partial<Record<"startsAt" | "endsAt" | "timezone", string>>>;
  rules?: Record<string, Partial<Record<"dayOfWeek" | "startsAt" | "endsAt" | "timezone", string>>>;
};
export type ShiftEditorValidationErrors = Partial<
  Record<
    | "membershipId"
    | "teamId"
    | "stationId"
    | "eventId"
    | "startsAt"
    | "endsAt"
    | "timezone"
    | "status"
    | "form",
    string
  >
>;

export const STATION_STATUS_VALUES = [
  "active",
  "inactive",
] as const satisfies readonly StationStatusValue[];
export const TEAM_STATUS_VALUES = [
  "active",
  "inactive",
] as const satisfies readonly TeamStatusValue[];
export const SHIFT_STATUS_VALUES = [
  "scheduled",
  "confirmed",
  "in_progress",
  "completed",
  "cancelled",
  "no_show",
] as const satisfies readonly ShiftStatusValue[];

let stationDraftCounter = 0;
let teamDraftCounter = 0;
let availabilityRecordDraftCounter = 0;
let availabilityRuleDraftCounter = 0;
let shiftDraftCounter = 0;

function trimOrNull(value?: string | null) {
  const normalized = value?.trim();
  return normalized ? normalized : null;
}

function createStationDraftId() {
  stationDraftCounter += 1;
  return `station-draft-${Date.now()}-${stationDraftCounter}`;
}

function createTeamDraftId() {
  teamDraftCounter += 1;
  return `team-draft-${Date.now()}-${teamDraftCounter}`;
}

function createAvailabilityRecordDraftId() {
  availabilityRecordDraftCounter += 1;
  return `availability-record-draft-${Date.now()}-${availabilityRecordDraftCounter}`;
}

function createAvailabilityRuleDraftId() {
  availabilityRuleDraftCounter += 1;
  return `availability-rule-draft-${Date.now()}-${availabilityRuleDraftCounter}`;
}

function createShiftDraftId() {
  shiftDraftCounter += 1;
  return `shift-draft-${Date.now()}-${shiftDraftCounter}`;
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

export function createTeamEditorValues(
  values?: Partial<TeamEditorValues>
): TeamEditorValues {
  return {
    description: values?.description ?? null,
    id: values?.id ?? createTeamDraftId(),
    key: values?.key ?? null,
    leadMembershipId: values?.leadMembershipId ?? null,
    memberIds: values?.memberIds ?? values?.members?.map((member) => member.id) ?? [],
    members: values?.members ?? [],
    name: values?.name ?? "",
    status: values?.status ?? "active",
    type: values?.type ?? null,
  };
}

export function normalizeTeamEditorValues(
  values: TeamEditorValues
): TeamEditorValues {
  const uniqueMemberIds = Array.from(
    new Set((values.memberIds ?? []).map((memberId) => memberId.trim()).filter(Boolean))
  );

  return {
    ...values,
    description: trimOrNull(values.description),
    key: trimOrNull(values.key),
    leadMembershipId: trimOrNull(values.leadMembershipId),
    memberIds: uniqueMemberIds,
    name: values.name.trim(),
    status: trimOrNull(values.status) ?? "active",
    type: trimOrNull(values.type),
  };
}

export function validateTeamEditorValues(
  values: TeamEditorValues,
  t: (key: string) => string
): TeamEditorValidationErrors {
  const errors: TeamEditorValidationErrors = {};

  if (!values.name.trim()) {
    errors.name = t("teamStaff.teamEditor.errors.nameRequired");
  }

  if ((values.memberIds ?? []).length === 0) {
    errors.memberIds = t("teamStaff.teamEditor.errors.memberRequired");
  }

  if (
    values.leadMembershipId &&
    !(values.memberIds ?? []).includes(values.leadMembershipId)
  ) {
    errors.leadMembershipId = t("teamStaff.teamEditor.errors.leadMustBelong");
  }

  return errors;
}

export function hasTeamEditorErrors(
  errors?: TeamEditorValidationErrors | null
) {
  if (!errors) {
    return false;
  }

  return Object.values(errors).some(Boolean);
}

export function createAvailabilityRecordValues(
  values?: Partial<MemberAvailabilityRecord>
): MemberAvailabilityRecord {
  return {
    available: values?.available ?? true,
    endsAt: values?.endsAt ?? null,
    id: values?.id ?? createAvailabilityRecordDraftId(),
    notes: values?.notes ?? null,
    source: values?.source ?? "user",
    startsAt: values?.startsAt ?? null,
    status: values?.status ?? null,
    timezone: values?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC",
    type: values?.type ?? (values?.available === false ? "unavailable" : "available"),
  };
}

export function createAvailabilityRuleValues(
  values?: Partial<AvailabilityRuleRecord>
): AvailabilityRuleRecord {
  return {
    active: values?.active ?? true,
    available: values?.available ?? true,
    dayOfWeek: values?.dayOfWeek ?? 1,
    effectiveFrom: values?.effectiveFrom ?? null,
    effectiveUntil: values?.effectiveUntil ?? null,
    endsAt: values?.endsAt ?? "17:00",
    id: values?.id ?? createAvailabilityRuleDraftId(),
    membershipId: values?.membershipId ?? null,
    startsAt: values?.startsAt ?? "09:00",
    timezone: values?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC",
  };
}

export function createAvailabilityEditorValues(
  membershipId: string,
  values?: Partial<AvailabilityEditorValues>
): AvailabilityEditorValues {
  return {
    membershipId,
    records: (values?.records ?? []).map(createAvailabilityRecordValues),
    rules: (values?.rules ?? []).map(createAvailabilityRuleValues),
  };
}

export function normalizeAvailabilityEditorValues(
  values: AvailabilityEditorValues
): AvailabilityEditorValues {
  return {
    membershipId: values.membershipId,
    records: values.records.map((record) => ({
      ...record,
      notes: trimOrNull(record.notes),
      source: trimOrNull(record.source) ?? "user",
      timezone: trimOrNull(record.timezone) ?? "UTC",
      type: trimOrNull(record.type) ?? (record.available === false ? "unavailable" : "available"),
    })),
    rules: values.rules.map((rule) => ({
      ...rule,
      timezone: trimOrNull(rule.timezone) ?? "UTC",
    })),
  };
}

export function validateAvailabilityEditorValues(
  values: AvailabilityEditorValues,
  t: (key: string) => string
): AvailabilityEditorValidationErrors {
  const errors: AvailabilityEditorValidationErrors = {};

  values.records.forEach((record) => {
    const recordErrors: Partial<Record<"startsAt" | "endsAt" | "timezone", string>> = {};

    if (!record.startsAt) {
      recordErrors.startsAt = t("teamStaff.availabilityEditor.errors.startsAtRequired");
    }

    if (!record.endsAt) {
      recordErrors.endsAt = t("teamStaff.availabilityEditor.errors.endsAtRequired");
    }

    if (
      record.startsAt &&
      record.endsAt &&
      new Date(record.endsAt).getTime() <= new Date(record.startsAt).getTime()
    ) {
      recordErrors.endsAt = t("teamStaff.availabilityEditor.errors.endsAfterStart");
    }

    if (!record.timezone?.trim()) {
      recordErrors.timezone = t("teamStaff.shiftEditor.errors.timezoneRequired");
    }

    if (Object.keys(recordErrors).length > 0) {
      errors.records = errors.records ?? {};
      errors.records[record.id ?? createAvailabilityRecordDraftId()] = recordErrors;
    }
  });

  values.rules.forEach((rule) => {
    const ruleErrors: Partial<Record<"dayOfWeek" | "startsAt" | "endsAt" | "timezone", string>> = {};

    if (!rule.dayOfWeek || rule.dayOfWeek < 1 || rule.dayOfWeek > 7) {
      ruleErrors.dayOfWeek = t("teamStaff.availabilityEditor.errors.dayOfWeekRequired");
    }

    if (!rule.startsAt) {
      ruleErrors.startsAt = t("teamStaff.availabilityEditor.errors.ruleStartsAtRequired");
    }

    if (!rule.endsAt) {
      ruleErrors.endsAt = t("teamStaff.availabilityEditor.errors.ruleEndsAtRequired");
    }

    if (rule.startsAt && rule.endsAt && rule.endsAt <= rule.startsAt) {
      ruleErrors.endsAt = t("teamStaff.availabilityEditor.errors.ruleEndsAfterStart");
    }

    if (!rule.timezone?.trim()) {
      ruleErrors.timezone = t("teamStaff.shiftEditor.errors.timezoneRequired");
    }

    if (Object.keys(ruleErrors).length > 0) {
      errors.rules = errors.rules ?? {};
      errors.rules[rule.id ?? createAvailabilityRuleDraftId()] = ruleErrors;
    }
  });

  return errors;
}

export function hasAvailabilityEditorErrors(
  errors?: AvailabilityEditorValidationErrors | null
) {
  if (!errors) {
    return false;
  }

  if (errors.form) {
    return true;
  }

  return Boolean(
    (errors.records && Object.keys(errors.records).length > 0) ||
      (errors.rules && Object.keys(errors.rules).length > 0)
  );
}

export function createShiftEditorValues(
  values?: Partial<ShiftEditorValues>
): ShiftEditorValues {
  return {
    breakMinutes:
      typeof values?.breakMinutes === "number" && Number.isFinite(values.breakMinutes)
        ? values.breakMinutes
        : 0,
    conflicts: values?.conflicts ?? null,
    createdAt: values?.createdAt ?? null,
    endsAt: values?.endsAt ?? null,
    event: values?.event ?? null,
    eventId: values?.eventId ?? null,
    id: values?.id ?? createShiftDraftId(),
    member: values?.member ?? null,
    membershipId: values?.membershipId ?? values?.member?.id ?? "",
    notes: values?.notes ?? null,
    role: values?.role ?? null,
    startsAt: values?.startsAt ?? null,
    station: values?.station ?? null,
    stationId: values?.stationId ?? null,
    status: values?.status ?? "scheduled",
    team: values?.team ?? null,
    teamId: values?.teamId ?? null,
    timezone: values?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? "UTC",
    updatedAt: values?.updatedAt ?? null,
  };
}

export function normalizeShiftEditorValues(
  values: ShiftEditorValues
): ShiftEditorValues {
  return {
    ...values,
    eventId: trimOrNull(values.eventId),
    membershipId: values.membershipId.trim(),
    notes: trimOrNull(values.notes),
    role: trimOrNull(values.role),
    stationId: trimOrNull(values.stationId),
    teamId: trimOrNull(values.teamId),
    timezone: trimOrNull(values.timezone) ?? "UTC",
  };
}

export function validateShiftEditorValues(
  values: ShiftEditorValues,
  t: (key: string) => string
): ShiftEditorValidationErrors {
  const errors: ShiftEditorValidationErrors = {};

  if (!values.membershipId.trim()) {
    errors.membershipId = t("teamStaff.shiftEditor.errors.memberRequired");
  }

  if (!values.startsAt) {
    errors.startsAt = t("teamStaff.shiftEditor.errors.startsAtRequired");
  }

  if (!values.endsAt) {
    errors.endsAt = t("teamStaff.shiftEditor.errors.endsAtRequired");
  }

  if (
    values.startsAt &&
    values.endsAt &&
    new Date(values.endsAt).getTime() <= new Date(values.startsAt).getTime()
  ) {
    errors.endsAt = t("teamStaff.shiftEditor.errors.endsAfterStart");
  }

  if (!values.timezone?.trim()) {
    errors.timezone = t("teamStaff.shiftEditor.errors.timezoneRequired");
  }

  return errors;
}

export function hasShiftEditorErrors(
  errors?: ShiftEditorValidationErrors | null
) {
  if (!errors) {
    return false;
  }

  return Object.values(errors).some(Boolean);
}
