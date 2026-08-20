import { apiRequest } from "@/api/client";
import type { PrepListEditorValues } from "@/features/prep/forms";
import type {
  PrepGenerationOptionsRecord,
  PrepGenerationResultRecord,
  PrepGenerationPreviewRecord,
  PrepItemRecord,
  PrepListCursorPage,
  PrepListDetailRecord,
  PrepListProgressRecord,
  PrepListRecord,
  PrepListVersionRecord,
  PrepSectionRecord,
  PrepUnitReference,
  PrepUserReference,
} from "@/features/prep/types";

type ApiUser = {
  id?: string | null;
  name?: string | null;
};

type ApiUnit = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  symbol?: string | null;
};

type ApiAssignment = {
  accepted_at?: string | null;
  assigned_at?: string | null;
  assigned_by?: ApiUser | null;
  completed_at?: string | null;
  id?: string | null;
  is_primary?: boolean | null;
  membership_id?: string | null;
  notes?: string | null;
  role_label?: string | null;
  status?: "assigned" | "accepted" | "declined" | "completed" | "cancelled" | null;
  user?: ApiUser | null;
};

type ApiItem = {
  actual_quantity?: number | null;
  actual_unit?: ApiUnit | null;
  actual_unit_id?: string | null;
  assignments?: ApiAssignment[] | null;
  blocked_reason?: string | null;
  completed_at?: string | null;
  completed_by?: ApiUser | null;
  created_at?: string | null;
  description?: string | null;
  due_at?: string | null;
  generated?: boolean | null;
  id?: string | null;
  metadata?: Record<string, unknown> | null;
  notes?: string | null;
  portions?: number | null;
  position?: number | null;
  prep_section_id?: string | null;
  priority?: PrepItemRecord["priority"] | null;
  quantity?: number | null;
  recipe?: { id?: string | null; name?: string | null } | null;
  recipe_id?: string | null;
  recipe_version?: { id?: string | null; name?: string | null; version?: number | null } | null;
  recipe_version_id?: string | null;
  requires_confirmation?: boolean | null;
  scale_factor?: number | null;
  source?: string | null;
  started_at?: string | null;
  starts_at?: string | null;
  status?: PrepItemRecord["status"] | null;
  title: string;
  unit?: ApiUnit | null;
  unit_id?: string | null;
  updated_at?: string | null;
  updated_by?: ApiUser | null;
  version?: number | null;
  yield_quantity?: number | null;
  yield_unit?: ApiUnit | null;
  yield_unit_id?: string | null;
};

type ApiSection = {
  due_at?: string | null;
  id?: string | null;
  items?: ApiItem[] | null;
  name: string;
  notes?: string | null;
  position?: number | null;
  prep_list_version_id?: string | null;
  production_date?: string | null;
  starts_at?: string | null;
  type?: string | null;
};

type ApiVersion = {
  approved_at?: string | null;
  approved_by?: ApiUser | null;
  change_summary?: string | null;
  created_at?: string | null;
  created_by?: ApiUser | null;
  event_starts_at_snapshot?: string | null;
  generation_metadata?: Record<string, unknown> | null;
  guest_count_snapshot?: number | null;
  id?: string | null;
  locked?: boolean | null;
  locked_at?: string | null;
  locked_by?: ApiUser | null;
  menu_version?: { id?: string | null; name?: string | null; version?: number | null } | null;
  menu_version_id?: string | null;
  prep_list_id?: string | null;
  progress?: {
    assigned_staff_count?: number | null;
    blocked?: number | null;
    completed?: number | null;
    in_progress?: number | null;
    remaining?: number | null;
    skipped?: number | null;
    total?: number | null;
    unassigned?: number | null;
  } | null;
  revision?: number | null;
  sections?: ApiSection[] | null;
  source?: PrepListVersionRecord["source"] | null;
  status?: PrepListVersionRecord["status"] | null;
  version: number;
};

