import { apiRequest } from "@/api/client";
import type { WorkspaceSummary } from "@/auth/types";
import type {
  AuthSessionRecord,
  CreateInvitationResult,
  CreateWorkspaceInput,
  UpdateWorkspaceInput,
  WorkspaceAccess,
  WorkspaceInvitation,
  WorkspaceMember,
  WorkspaceRole,
} from "@/features/workspace/types";

type ApiPermission = {
  key: string;
};

type ApiRole = {
  id: string;
  key: string;
  name: string;
  permissions?: ApiPermission[] | null;
};

type ApiWorkspace = {
  id: string;
  name: string;
  slug: string;
  default_locale: string;
  timezone: string;
  currency: string;
  status: string;
};

type ApiUser = {
  id: string;
  name: string;
  email: string;
};

type ApiMembership = {
  id: string;
  workspace_id: string;
  user_id: string;
  role_id: string | null;
  status: string;
  joined_at: string | null;
  user?: ApiUser | null;
  role?: ApiRole | null;
};

type ApiWorkspaceAccess = {
  id: string;
  status: string;
  joined_at: string | null;
  workspace: ApiWorkspace;
  role: ApiRole | null;
  permissions: string[];
};

type ApiInvitation = {
  id: string;
  email: string;
  role_id: string | null;
  expires_at: string;
  is_expired?: boolean;
  role?: ApiRole | null;
};

type ApiSession = {
  id: string;
  last_seen_at: string | null;
  revoked_at: string | null;
  is_current: boolean;
  workspace?: {
    name: string;
  } | null;
  device?: {
    platform?: string | null;
    name?: string | null;
    last_ip?: string | null;
  } | null;
};

export async function listWorkspaces(
  authToken: string
): Promise<WorkspaceAccess[]> {
  const response = await apiRequest<{ data: ApiWorkspaceAccess[] }>("/workspaces", {
    authToken,
  });

  return response.data.map((access) => ({
    membershipId: access.id,
    status: access.status,
    joinedAt: access.joined_at,
    workspace: mapWorkspace(access.workspace),
    role: access.role ? mapRole(access.role) : null,
    permissions: access.permissions,
  }));
}

export async function createWorkspace(
  authToken: string,
  input: CreateWorkspaceInput
): Promise<WorkspaceAccess> {
  const response = await apiRequest<{ data: ApiWorkspaceAccess }>("/workspaces", {
    method: "POST",
    authToken,
    body: JSON.stringify({
      name: input.name.trim(),
      default_locale: input.defaultLocale,
      timezone: input.timezone.trim(),
      currency: input.currency.trim().toUpperCase(),
    }),
  });

  return {
    membershipId: response.data.id,
    status: response.data.status,
    joinedAt: response.data.joined_at,
    workspace: mapWorkspace(response.data.workspace),
    role: response.data.role ? mapRole(response.data.role) : null,
    permissions: response.data.permissions,
  };
}

export async function updateCurrentWorkspace(
  authToken: string,
  workspaceId: string,
  input: UpdateWorkspaceInput
): Promise<WorkspaceSummary> {
  const response = await apiRequest<{ data: ApiWorkspace }>("/workspaces/current", {
    method: "PATCH",
    authToken,
    workspaceId,
    body: JSON.stringify({
      name: input.name?.trim(),
      default_locale: input.defaultLocale,
      timezone: input.timezone?.trim(),
      currency: input.currency?.trim().toUpperCase(),
    }),
  });

  return mapWorkspace(response.data);
}

export async function listAuthSessions(
  authToken: string
): Promise<AuthSessionRecord[]> {
  const response = await apiRequest<{ data: ApiSession[] }>("/auth/sessions", {
    authToken,
  });

  return response.data.map((session) => ({
    id: session.id,
    lastSeenAt: session.last_seen_at,
    revokedAt: session.revoked_at,
    isCurrent: session.is_current,
    workspaceName: session.workspace?.name ?? null,
    device: session.device
      ? {
          platform: session.device.platform ?? null,
          name: session.device.name ?? null,
          lastIp: session.device.last_ip ?? null,
        }
      : null,
  }));
}

export async function revokeAuthSession(
  authToken: string,
  sessionId: string
): Promise<void> {
  await apiRequest<void>(`/auth/sessions/${sessionId}`, {
    method: "DELETE",
    authToken,
  });
}

