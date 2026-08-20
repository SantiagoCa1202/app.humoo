import { apiRequest } from "@/api/client";
import { coerceBeoRecord, coerceBeoVersionRecord, coerceDocumentRecord } from "@/features/documents";
import { coerceEventRecord } from "@/features/events/api";
import type {
  CommandCenterAttentionItem,
  CommandCenterBeoAttentionItem,
  CommandCenterPrepProgress,
  CommandCenterRecord,
  CommandCenterWorkspaceSummary,
} from "@/features/home/types";
import { coercePrepListRecord } from "@/features/prep/api";
import { coerceTaskRecord } from "@/features/tasks";
import type { AvailabilitySummaryRecord } from "@/features/team-staff";
import type { TaskSummaryRecord } from "@/features/tasks";

type ApiCommandCenterSummary = {
  active_prep_lists?: number | null;
  events_today?: number | null;
  menus?: number | null;
  open_tasks?: number | null;
  recipes?: number | null;
  team_members?: number | null;
};

type ApiPrepProgress = {
  blocked?: number | null;
  done?: number | null;
  in_progress?: number | null;
  skipped?: number | null;
  todo?: number | null;
  total?: number | null;
};

type ApiTaskSummary = {
  assigned?: number | null;
  blocked?: number | null;
  cancelled?: number | null;
  done?: number | null;
  in_progress?: number | null;
  overdue?: number | null;
  todo?: number | null;
  total?: number | null;
  unassigned?: number | null;
};

type ApiStaffingSummary = {
  active?: number | null;
  available?: number | null;
  invited?: number | null;
  on_shift?: number | null;
  total?: number | null;
  unavailable?: number | null;
};

type ApiBeoAttentionItem = {
  beo?: unknown;
  document: unknown;
  message: CommandCenterBeoAttentionItem["message"];
  reason: CommandCenterBeoAttentionItem["reason"];
  tone: CommandCenterBeoAttentionItem["tone"];
  updated_at?: string | null;
  version?: unknown;
};

type ApiAttentionItem = {
  count?: number | null;
  tone: CommandCenterAttentionItem["tone"];
  type: CommandCenterAttentionItem["type"];
};

type ApiCommandCenter = {
  active_prep?: unknown | null;
  attention_items?: ApiAttentionItem[] | null;
  beo_attention_items?: ApiBeoAttentionItem[] | null;
  generated_at?: string | null;
  my_tasks?: unknown[] | null;
  prep_progress?: ApiPrepProgress | null;
  staffing_summary?: ApiStaffingSummary | null;
  task_summary?: ApiTaskSummary | null;
  upcoming_events?: unknown[] | null;
  workspace: {
    id: string;
    name?: string | null;
    timezone?: string | null;
  };
  workspace_summary?: ApiCommandCenterSummary | null;
};

export async function getCommandCenter(
  authToken: string,
  workspaceId: string
): Promise<CommandCenterRecord> {
  const response = await apiRequest<{ data: ApiCommandCenter }>("/command-center", {
    authToken,
    workspaceId,
  });

  return mapCommandCenter(response.data);
}

function mapWorkspaceSummary(
  summary?: ApiCommandCenterSummary | null
): CommandCenterWorkspaceSummary {
  return {
    activePrepLists: summary?.active_prep_lists ?? null,
    eventsToday: summary?.events_today ?? null,
    menus: summary?.menus ?? null,
    openTasks: summary?.open_tasks ?? null,
    recipes: summary?.recipes ?? null,
    teamMembers: summary?.team_members ?? null,
  };
}

function mapPrepProgress(progress?: ApiPrepProgress | null): CommandCenterPrepProgress | null {
  if (!progress) {
    return null;
  }

  return {
    blocked: progress.blocked ?? null,
    done: progress.done ?? null,
    inProgress: progress.in_progress ?? null,
    skipped: progress.skipped ?? null,
    todo: progress.todo ?? null,
    total: progress.total ?? null,
  };
}

function mapTaskSummary(summary?: ApiTaskSummary | null): TaskSummaryRecord | null {
  if (!summary) {
    return null;
  }

  return {
    assigned: summary.assigned ?? null,
    blocked: summary.blocked ?? null,
    cancelled: summary.cancelled ?? null,
    done: summary.done ?? null,
    inProgress: summary.in_progress ?? null,
    overdue: summary.overdue ?? null,
    todo: summary.todo ?? null,
    total: summary.total ?? null,
    unassigned: summary.unassigned ?? null,
  };
}

function mapStaffingSummary(
  summary?: ApiStaffingSummary | null
): AvailabilitySummaryRecord | null {
  if (!summary) {
    return null;
  }

  return {
    active: summary.active ?? null,
    available: summary.available ?? null,
    invited: summary.invited ?? null,
    onShift: summary.on_shift ?? null,
    total: summary.total ?? null,
    unavailable: summary.unavailable ?? null,
  };
}

function mapBeoAttentionItem(item: ApiBeoAttentionItem): CommandCenterBeoAttentionItem | null {
  const document = coerceDocumentRecord(item.document);

  if (!document) {
    return null;
  }

  return {
    beo: coerceBeoRecord(item.beo),
    document,
    message: item.message,
    reason: item.reason,
    tone: item.tone,
    updatedAt: item.updated_at ?? null,
    version: coerceBeoVersionRecord(item.version),
  };
}

function mapAttentionItem(item: ApiAttentionItem): CommandCenterAttentionItem {
  return {
    count: item.count ?? null,
    tone: item.tone,
    type: item.type,
  };
}

function mapCommandCenter(payload: ApiCommandCenter): CommandCenterRecord {
  return {
    activePrep: coercePrepListRecord(payload.active_prep),
    attentionItems: (payload.attention_items ?? []).map(mapAttentionItem),
    beoAttentionItems: (payload.beo_attention_items ?? [])
      .map(mapBeoAttentionItem)
      .filter((item): item is CommandCenterBeoAttentionItem => Boolean(item)),
    generatedAt: payload.generated_at ?? null,
    myTasks: (payload.my_tasks ?? [])
      .map(coerceTaskRecord)
      .filter((task): task is NonNullable<typeof task> => Boolean(task)),
    prepProgress: mapPrepProgress(payload.prep_progress),
    staffingSummary: mapStaffingSummary(payload.staffing_summary),
    taskSummary: mapTaskSummary(payload.task_summary),
    upcomingEvents: (payload.upcoming_events ?? [])
      .map(coerceEventRecord)
      .filter((event): event is NonNullable<typeof event> => Boolean(event)),
    workspace: {
      id: payload.workspace.id,
      name: payload.workspace.name ?? null,
      timezone: payload.workspace.timezone ?? null,
    },
    workspaceSummary: mapWorkspaceSummary(payload.workspace_summary),
  };
}