type ApiList = {
  blocked_items?: number | null;
  completed_at?: string | null;
  completed_by?: ApiUser | null;
  completed_items?: number | null;
  created_at?: string | null;
  created_by?: ApiUser | null;
  current_version?: number | null;
  current_version_id?: string | null;
  current_version_record?: ApiVersion | null;
  event?: {
    ends_at?: string | null;
    id?: string | null;
    name?: string | null;
    starts_at?: string | null;
    timezone?: string | null;
  } | null;
  event_id?: string | null;
  id: string;
  metadata?: Record<string, unknown> | null;
  name: string;
  production_ends_at?: string | null;
  production_starts_at?: string | null;
  progress?: ApiProgress | null;
  status?: PrepListRecord["status"] | null;
  timezone?: string | null;
  total_items?: number | null;
  updated_at?: string | null;
  updated_by?: ApiUser | null;
  versions?: ApiVersion[] | null;
};

type ApiProgress = {
  assigned_staff_count?: number | null;
  blocked?: number | null;
  completed?: number | null;
  in_progress?: number | null;
  remaining?: number | null;
  skipped?: number | null;
  total?: number | null;
  unassigned?: number | null;
};

type ApiCursorResponse = {
  data: ApiList[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
};

type ApiGenerationResponse = {
  data: {
    estimated_assignments?: number | null;
    estimated_items?: number | null;
    event?: ApiList["event"] | null;
    items?: ApiItem[] | null;
    menu_label?: string | null;
    prep_list?: ApiList | null;
    progress?: ApiProgress | null;
    summary?: string | null;
    version?: ApiVersion | null;
    warnings?: PrepGenerationPreviewRecord["warnings"] | null;
  };
};

function mapUser(user?: ApiUser | null): PrepUserReference | null {
  if (!user) {
    return null;
  }

  return {
    id: user.id ?? null,
    name: user.name ?? null,
  };
}

function mapUnit(unit?: ApiUnit | null): PrepUnitReference | null {
  if (!unit) {
    return null;
  }

  return {
    id: unit.id ?? null,
    key: unit.key ?? null,
    name: unit.name ?? null,
    symbol: unit.symbol ?? null,
  };
}

function mapAssignment(assignment: ApiAssignment) {
  return {
    acceptedAt: assignment.accepted_at ?? null,
    assignedAt: assignment.assigned_at ?? null,
    assignedBy: mapUser(assignment.assigned_by),
    completedAt: assignment.completed_at ?? null,
    id: assignment.id ?? null,
    isPrimary: assignment.is_primary ?? null,
    membershipId: assignment.membership_id ?? null,
    notes: assignment.notes ?? null,
    roleLabel: assignment.role_label ?? null,
    status: assignment.status ?? null,
    user: mapUser(assignment.user),
  };
}

export function mapPrepItem(item: ApiItem): PrepItemRecord {
  return {
    actualQuantity: item.actual_quantity ?? null,
    actualUnit: mapUnit(item.actual_unit),
    actualUnitId: item.actual_unit_id ?? null,
    assignments: item.assignments?.map(mapAssignment) ?? [],
    blockedReason: item.blocked_reason ?? null,
    completedAt: item.completed_at ?? null,
    completedBy: mapUser(item.completed_by),
    createdAt: item.created_at ?? null,
    description: item.description ?? null,
    dueAt: item.due_at ?? null,
    generated: item.generated ?? null,
    id: item.id ?? null,
    metadata: item.metadata ?? null,
    notes: item.notes ?? null,
    portions: item.portions ?? null,
    position: item.position ?? null,
    prepSectionId: item.prep_section_id ?? null,
    priority: item.priority ?? null,
    quantity: item.quantity ?? null,
    recipeId: item.recipe_id ?? item.recipe?.id ?? null,
    recipeName: item.recipe_version?.name ?? item.recipe?.name ?? null,
    recipeVersionId: item.recipe_version_id ?? item.recipe_version?.id ?? null,
    requiresConfirmation: item.requires_confirmation ?? null,
    scaleFactor: item.scale_factor ?? null,
    source: item.source ?? null,
    startedAt: item.started_at ?? null,
    startsAt: item.starts_at ?? null,
    status: item.status ?? null,
    title: item.title,
    unit: mapUnit(item.unit),
    unitId: item.unit_id ?? null,
    updatedAt: item.updated_at ?? null,
    updatedBy: mapUser(item.updated_by),
    version: item.version ?? null,
    yieldQuantity: item.yield_quantity ?? null,
    yieldUnit: mapUnit(item.yield_unit),
    yieldUnitId: item.yield_unit_id ?? null,
  };
}

function mapSection(section: ApiSection): PrepSectionRecord {
  return {
    dueAt: section.due_at ?? null,
    id: section.id ?? null,
    items: section.items?.map(mapPrepItem) ?? [],
    name: section.name,
    notes: section.notes ?? null,
    position: section.position ?? null,
    prepListVersionId: section.prep_list_version_id ?? null,
    productionDate: section.production_date ?? null,
    startsAt: section.starts_at ?? null,
    type: section.type ?? null,
  };
}

function mapProgress(progress?: ApiProgress | null): PrepListProgressRecord | null {
  if (!progress) {
    return null;
  }

  return {
    assignedStaffCount: progress.assigned_staff_count ?? null,
    blocked: progress.blocked ?? null,
    completed: progress.completed ?? null,
    inProgress: progress.in_progress ?? null,
    remaining: progress.remaining ?? null,
    skipped: progress.skipped ?? null,
    total: progress.total ?? null,
    unassigned: progress.unassigned ?? null,
  };
}

export function coercePrepProgressRecord(value: unknown): PrepListProgressRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapProgress(value as ApiProgress);
}