export async function listWorkspaceRoles(
  authToken: string,
  workspaceId: string
): Promise<WorkspaceRole[]> {
  const response = await apiRequest<{ data: ApiRole[] }>(
    "/workspaces/current/roles",
    {
      authToken,
      workspaceId,
    }
  );

  return response.data.map(mapRole);
}

export async function listWorkspaceMembers(
  authToken: string,
  workspaceId: string
): Promise<WorkspaceMember[]> {
  const response = await apiRequest<{ data: ApiMembership[] }>(
    "/workspaces/current/members",
    {
      authToken,
      workspaceId,
    }
  );

  return response.data.map((membership) => ({
    id: membership.id,
    workspaceId: membership.workspace_id,
    userId: membership.user_id,
    roleId: membership.role_id,
    status: membership.status,
    joinedAt: membership.joined_at,
    user: membership.user
      ? {
          id: membership.user.id,
          name: membership.user.name,
          email: membership.user.email,
        }
      : null,
    role: membership.role ? mapRole(membership.role) : null,
  }));
}

export async function updateWorkspaceMember(
  authToken: string,
  workspaceId: string,
  memberId: string,
  input: {
    roleId?: string | null;
    status?: string;
  }
): Promise<WorkspaceMember> {
  const response = await apiRequest<{ data: ApiMembership }>(
    `/workspaces/current/members/${memberId}`,
    {
      method: "PATCH",
      authToken,
      workspaceId,
      body: JSON.stringify({
        role_id: input.roleId,
        status: input.status,
      }),
    }
  );

  return {
    id: response.data.id,
    workspaceId: response.data.workspace_id,
    userId: response.data.user_id,
    roleId: response.data.role_id,
    status: response.data.status,
    joinedAt: response.data.joined_at,
    user: response.data.user
      ? {
          id: response.data.user.id,
          name: response.data.user.name,
          email: response.data.user.email,
        }
      : null,
    role: response.data.role ? mapRole(response.data.role) : null,
  };
}

export async function removeWorkspaceMember(
  authToken: string,
  workspaceId: string,
  memberId: string
): Promise<void> {
  await apiRequest<void>(`/workspaces/current/members/${memberId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

export async function listWorkspaceInvitations(
  authToken: string,
  workspaceId: string
): Promise<WorkspaceInvitation[]> {
  const response = await apiRequest<{ data: ApiInvitation[] }>(
    "/workspaces/current/invitations",
    {
      authToken,
      workspaceId,
    }
  );

  return response.data.map((invitation) => ({
    id: invitation.id,
    email: invitation.email,
    roleId: invitation.role_id,
    expiresAt: invitation.expires_at,
    isExpired: Boolean(invitation.is_expired),
    role: invitation.role ? mapRole(invitation.role) : null,
  }));
}

export async function createWorkspaceInvitation(
  authToken: string,
  workspaceId: string,
  input: {
    email: string;
    roleId: string | null;
  }
): Promise<CreateInvitationResult> {
  const response = await apiRequest<{
    data: ApiInvitation;
    meta?: {
      invitation_token_preview?: string | null;
      accept_url_preview?: string | null;
    } | null;
  }>("/workspaces/current/invitations", {
    method: "POST",
    authToken,
    workspaceId,
    body: JSON.stringify({
      email: input.email.trim().toLowerCase(),
      role_id: input.roleId,
    }),
  });

  return {
    invitation: {
      id: response.data.id,
      email: response.data.email,
      roleId: response.data.role_id,
      expiresAt: response.data.expires_at,
      isExpired: Boolean(response.data.is_expired),
      role: response.data.role ? mapRole(response.data.role) : null,
    },
    invitationTokenPreview: response.meta?.invitation_token_preview ?? null,
    acceptUrlPreview: response.meta?.accept_url_preview ?? null,
  };
}

export async function cancelWorkspaceInvitation(
  authToken: string,
  workspaceId: string,
  invitationId: string
): Promise<void> {
  await apiRequest<void>(`/workspaces/current/invitations/${invitationId}`, {
    method: "DELETE",
    authToken,
    workspaceId,
  });
}

function mapWorkspace(workspace: ApiWorkspace): WorkspaceSummary {
  return {
    id: workspace.id,
    name: workspace.name,
    slug: workspace.slug,
    defaultLocale: workspace.default_locale,
    timezone: workspace.timezone,
    currency: workspace.currency,
    status: workspace.status,
  };
}

function mapRole(role: ApiRole): WorkspaceRole {
  return {
    id: role.id,
    key: role.key,
    name: role.name,
    permissionKeys: role.permissions?.map((permission) => permission.key) ?? [],
  };
}
