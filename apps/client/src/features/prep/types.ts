import type React from "react";
import type { ImageSourcePropType } from "react-native";

import type {
  PrepListStatus,
  PrepListVersionStatus,
  PrepTaskStatus,
} from "@/theme/status-config";

export type { PrepListStatus, PrepListVersionStatus, PrepTaskStatus } from "@/theme/status-config";

export type PrepUserReference = {
  id?: string | null;
  name?: string | null;
  source?: ImageSourcePropType;
};

export type PrepEventReference = {
  id?: string | null;
  name?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
  timezone?: string | null;
};

export type PrepUnitReference = {
  id?: string | null;
  key?: string | null;
  name?: string | null;
  symbol?: string | null;
};

export type PrepItemAssignmentRecord = {
  acceptedAt?: string | null;
  assignedAt?: string | null;
  assignedBy?: PrepUserReference | null;
  completedAt?: string | null;
  id?: string | null;
  isPrimary?: boolean | null;
  membershipId?: string | null;
  notes?: string | null;
  roleLabel?: string | null;
  status?: "assigned" | "accepted" | "declined" | "completed" | "cancelled" | null;
  user?: PrepUserReference | null;
};

export type PrepItemRecord = {
  actualQuantity?: number | null;
  actualUnit?: PrepUnitReference | null;
  actualUnitId?: string | null;
  assignments?: PrepItemAssignmentRecord[] | null;
  blockedReason?: string | null;
  clientId?: string | null;
  completedAt?: string | null;
  completedBy?: PrepUserReference | null;
  createdAt?: string | null;
  description?: string | null;
  dueAt?: string | null;
  generated?: boolean | null;
  id: string | null;
  metadata?: Record<string, unknown> | null;
  notes?: string | null;
  portions?: number | null;
  position?: number | null;
  prepSectionId?: string | null;
  priority?: "low" | "normal" | "high" | "urgent" | null;
  quantity?: number | null;
  recipeId?: string | null;
  recipeName?: string | null;
  recipeVersionId?: string | null;
  requiresConfirmation?: boolean | null;
  scaleFactor?: number | null;
  source?: string | null;
  startedAt?: string | null;
  startsAt?: string | null;
  status?: PrepTaskStatus | null;
  title: string;
  unit?: PrepUnitReference | null;
  unitId?: string | null;
  unitLabel?: string | null;
  updatedAt?: string | null;
  updatedBy?: PrepUserReference | null;
  version?: number | null;
  yieldQuantity?: number | null;
  yieldUnit?: PrepUnitReference | null;
  yieldUnitId?: string | null;
};

export type PrepSectionRecord = {
  dueAt?: string | null;
  id: string | null;
  items?: PrepItemRecord[] | null;
  name: string;
  notes?: string | null;
  position?: number | null;
  prepListVersionId?: string | null;
  productionDate?: string | null;
  startsAt?: string | null;
  type?: string | null;
};

export type PrepListVersionRecord = {
  approvedAt?: string | null;
  approvedBy?: PrepUserReference | null;
  beoVersionId?: string | null;
  changeSummary?: string | null;
  createdAt?: string | null;
  createdBy?: PrepUserReference | null;
  eventStartsAtSnapshot?: string | null;
  generationMetadata?: Record<string, unknown> | null;
  guestCountSnapshot?: number | null;
  id: string | null;
  locked?: boolean | null;
  lockedAt?: string | null;
  lockedBy?: PrepUserReference | null;
  menuVersionId?: string | null;
  prepListId?: string | null;
  revision?: number | null;
  sections?: PrepSectionRecord[] | null;
  source?: "manual" | "ai" | "regeneration" | "import" | null;
  status?: PrepListVersionStatus | null;
  version: number;
};

export type PrepListProgressRecord = {
  assignedStaff?: PrepUserReference[] | null;
  assignedStaffCount?: number | null;
  blocked?: number | null;
  completed?: number | null;
  dueAt?: string | null;
  inProgress?: number | null;
  percentage?: number | null;
  remaining?: number | null;
  skipped?: number | null;
  total?: number | null;
  unassigned?: number | null;
};

export type PrepItemConflictType =
  | "version_conflict"
  | "remote_update"
  | "stale_data"
  | "status_changed"
  | "assignment_changed"
  | "quantity_changed";

export type PrepGenerationSource = "manual" | "ai" | "regeneration" | "import";

export type PrepGenerationOptionsRecord = {
  assignmentMembershipId?: string | null;
  beoVersionId?: string | null;
  dueAt?: string | null;
  eventId?: string | null;
  guestCount?: number | null;
  includeAssignments?: boolean | null;
  menuVersionId?: string | null;
  notes?: string | null;
  preserveAssignments?: boolean | null;
  preserveCompletedItems?: boolean | null;
  source?: PrepGenerationSource | null;
};

export type PrepGenerationAvailableOptions = {
  allowAssignment?: boolean;
  allowDueAt?: boolean;
  allowGuestCount?: boolean;
  allowIncludeAssignments?: boolean;
  allowMenuVersion?: boolean;
  allowNotes?: boolean;
  allowPreserveAssignments?: boolean;
  allowPreserveCompletedItems?: boolean;
  allowSourceSelection?: boolean;
  allowBeoVersion?: boolean;
};

export type PrepGenerationWarning = {
  description?: string | null;
  id?: string;
  title: string;
  tone?: "warning" | "danger" | "info";
};

export type PrepGenerationPreviewRecord = {
  estimatedAssignments?: number | null;
  estimatedItems?: number | null;
  event?: PrepEventReference | null;
  items?: PrepItemRecord[] | null;
  menuLabel?: string | null;
  metadata?: Record<string, string | number | null | undefined> | null;
  prepList?: PrepListRecord | null;
  progress?: PrepListProgressRecord | null;
  summary?: string | null;
  warnings?: PrepGenerationWarning[] | null;
};

export type PrepVersionComparisonChange = {
  after: React.ReactNode;
  before: React.ReactNode;
  id?: string;
  label: React.ReactNode;
};

export type PrepListRecord = {
  blockedItems?: number | null;
  completedAt?: string | null;
  completedBy?: PrepUserReference | null;
  completedItems?: number | null;
  createdAt?: string | null;
  createdBy?: PrepUserReference | null;
  currentVersion?: number | null;
  event?: PrepEventReference | null;
  eventId?: string | null;
  id: string;
  metadata?: Record<string, unknown> | null;
  name: string;
  productionEndsAt?: string | null;
  productionStartsAt?: string | null;
  status?: PrepListStatus | null;
  timezone?: string | null;
  totalItems?: number | null;
  updatedAt?: string | null;
  updatedBy?: PrepUserReference | null;
};

export type PrepDisplayRecord = PrepListRecord;

export type PrepListDetailRecord = {
  currentVersion?: PrepListVersionRecord | null;
  prepList: PrepListRecord;
  progress?: PrepListProgressRecord | null;
  versions?: PrepListVersionRecord[] | null;
};

export type PrepListCursorPage = {
  data: PrepListRecord[];
  nextCursor: string | null;
  nextPageUrl: string | null;
  path: string;
  perPage: number;
  prevCursor: string | null;
  prevPageUrl: string | null;
};

export type PrepGenerationResultRecord = {
  currentVersion?: PrepListVersionRecord | null;
  prepList?: PrepListRecord | null;
  preview: PrepGenerationPreviewRecord;
};