function mapVersion(version?: ApiVersion | null): PrepListVersionRecord | null {
  if (!version) {
    return null;
  }

  return {
    approvedAt: version.approved_at ?? null,
    approvedBy: mapUser(version.approved_by),
    changeSummary: version.change_summary ?? null,
    createdAt: version.created_at ?? null,
    createdBy: mapUser(version.created_by),
    eventStartsAtSnapshot: version.event_starts_at_snapshot ?? null,
    generationMetadata: version.generation_metadata ?? null,
    guestCountSnapshot: version.guest_count_snapshot ?? null,
    id: version.id ?? null,
    locked: version.locked ?? null,
    lockedAt: version.locked_at ?? null,
    lockedBy: mapUser(version.locked_by),
    menuVersionId: version.menu_version_id ?? version.menu_version?.id ?? null,
    prepListId: version.prep_list_id ?? null,
    revision: version.revision ?? null,
    sections: version.sections?.map(mapSection) ?? [],
    source: version.source ?? null,
    status: version.status ?? null,
    version: version.version,
  };
}

function mapList(list: ApiList): PrepListRecord {
  return {
    blockedItems: list.blocked_items ?? null,
    completedAt: list.completed_at ?? null,
    completedBy: mapUser(list.completed_by),
    completedItems: list.completed_items ?? null,
    createdAt: list.created_at ?? null,
    createdBy: mapUser(list.created_by),
    currentVersion: list.current_version ?? null,
    event: list.event
      ? {
          endsAt: list.event.ends_at ?? null,
          id: list.event.id ?? null,
          name: list.event.name ?? null,
          startsAt: list.event.starts_at ?? null,
          timezone: list.event.timezone ?? null,
        }
      : null,
    eventId: list.event_id ?? list.event?.id ?? null,
    id: list.id,
    metadata: list.metadata ?? null,
    name: list.name,
    productionEndsAt: list.production_ends_at ?? null,
    productionStartsAt: list.production_starts_at ?? null,
    status: list.status ?? null,
    timezone: list.timezone ?? null,
    totalItems: list.total_items ?? null,
    updatedAt: list.updated_at ?? null,
    updatedBy: mapUser(list.updated_by),
  };
}

