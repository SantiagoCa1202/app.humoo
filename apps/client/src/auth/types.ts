export type AuthUser = {
  id: string;
  name: string;
  firstName: string;
  lastName: string;
  email: string;
  preferredLocale: string;
  timezone: string;
  status?: string | null;
};

export type SessionMode = "local-fallback" | "api";

export type WorkspaceSummary = {
  id: string;
  name: string;
  slug: string;
  defaultLocale: string;
  timezone: string;
  currency: string;
  status: string;
};

export type WorkspaceMembership = {
  id: string;
  workspaceId: string;
  userId: string;
  roleId: string | null;
  roleKey: string | null;
  roleName: string | null;
  status: string;
  joinedAt: string | null;
  workspace: WorkspaceSummary | null;
};

export type AppSession = {
  mode: SessionMode;
  token: string | null;
  user: AuthUser;
  memberships: WorkspaceMembership[];
  currentWorkspace: WorkspaceSummary | null;
  currentMembership: WorkspaceMembership | null;
  permissions: string[];
  createdAt: string;
};

export type SignInInput = {
  email: string;
  password: string;
};

export type SignUpInput = SignInInput & {
  firstName: string;
  lastName: string;
  invitationToken?: string | null;
};

export type ForgotPasswordResult = {
  message: string;
  previewEmail?: string | null;
  resetTokenPreview?: string | null;
  resetUrlPreview?: string | null;
};

export type ResetPasswordInput = {
  email: string;
  token: string;
  password: string;
  passwordConfirmation: string;
};

export type CreateOrganizationInput = {
  name: string;
  businessType: string;
  countryCode: string;
  currencyCode: string;
  timezone: string;
};

export type UpdateProfileInput = {
  firstName: string;
  lastName: string;
  timezone: string;
};
