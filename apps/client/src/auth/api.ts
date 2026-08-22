import { Platform } from "react-native";

import { apiRequest } from "@/api/client";
import type {
  AppSession,
  AuthUser,
  ForgotPasswordResult,
  ResetPasswordInput,
  SignInInput,
  SignUpInput,
  UpdateProfileInput,
  WorkspaceMembership,
  WorkspaceSummary,
} from "@/auth/types";

type ApiUser = {
  id: string;
  name: string;
  email: string;
  locale?: string | null;
  timezone?: string | null;
  status?: string | null;
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

type ApiRole = {
  id: string;
  key: string;
  name: string;
};

type ApiMembership = {
  id: string;
  workspace_id: string;
  user_id: string;
  role_id: string | null;
  status: string;
  joined_at: string | null;
  workspace?: ApiWorkspace | null;
  role?: ApiRole | null;
};

type LoginResponse = {
  user: ApiUser;
  token: string;
};

type RegisterResponse = LoginResponse;

type MeResponse = {
  data: {
    user: ApiUser;
    memberships: ApiMembership[];
    current_workspace: ApiWorkspace | null;
    current_membership: ApiMembership | null;
    permissions: string[];
  };
};

type ForgotPasswordResponse = {
  message: string;
  data?: {
    email?: string | null;
    reset_token_preview?: string | null;
    reset_url_preview?: string | null;
  } | null;
};

type InvitationPreviewResponse = {
  data: {
    email: string;
    expires_at: string;
    workspace: ApiWorkspace;
    role: ApiRole | null;
  };
};

export type InvitationPreview = {
  email: string;
  expiresAt: string;
  workspace: WorkspaceSummary;
  role: {
    id: string;
    key: string;
    name: string;
  } | null;
};

export async function loginWithApi(input: SignInInput): Promise<AppSession> {
  const response = await apiRequest<LoginResponse>("/auth/login", {
    method: "POST",
    body: JSON.stringify({
      email: input.email.trim().toLowerCase(),
      password: input.password,
      device_name: getDeviceName(),
    }),
  });

  return refreshApiSession(response.token);
}

export async function registerWithApi(input: SignUpInput): Promise<AppSession> {
  const response = await apiRequest<RegisterResponse>("/auth/register", {
    method: "POST",
    body: JSON.stringify({
      first_name: input.firstName.trim(),
      last_name: input.lastName.trim(),
      email: input.email.trim().toLowerCase(),
      password: input.password,
      password_confirmation: input.password,
      invitation_token: input.invitationToken?.trim() || undefined,
      device_name: getDeviceName(),
    }),
  });

  return refreshApiSession(response.token);
}

export async function refreshApiSession(
  token: string,
  preferredWorkspaceId?: string | null,
  createdAt = new Date().toISOString()
): Promise<AppSession> {
  const initial = await apiRequest<MeResponse>("/auth/me", {
    authToken: token,
    workspaceId: preferredWorkspaceId ?? undefined,
  });

  const fallbackWorkspaceId =
    preferredWorkspaceId ??
    initial.data.current_workspace?.id ??
    initial.data.memberships[0]?.workspace_id ??
    null;

  const resolved =
    fallbackWorkspaceId &&
    initial.data.current_workspace?.id !== fallbackWorkspaceId
      ? await apiRequest<MeResponse>("/auth/me", {
          authToken: token,
          workspaceId: fallbackWorkspaceId,
        })
      : initial;

  return buildApiSession(token, resolved.data, createdAt);
}

export async function logoutFromApi(token: string): Promise<void> {
  await apiRequest<void>("/auth/logout", {
    method: "POST",
    authToken: token,
  });
}

export async function updateProfileWithApi(
  token: string,
  input: UpdateProfileInput,
  locale: "en" | "es",
): Promise<void> {
  await apiRequest("/account", {
    authToken: token,
    body: JSON.stringify({
      locale,
      name: `${input.firstName.trim()} ${input.lastName.trim()}`.trim(),
      timezone: input.timezone.trim(),
    }),
    method: "PATCH",
  });
}

export async function requestPasswordResetWithApi(
  email: string
): Promise<ForgotPasswordResult> {
  const response = await apiRequest<ForgotPasswordResponse>(
    "/auth/forgot-password",
    {
      method: "POST",
      body: JSON.stringify({
        email: email.trim().toLowerCase(),
      }),
    }
  );

  return {
    message: response.message,
    previewEmail: response.data?.email ?? null,
    resetTokenPreview: response.data?.reset_token_preview ?? null,
    resetUrlPreview: response.data?.reset_url_preview ?? null,
  };
}

export async function resetPasswordWithApi(
  input: ResetPasswordInput
): Promise<void> {
  await apiRequest<void>("/auth/reset-password", {
    method: "POST",
    body: JSON.stringify({
      email: input.email.trim().toLowerCase(),
      token: input.token.trim(),
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    }),
  });
}

export async function acceptInvitationWithApi(
  token: string,
  authToken: string,
  createdAt: string
): Promise<AppSession> {
  await apiRequest("/invitations/accept", {
    method: "POST",
    authToken,
    body: JSON.stringify({
      token: token.trim(),
    }),
  });

  return refreshApiSession(authToken, null, createdAt);
}

export async function previewInvitation(
  token: string
): Promise<InvitationPreview> {
  const response = await apiRequest<InvitationPreviewResponse>(
    `/invitations/${encodeURIComponent(token.trim())}`
  );

  return {
    email: response.data.email,
    expiresAt: response.data.expires_at,
    workspace: mapWorkspace(response.data.workspace),
    role: response.data.role
      ? {
          id: response.data.role.id,
          key: response.data.role.key,
          name: response.data.role.name,
        }
      : null,
  };
}

function buildApiSession(
  token: string,
  payload: MeResponse["data"],
  createdAt: string
): AppSession {
  return {
    mode: "api",
    token,
    user: mapUser(payload.user),
    memberships: payload.memberships.map(mapMembership),
    currentWorkspace: payload.current_workspace
      ? mapWorkspace(payload.current_workspace)
      : null,
    currentMembership: payload.current_membership
      ? mapMembership(payload.current_membership)
      : null,
    permissions: payload.permissions,
    createdAt,
  };
}

function mapUser(user: ApiUser): AuthUser {
  const { firstName, lastName } = splitDisplayName(user.name);

  return {
    id: user.id,
    name: user.name,
    firstName,
    lastName,
    email: user.email,
    preferredLocale: user.locale?.trim() || "en",
    timezone: user.timezone?.trim() || "UTC",
    status: user.status ?? null,
  };
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

function mapMembership(membership: ApiMembership): WorkspaceMembership {
  return {
    id: membership.id,
    workspaceId: membership.workspace_id,
    userId: membership.user_id,
    roleId: membership.role_id,
    roleKey: membership.role?.key ?? null,
    roleName: membership.role?.name ?? null,
    status: membership.status,
    joinedAt: membership.joined_at,
    workspace: membership.workspace ? mapWorkspace(membership.workspace) : null,
  };
}

function splitDisplayName(name: string): {
  firstName: string;
  lastName: string;
} {
  const normalized = name.trim();

  if (!normalized) {
    return {
      firstName: "Humoo",
      lastName: "",
    };
  }

  const [firstName, ...rest] = normalized.split(/\s+/);

  return {
    firstName,
    lastName: rest.join(" "),
  };
}

function getDeviceName(): string {
  return `humoo-expo-${Platform.OS}`;
}