export function coercePrepListRecord(value: unknown): PrepListRecord | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  return mapList(value as ApiList);
}

function buildPrepListPayload(values: PrepListEditorValues) {
  return {
    event_id: values.eventId,
    name: values.name.trim(),
    production_ends_at: values.productionEndsAt ?? null,
    production_starts_at: values.productionStartsAt ?? null,
    timezone: values.timezone ?? null,
  };
}

function buildGenerationPayload(values: PrepGenerationOptionsRecord, preview = false) {
  return {
    assignment_membership_id: values.assignmentMembershipId ?? null,
    beo_version_id: values.beoVersionId ?? null,
    due_at: values.dueAt ?? null,
    guest_count: values.guestCount ?? null,
    include_assignments: values.includeAssignments ?? false,
    menu_version_id: values.menuVersionId ?? null,
    notes: values.notes ?? null,
    preserve_assignments: values.preserveAssignments ?? false,
    preserve_completed_items: values.preserveCompletedItems ?? false,
    preview,
    source: values.source ?? "manual",
  };
}

function mapGenerationResult(response: ApiGenerationResponse["data"]): PrepGenerationResultRecord {
  const prepList = response.prep_list ? mapList(response.prep_list) : null;
  const preview: PrepGenerationPreviewRecord = {
    estimatedAssignments: response.estimated_assignments ?? null,
    estimatedItems: response.estimated_items ?? null,
    event: response.event
      ? {
          endsAt: response.event.ends_at ?? null,
          id: response.event.id ?? null,
          name: response.event.name ?? null,
          startsAt: response.event.starts_at ?? null,
          timezone: response.event.timezone ?? null,
        }
      : null,
    items: response.items?.map(mapPrepItem) ?? [],
    menuLabel: response.menu_label ?? null,
    prepList,
    progress: mapProgress(response.progress),
    summary: response.summary ?? null,
    warnings: response.warnings ?? [],
  };

  return {
    currentVersion: mapVersion(response.version),
    prepList,
    preview,
  };
}

export async function listPrepLists(
  authToken: string,
  workspaceId: string,
  filters: { cursor?: string | null; eventId?: string | null; perPage?: number; search?: string; status?: string | null } = {}
): Promise<PrepListCursorPage> {
  const response = await apiRequest<ApiCursorResponse>("/prep-lists", {
    authToken,
    query: {
      cursor: filters.cursor ?? undefined,
      event_id: filters.eventId ?? undefined,
      per_page: filters.perPage ?? undefined,
      search: filters.search?.trim() || undefined,
      status: filters.status ?? undefined,
    },
    workspaceId,
  });

  return {
    data: response.data.map(mapList),
    nextCursor: response.next_cursor,
    nextPageUrl: response.next_page_url,
    path: response.path,
    perPage: response.per_page,
    prevCursor: response.prev_cursor,
    prevPageUrl: response.prev_page_url,
  };
}

export async function getPrepList(
  authToken: string,
  workspaceId: string,
  prepListId: string
): Promise<PrepListDetailRecord> {
  const response = await apiRequest<{ data: ApiList }>(`/prep-lists/${prepListId}`, {
    authToken,
    workspaceId,
  });

  return {
    currentVersion: mapVersion(response.data.current_version_record),
    prepList: mapList(response.data),
    progress: mapProgress(response.data.progress),
    versions: response.data.versions?.map((version) => mapVersion(version)).filter(Boolean) as PrepListVersionRecord[],
  };
}

export async function createPrepList(
  authToken: string,
  workspaceId: string,
  values: PrepListEditorValues
): Promise<PrepListDetailRecord> {
  const response = await apiRequest<{ data: ApiList }>("/prep-lists", {
    method: "POST",
    authToken,
    body: JSON.stringify(buildPrepListPayload(values)),
    workspaceId,
  });

  return {
    currentVersion: mapVersion(response.data.current_version_record),
    prepList: mapList(response.data),
    progress: mapProgress(response.data.progress),
    versions: response.data.versions?.map((version) => mapVersion(version)).filter(Boolean) as PrepListVersionRecord[],
  };
}

