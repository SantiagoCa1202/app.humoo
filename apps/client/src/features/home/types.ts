import type { AlertTone } from "@/theme/status-config";
import type { AvailabilitySummaryRecord } from "@/features/team-staff";
import type { BeoRecord, BeoVersionRecord, DocumentRecord } from "@/features/documents";
import type { EventRecord } from "@/features/events";
import type { PrepListRecord } from "@/features/prep";
import type { TaskRecord, TaskSummaryRecord } from "@/features/tasks";

export type CommandCenterWorkspaceSummary = {
  activePrepLists?: number | null;
  eventsToday?: number | null;
  menus?: number | null;
  openTasks?: number | null;
  recipes?: number | null;
  teamMembers?: number | null;
};

export type CommandCenterPrepProgress = {
  blocked?: number | null;
  done?: number | null;
  inProgress?: number | null;
  skipped?: number | null;
  todo?: number | null;
  total?: number | null;
};

export type CommandCenterBeoAttentionReason =
  | "processing"
  | "processing_failed"
  | "review_required";

export type CommandCenterBeoAttentionItem = {
  beo?: BeoRecord | null;
  document: DocumentRecord;
  message: CommandCenterBeoAttentionReason;
  reason: CommandCenterBeoAttentionReason;
  tone: AlertTone;
  updatedAt?: string | null;
  version?: BeoVersionRecord | null;
};

export type CommandCenterAttentionItemType = "prep_blocked" | "tasks_overdue";

export type CommandCenterAttentionItem = {
  count?: number | null;
  tone: AlertTone;
  type: CommandCenterAttentionItemType;
};

export type CommandCenterRecord = {
  activePrep?: PrepListRecord | null;
  attentionItems: CommandCenterAttentionItem[];
  beoAttentionItems: CommandCenterBeoAttentionItem[];
  generatedAt?: string | null;
  myTasks: TaskRecord[];
  prepProgress?: CommandCenterPrepProgress | null;
  staffingSummary?: AvailabilitySummaryRecord | null;
  taskSummary?: TaskSummaryRecord | null;
  upcomingEvents: EventRecord[];
  workspace: {
    id: string;
    name?: string | null;
    timezone?: string | null;
  };
  workspaceSummary: CommandCenterWorkspaceSummary;
};
