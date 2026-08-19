import type { WorkspaceMembership, WorkspaceSummary } from "@/auth/types";

export type WorkspaceRole = {
  id: string;
  key: string;
  name: string;
  permissionKeys: string[];
};

export type WorkspaceAccess = {
  membershipId: string;
  status: string;
  joinedAt: string | null;
  workspace: WorkspaceSummary;
  role: WorkspaceRole | null;
  permissions: string[];
};

export type WorkspaceMember = {
  id: string;
  workspaceId: string;
  userId: string;
  roleId: string | null;
  status: string;
  joinedAt: string | null;
  user: {
    id: string;
    name: string;
    email: string;
  } | null;
  role: WorkspaceRole | null;
};

export type WorkspaceInvitation = {
  id: string;
  email: string;
  roleId: string | null;
  expiresAt: string;
  isExpired: boolean;
  role: WorkspaceRole | null;
};

export type AuthSessionRecord = {
  id: string;
  lastSeenAt: string | null;
  revokedAt: string | null;
  isCurrent: boolean;
  workspaceName: string | null;
  device: {
    platform: string | null;
    name: string | null;
    lastIp: string | null;
  } | null;
};

export type CreateInvitationResult = {
  invitation: WorkspaceInvitation;
  invitationTokenPreview: string | null;
  acceptUrlPreview: string | null;
};

export type CreateWorkspaceInput = {
  name: string;
  defaultLocale: "en" | "es";
  timezone: string;
  currency: string;
};

export type UpdateWorkspaceInput = Partial<CreateWorkspaceInput>;

export type WorkspaceStatus = "loading" | "ready" | "workspace_required" | "error";

export type ActiveWorkspaceContext = {
  access: WorkspaceAccess | null;
  membership: WorkspaceMembership | null;
  permissions: string[];
};