export async function getPrepVersions(
  authToken: string,
  workspaceId: string,
  prepListId: string
): Promise<PrepListVersionRecord[]> {
  const response = await apiRequest<{ data: ApiVersion[] }>(`/prep-lists/${prepListId}/versions`, {
    authToken,
    workspaceId,
  });

  return response.data.map((version) => mapVersion(version)).filter(Boolean) as PrepListVersionRecord[];
}

export async function previewPrepGeneration(
  authToken: string,
  workspaceId: string,
  prepListId: string,
  values: PrepGenerationOptionsRecord
): Promise<PrepGenerationResultRecord> {
  const response = await apiRequest<ApiGenerationResponse>(`/prep-lists/${prepListId}/generate`, {
    method: "POST",
    authToken,
    body: JSON.stringify(buildGenerationPayload(values, true)),
    workspaceId,
  });

  return mapGenerationResult(response.data);
}

export async function generatePrep(
  authToken: string,
  workspaceId: string,
  prepListId: string,
  values: PrepGenerationOptionsRecord
): Promise<PrepGenerationResultRecord> {
  const response = await apiRequest<ApiGenerationResponse>(`/prep-lists/${prepListId}/generate`, {
    method: "POST",
    authToken,
    body: JSON.stringify(buildGenerationPayload(values)),
    workspaceId,
  });

  return mapGenerationResult(response.data);
}

export async function regeneratePrep(
  authToken: string,
  workspaceId: string,
  prepListId: string,
  values: PrepGenerationOptionsRecord
): Promise<PrepGenerationResultRecord> {
  const response = await apiRequest<ApiGenerationResponse>(`/prep-lists/${prepListId}/regenerate`, {
    method: "POST",
    authToken,
    body: JSON.stringify(buildGenerationPayload({ ...values, source: "regeneration" }, true)),
    workspaceId,
  });

  return mapGenerationResult(response.data);
}

export async function confirmPrepRegeneration(
  authToken: string,
  workspaceId: string,
  prepListId: string,
  values: PrepGenerationOptionsRecord
): Promise<PrepGenerationResultRecord> {
  const response = await apiRequest<ApiGenerationResponse>(`/prep-lists/${prepListId}/regenerate`, {
    method: "POST",
    authToken,
    body: JSON.stringify(buildGenerationPayload({ ...values, source: "regeneration" })),
    workspaceId,
  });

  return mapGenerationResult(response.data);
}

export async function updatePrepItem(
  authToken: string,
  workspaceId: string,
  itemId: string,
  values: PrepItemRecord
): Promise<PrepItemRecord> {
  const response = await apiRequest<{ data: ApiItem }>(`/prep-items/${itemId}`, {
    method: "PATCH",
    authToken,
    body: JSON.stringify({
      actual_quantity: values.actualQuantity ?? null,
      actual_unit_id: values.actualUnitId ?? null,
      assignment_membership_id: values.assignments?.[0]?.membershipId ?? null,
      blocked_reason: values.blockedReason ?? null,
      description: values.description ?? null,
      due_at: values.dueAt ?? null,
      notes: values.notes ?? null,
      portions: values.portions ?? null,
      prep_section_id: values.prepSectionId ?? null,
      priority: values.priority ?? null,
      quantity: values.quantity ?? null,
      starts_at: values.startsAt ?? null,
      status: values.status ?? null,
      title: values.title.trim(),
      unit_id: values.unitId ?? null,
      version: values.version ?? 1,
      yield_quantity: values.yieldQuantity ?? null,
      yield_unit_id: values.yieldUnitId ?? null,
    }),
    workspaceId,
  });

  return mapPrepItem(response.data);
}
